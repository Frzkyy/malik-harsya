<?php
require_once __DIR__ . '/config.php';

function db(): PDO
{
    static $pdo = null;
    if ($pdo) return $pdo;

    $path = env('DB_PATH', APP_ROOT . '/data/rsvp.db');
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
            foto   TEXT NOT NULL DEFAULT ''
        )
    ");
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS galeri (
            id         INTEGER PRIMARY KEY AUTOINCREMENT,
            jenis      TEXT NOT NULL CHECK (jenis IN ('pria','wanita')),
            file       TEXT NOT NULL,
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
        )
    ");
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_galeri_jenis ON galeri (jenis, id)');

    // Isi awal sama dengan teks yang sebelumnya tertulis langsung di index.php.
    $pdo->exec("
        INSERT OR IGNORE INTO pengantin (jenis, nama, ibu, ayah, alamat, foto) VALUES
        ('pria',   'Malik Pratama, S.T', 'Siti Nurhaliza', 'Hendra Pratama', '', 'media/perkenalan-mempelai-pria.jpeg'),
        ('wanita', 'Harsya Rahma, S.H',  'Dewi Lestari',   'Ahmad Fauzi',    '', 'media/perkenalan-mempelai-wanita.jpeg')
    ");

    // Galeri hanya tampil (tidak bisa diubah dari dashboard); isi awal pakai foto yang sudah ada.
    if ((int) $pdo->query('SELECT COUNT(*) FROM galeri')->fetchColumn() === 0) {
        $pdo->exec("
            INSERT INTO galeri (jenis, file) VALUES
            ('pria',   'media/galeri-2.jpeg'),
            ('pria',   'media/galeri-1.jpeg'),
            ('wanita', 'media/galeri-3.jpeg')
        ");
    }

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

/** @return array{pria: array, wanita: array} */
function ambil_pengantin(): array
{
    $hasil = [];
    foreach (db()->query('SELECT * FROM pengantin')->fetchAll() as $r) {
        $hasil[$r['jenis']] = $r;
    }
    return $hasil;
}
