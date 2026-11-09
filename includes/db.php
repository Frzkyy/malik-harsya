<?php
require_once __DIR__ . '/config.php';

function db(): PDO
{
    static $pdo = null;
    if ($pdo) return $pdo;

    $path = env('DB_PATH', 'data/rsvp.db');
    // Path relatif dihitung dari root proyek, bukan dari folder script yang sedang jalan
    // (kalau tidak, dashboard/ akan membuat database sendiri di dashboard/data/).
    if (!preg_match('#^(/|[A-Za-z]:[\\/])#', $path)) {
        $path = APP_ROOT . '/' . $path;
    }
    $dir  = dirname($path);

    if (!is_dir($dir) && !mkdir($dir, 0750, true) && !is_dir($dir)) {
        throw new RuntimeException("Folder DB tidak bisa dibuat: $dir");
    }

    $pdo = new PDO('sqlite:' . $path, null, null, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    $pdo->exec('PRAGMA journal_mode = WAL');
    $pdo->exec('PRAGMA busy_timeout = 5000');
    $pdo->exec('PRAGMA foreign_keys = ON');

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS rsvp (
            id         INTEGER PRIMARY KEY AUTOINCREMENT,
            nama       TEXT    NOT NULL,
            status     TEXT    NOT NULL CHECK (status IN ('hadir','tidak','ragu')),
            jumlah     INTEGER NOT NULL DEFAULT 1 CHECK (jumlah BETWEEN 0 AND 10),
            ucapan     TEXT,
            created_at TEXT    NOT NULL DEFAULT CURRENT_TIMESTAMP
        )
    ");
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_rsvp_created ON rsvp (created_at DESC)');

    // Daftar tamu undangan; slug dipakai di link pribadi (/slug atau ?tamu=slug).
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS tamu (
            id         INTEGER PRIMARY KEY AUTOINCREMENT,
            nama       TEXT    NOT NULL,
            slug       TEXT    NOT NULL UNIQUE,
            created_at TEXT    NOT NULL DEFAULT CURRENT_TIMESTAMP
        )
    ");
    // RSVP yang dikirim lewat link tamu ditandai dengan tamu_id (kosong = RSVP umum).
    $kolom_rsvp = array_column($pdo->query('PRAGMA table_info(rsvp)')->fetchAll(), 'name');
    if (!in_array('tamu_id', $kolom_rsvp, true)) {
        $pdo->exec('ALTER TABLE rsvp ADD COLUMN tamu_id INTEGER');
    }
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_rsvp_tamu ON rsvp (tamu_id)');

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS admin (
            id            INTEGER PRIMARY KEY AUTOINCREMENT,
            username      TEXT    NOT NULL UNIQUE,
            password_hash TEXT    NOT NULL,
            role          TEXT    NOT NULL DEFAULT 'admin' CHECK (role IN ('superadmin','admin')),
            created_at    TEXT    NOT NULL DEFAULT CURRENT_TIMESTAMP
        )
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS pengantin (
            jenis  TEXT PRIMARY KEY CHECK (jenis IN ('pria','wanita')),
            nama   TEXT NOT NULL DEFAULT '',
            ibu    TEXT NOT NULL DEFAULT '',
            ayah   TEXT NOT NULL DEFAULT '',
            alamat TEXT NOT NULL DEFAULT '',
            foto   TEXT NOT NULL DEFAULT '',
            foto_lebar  INTEGER NOT NULL DEFAULT 0,
            foto_tinggi INTEGER NOT NULL DEFAULT 0,
            panggilan   TEXT NOT NULL DEFAULT ''
        )
    ");
    $kolom = array_column($pdo->query('PRAGMA table_info(pengantin)')->fetchAll(), 'name');
    foreach (['foto_lebar', 'foto_tinggi'] as $k) {
        if (!in_array($k, $kolom, true)) {
            $pdo->exec("ALTER TABLE pengantin ADD COLUMN $k INTEGER NOT NULL DEFAULT 0");
        }
    }
    if (!in_array('panggilan', $kolom, true)) {
        $pdo->exec("ALTER TABLE pengantin ADD COLUMN panggilan TEXT NOT NULL DEFAULT ''");
        // Isi awal = teks yang sebelumnya tertulis langsung di cover.
        $pdo->exec("UPDATE pengantin SET panggilan = CASE jenis WHEN 'pria' THEN 'Malik' ELSE 'Harsya' END");
    }
    // Isi undangan yang bisa diedit dari dashboard (kunci -> nilai). Bawaan: includes/undangan.php.
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS undangan (
            kunci TEXT PRIMARY KEY,
            nilai TEXT NOT NULL DEFAULT ''
        )
    ");

    $galeri_sudah_ada = (bool) $pdo->query("SELECT 1 FROM sqlite_master WHERE type = 'table' AND name = 'galeri'")->fetchColumn();
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS galeri (
            id         INTEGER PRIMARY KEY AUTOINCREMENT,
            file       TEXT    NOT NULL,
            lebar      INTEGER NOT NULL DEFAULT 0,
            tinggi     INTEGER NOT NULL DEFAULT 0,
            created_at TEXT    NOT NULL DEFAULT CURRENT_TIMESTAMP
        )
    ");
    migrasi_galeri($pdo);

    // Isi awal sama dengan teks yang sebelumnya tertulis langsung di index.php.
    $pdo->exec("
        INSERT OR IGNORE INTO pengantin (jenis, nama, ibu, ayah, alamat, foto) VALUES
        ('pria',   'Malik Pratama, S.T', 'Siti Nurhaliza', 'Hendra Pratama', '', 'media/perkenalan-mempelai-pria.jpeg'),
        ('wanita', 'Harsya Rahma, S.H',  'Dewi Lestari',   'Ahmad Fauzi',    '', 'media/perkenalan-mempelai-wanita.jpeg')
    ");

    // Isi awal galeri = foto yang sebelumnya tertulis langsung di index.php.
    // Hanya saat database baru; kalau admin menghapus semua foto, tidak diisi ulang.
    if (!$galeri_sudah_ada && (int) $pdo->query('SELECT COUNT(*) FROM galeri')->fetchColumn() === 0) {
        $isi = $pdo->prepare('INSERT INTO galeri (file) VALUES (?)');
        foreach (['media/galeri-2.jpeg', 'media/galeri-1.jpeg', 'media/galeri-3.jpeg'] as $f) {
            $isi->execute([$f]);
        }
    }

    isi_ukuran_gambar($pdo);

    // Seed superadmin pertama dari .env, hanya kalau tabel admin masih kosong.
    if ((int) $pdo->query('SELECT COUNT(*) FROM admin')->fetchColumn() === 0) {
        $u = strtolower(trim((string) env('SUPERADMIN_USER', '')));
        $p = (string) env('SUPERADMIN_PASSWORD', '');
        if ($u !== '' && $p !== '') {
            $pdo->prepare('INSERT INTO admin (username, password_hash, role) VALUES (?, ?, ?)')
                ->execute([$u, password_hash($p, PASSWORD_DEFAULT), 'superadmin']);
        }
    }

    return $pdo;
}

/** Database lama punya kolom `jenis` di galeri (pria/wanita) dan belum ada ukuran gambar. */
function migrasi_galeri(PDO $pdo): void
{
    $kolom = array_column($pdo->query('PRAGMA table_info(galeri)')->fetchAll(), 'name');

    if (in_array('jenis', $kolom, true)) {
        $pdo->beginTransaction();
        $pdo->exec('DROP INDEX IF EXISTS idx_galeri_jenis');
        $pdo->exec('ALTER TABLE galeri RENAME TO galeri_lama');
        $pdo->exec("
            CREATE TABLE galeri (
                id         INTEGER PRIMARY KEY AUTOINCREMENT,
                file       TEXT    NOT NULL,
                lebar      INTEGER NOT NULL DEFAULT 0,
                tinggi     INTEGER NOT NULL DEFAULT 0,
                created_at TEXT    NOT NULL DEFAULT CURRENT_TIMESTAMP
            )
        ");
        $pdo->exec('INSERT INTO galeri (id, file, created_at) SELECT id, file, created_at FROM galeri_lama ORDER BY id');
        $pdo->exec('DROP TABLE galeri_lama');
        $pdo->commit();
    }
}

/** Lengkapi lebar/tinggi untuk baris lama (foto bawaan) supaya layout tahu rasionya. */
function isi_ukuran_gambar(PDO $pdo): void
{
    require_once __DIR__ . '/upload.php';

    foreach (['galeri' => ['id', 'file', 'lebar', 'tinggi'], 'pengantin' => ['jenis', 'foto', 'foto_lebar', 'foto_tinggi']] as $tabel => [$pk, $kf, $kl, $kt]) {
        $baris = $pdo->query("SELECT $pk AS pk, $kf AS f FROM $tabel WHERE $kl = 0 OR $kt = 0")->fetchAll();
        if (!$baris) continue;
        $u = $pdo->prepare("UPDATE $tabel SET $kl = ?, $kt = ? WHERE $pk = ?");
        foreach ($baris as $b) {
            if ($b['f'] === '') continue;
            [$w, $h] = ukuran_gambar($b['f']);
            if ($w > 0) $u->execute([$w, $h, $b['pk']]);
        }
    }
}

/** Nama panggilan untuk cover; kalau kosong, pakai kata pertama nama lengkap. */
function nama_panggilan(array $p): string
{
    $n = trim((string) ($p['panggilan'] ?? ''));
    if ($n !== '') return $n;
    return trim(explode(' ', trim(explode(',', (string) ($p['nama'] ?? ''))[0]))[0]);
}

/** @return array{pria: array, wanita: array} */
function ambil_pengantin(): array
{
    $hasil = [];
    foreach (db()->query('SELECT * FROM pengantin')->fetchAll() as $r) {
        $hasil[$r['jenis']] = $r;
    }
    return $hasil;
}
