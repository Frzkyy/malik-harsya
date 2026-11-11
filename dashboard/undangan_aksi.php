<?php
// Handler POST editor Undangan (tab Undangan di dashboard): simpan teks, tanggal, jam, gambar,
// alamat, galeri (foto + tata letak), dan daftar cerita -- semuanya dalam SATU transaksi.
// Dipanggil lewat fetch (balasan JSON) dari assets/js/editor-undangan.js;
// tanpa JavaScript tetap jalan sebagai form biasa untuk bagian teks (galeri & cerita butuh JavaScript).
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/upload.php';
require_once __DIR__ . '/../includes/undangan.php';
require_once __DIR__ . '/../includes/galeri.php';
require_once __DIR__ . '/../includes/cerita.php';

$json = stripos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false;

function selesai_undangan(bool $json, bool $ok, string $pesan, array $tambahan = [], int $kode = 200): never
{
    if ($json) {
        http_response_code($kode);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        echo json_encode(['ok' => $ok, 'pesan' => $pesan] + $tambahan, JSON_UNESCAPED_UNICODE);
        exit;
    }
    $_SESSION['flash_pengantin'] = ['tipe' => $ok ? 'ok' : 'error', 'pesan' => $pesan];
    header('Location: ' . app_url('dashboard/index.php?panel=pengantin'), true, 303);
    exit;
}

/**
 * $_FILES['nama'][kunci] (field berbentuk nama[kunci]) -> [kunci => entri file ala $_FILES], hanya yang ada isinya.
 * @return array<string, array>
 */
function file_per_kunci(string $nama): array
{
    $hasil = [];
    if (!isset($_FILES[$nama]) || !is_array($_FILES[$nama]['name'] ?? null)) return $hasil;
    foreach ($_FILES[$nama]['name'] as $k => $_) {
        if (($_FILES[$nama]['error'][$k] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) continue;
        $hasil[(string) $k] = [
            'name'     => $_FILES[$nama]['name'][$k],
            'type'     => $_FILES[$nama]['type'][$k] ?? '',
            'tmp_name' => $_FILES[$nama]['tmp_name'][$k] ?? '',
            'error'    => $_FILES[$nama]['error'][$k],
            'size'     => $_FILES[$nama]['size'][$k] ?? 0,
        ];
    }
    return $hasil;
}

// Sesi admin berakhir: JSON dapat 401 (bukan redirect ke halaman login), form biasa dialihkan.
if ($json && (!is_admin() || !admin_masih_ada())) {
    selesai_undangan(true, false, 'Sesi berakhir. Muat ulang halaman lalu masuk lagi.', [], 401);
}
require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . app_url('dashboard/index.php?panel=pengantin'), true, 303);
    exit;
}

// Body melebihi post_max_size -> $_POST & $_FILES dikosongkan PHP.
if (empty($_POST) && empty($_FILES) && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
    selesai_undangan($json, false, 'Total ukuran gambar terlalu besar. Simpan beberapa gambar sekali saja (maksimal 12 MB per gambar).', [], 413);
}

if (!csrf_valid($_POST['csrf'] ?? null)) {
    selesai_undangan($json, false, 'Sesi kedaluwarsa. Muat ulang halaman lalu coba lagi.', [], 403);
}

// ---------------------------------------------------------------- validasi (belum ada yang disimpan)

[$teks, $mempelai, $galat] = validasi_undangan($_POST);
if ($galat !== null) {
    selesai_undangan($json, false, $galat, [], 422);
}

try {
    $pdo = db();
    $id_galeri  = array_map('intval', $pdo->query('SELECT id FROM galeri')->fetchAll(PDO::FETCH_COLUMN));
    $id_cerita  = array_map('intval', $pdo->query('SELECT id FROM cerita')->fetchAll(PDO::FETCH_COLUMN));
} catch (Throwable $ex) {
    error_log('Simpan undangan: gagal membaca database: ' . $ex->getMessage());
    selesai_undangan($json, false, 'Terjadi kesalahan saat membaca data. Coba lagi.', [], 500);
}

// Galeri & cerita hanya diproses kalau dikirim (form tanpa JavaScript tidak mengirimnya).
$file_galeri = file_per_kunci('galeri_baru');
$file_cerita = file_per_kunci('cerita_foto');

$tata = null;
if (isset($_POST['galeri_tata'])) {
    [$tata, $galat] = validasi_tata_galeri((string) $_POST['galeri_tata'], $id_galeri, array_keys($file_galeri));
    if ($galat !== null) selesai_undangan($json, false, $galat, [], 422);
}

$daftar_cerita = null;
if (isset($_POST['cerita'])) {
    [$daftar_cerita, $galat] = validasi_cerita($_POST['cerita'], $id_cerita);
    if ($galat !== null) selesai_undangan($json, false, $galat, [], 422);
}

// ---------------------------------------------------------------- unggah gambar

// Gambar bagian teks (opsional): kolom file kosong = gambar lama dipertahankan.
// 'kolom file' => [label]
$kolom_foto = [
    'cover_foto'   => 'Foto cover',
    'pria_foto'    => 'Foto mempelai pria',
    'wanita_foto'  => 'Foto mempelai wanita',
    'akad_foto'    => 'Foto akad nikah',
    'resepsi_foto' => 'Foto resepsi',
    'alamat_foto'  => 'Foto lokasi',
];

$baru = [];          // kolom file => hasil simpan_gambar
$baru_galeri = [];   // kunci n* => hasil simpan_gambar
$baru_cerita = [];   // kunci cerita => hasil simpan_gambar

$bersihkan = static function () use (&$baru, &$baru_galeri, &$baru_cerita): void {
    foreach ([$baru, $baru_galeri, $baru_cerita] as $kelompok) {
        foreach ($kelompok as $b) hapus_unggahan($b['file']);
    }
};

$tugas = [];   // [tujuan, kunci, entri file, label]
foreach ($kolom_foto as $kolom => $label) {
    if (isset($_FILES[$kolom]) && ($_FILES[$kolom]['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        $tugas[] = ['baru', $kolom, $_FILES[$kolom], $label];
    }
}
if ($tata !== null) {
    $no = 0;
    foreach ($tata as $t) {
        $no++;
        if ($t['k'][0] === 'n') $tugas[] = ['galeri', $t['k'], $file_galeri[$t['k']], "Foto galeri ke-$no"];
    }
}
if ($daftar_cerita !== null) {
    $no = 0;
    foreach ($daftar_cerita as $c) {
        $no++;
        if (isset($file_cerita[$c['k']])) $tugas[] = ['cerita', $c['k'], $file_cerita[$c['k']], "Foto cerita ke-$no"];
    }
}

foreach ($tugas as [$tujuan, $kunci, $berkas, $label]) {
    try {
        $hasil = simpan_gambar($berkas);
    } catch (Throwable $ex) {
        $bersihkan();
        $pesan = $ex instanceof RuntimeException ? $ex->getMessage() : 'Gagal memproses gambar.';
        if (!($ex instanceof RuntimeException)) error_log('Simpan undangan: ' . $ex->getMessage());
        selesai_undangan($json, false, $label . ': ' . $pesan, [], 422);
    }
    if ($tujuan === 'baru') $baru[$kunci] = $hasil;
    elseif ($tujuan === 'galeri') $baru_galeri[$kunci] = $hasil;
    else $baru_cerita[$kunci] = $hasil;
}

// ---------------------------------------------------------------- simpan (satu transaksi)

$lama = [];            // path gambar lama yang dihapus setelah transaksi berhasil
$peta_galeri = [];     // kunci n* -> ['k' => 'g<id>', 'file' => ...]
$peta_cerita = [];     // kunci n* -> ['k' => 'c<id>', 'foto' => ...]
$ganti_cerita = [];    // kunci c* -> path foto baru (foto cerita lama diganti)

try {
    $undangan_lama = ambil_undangan();
    $pdo->beginTransaction();

    // --- tabel undangan
    $nilai = $teks;
    foreach (UNDANGAN_KUNCI_FOTO as $k) {
        if (isset($baru[$k])) {
            $nilai[$k] = $baru[$k]['file'];
            $lama[] = $undangan_lama[$k];
        }
    }
    simpan_undangan($pdo, $nilai);

    // --- tabel pengantin
    $baca_foto = $pdo->prepare('SELECT foto FROM pengantin WHERE jenis = ?');
    $ubah = $pdo->prepare('UPDATE pengantin SET nama = ?, panggilan = ?, ibu = ?, ayah = ? WHERE jenis = ?');
    $ubah_foto = $pdo->prepare('UPDATE pengantin SET foto = ?, foto_lebar = ?, foto_tinggi = ? WHERE jenis = ?');

    foreach ($mempelai as $jenis => $m) {
        $ubah->execute([$m['nama'], $m['panggilan'], $m['ibu'], $m['ayah'], $jenis]);
        if (isset($baru["{$jenis}_foto"])) {
            $f = $baru["{$jenis}_foto"];
            $baca_foto->execute([$jenis]);
            $lama[] = (string) $baca_foto->fetchColumn();
            $ubah_foto->execute([$f['file'], $f['lebar'], $f['tinggi'], $jenis]);
        }
    }

    // --- galeri: foto + tata letak
    if ($tata !== null) {
        $ada = $pdo->query('SELECT id, file FROM galeri')->fetchAll(PDO::FETCH_KEY_PAIR);
        $ubah_g = $pdo->prepare('UPDATE galeri SET x = ?, y = ?, w = ?, h = ? WHERE id = ?');
        $tambah_g = $pdo->prepare('INSERT INTO galeri (file, lebar, tinggi, x, y, w, h) VALUES (?, ?, ?, ?, ?, ?, ?)');
        $tetap = [];

        foreach ($tata as $t) {
            if ($t['k'][0] === 'g') {
                $id = (int) substr($t['k'], 1);
                $ubah_g->execute([$t['x'], $t['y'], $t['w'], $t['h'], $id]);
                $tetap[$id] = true;
            } else {
                $f = $baru_galeri[$t['k']];
                $tambah_g->execute([$f['file'], $f['lebar'], $f['tinggi'], $t['x'], $t['y'], $t['w'], $t['h']]);
                $peta_galeri[$t['k']] = ['k' => 'g' . $pdo->lastInsertId(), 'file' => $f['file']];
            }
        }
        $hapus_g = $pdo->prepare('DELETE FROM galeri WHERE id = ?');
        foreach ($ada as $id => $file) {
            if (isset($tetap[(int) $id])) continue;
            $hapus_g->execute([$id]);
            $lama[] = (string) $file;
        }
    }

    // --- cerita kami
    if ($daftar_cerita !== null) {
        $ada = $pdo->query('SELECT id, foto FROM cerita')->fetchAll(PDO::FETCH_KEY_PAIR);
        $ubah_c = $pdo->prepare('UPDATE cerita SET urutan = ?, judul = ?, deskripsi = ? WHERE id = ?');
        $ubah_cf = $pdo->prepare('UPDATE cerita SET foto = ? WHERE id = ?');
        $tambah_c = $pdo->prepare('INSERT INTO cerita (urutan, judul, deskripsi, foto) VALUES (?, ?, ?, ?)');
        $tetap = [];

        foreach ($daftar_cerita as $i => $c) {
            $urutan = $i + 1;
            if ($c['k'][0] === 'c') {
                $id = (int) substr($c['k'], 1);
                $ubah_c->execute([$urutan, $c['judul'], $c['deskripsi'], $id]);
                if (isset($baru_cerita[$c['k']])) {
                    $ubah_cf->execute([$baru_cerita[$c['k']]['file'], $id]);
                    $lama[] = (string) $ada[$id];
                    $ganti_cerita[$c['k']] = $baru_cerita[$c['k']]['file'];
                }
                $tetap[$id] = true;
            } else {
                // Cerita baru tanpa foto: pakai foto cadangan bawaan (bergantian), admin tinggal menggantinya.
                $foto = isset($baru_cerita[$c['k']])
                    ? $baru_cerita[$c['k']]['file']
                    : CERITA_FOTO_CADANGAN[$i % count(CERITA_FOTO_CADANGAN)];
                $tambah_c->execute([$urutan, $c['judul'], $c['deskripsi'], $foto]);
                $peta_cerita[$c['k']] = ['k' => 'c' . $pdo->lastInsertId(), 'foto' => $foto];
            }
        }
        $hapus_c = $pdo->prepare('DELETE FROM cerita WHERE id = ?');
        foreach ($ada as $id => $foto) {
            if (isset($tetap[(int) $id])) continue;
            $hapus_c->execute([$id]);
            $lama[] = (string) $foto;
        }
    }

    $pdo->commit();
} catch (Throwable $ex) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    $bersihkan();
    error_log('Simpan undangan gagal: ' . $ex->getMessage());
    selesai_undangan($json, false, 'Terjadi kesalahan saat menyimpan. Coba lagi.', [], 500);
}

// Berhasil: buang file lama (hanya yang hasil unggahan; gambar bawaan di media/ tidak disentuh).
foreach ($lama as $f) hapus_unggahan((string) $f);

$gambar = [];
foreach ($baru as $kolom => $b) $gambar[$kolom] = $b['file'];

selesai_undangan($json, true, 'Perubahan disimpan.', [
    'gambar'       => $gambar,         // kolom file -> path baru
    'galeri_baru'  => $peta_galeri,    // kunci sementara -> kunci tetap + path
    'cerita_baru'  => $peta_cerita,
    'cerita_foto'  => $ganti_cerita,   // kunci cerita lama -> path foto baru
]);
