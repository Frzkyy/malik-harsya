<?php
// Handler POST editor Undangan (tab Undangan di dashboard): simpan teks, tanggal, jam, dan gambar.
// Dipanggil lewat fetch (balasan JSON) dari assets/js/editor-undangan.js;
// tanpa JavaScript tetap jalan sebagai form biasa (redirect + pesan).
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/upload.php';
require_once __DIR__ . '/../includes/undangan.php';

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
    header('Location: index.php?panel=pengantin', true, 303);
    exit;
}

// Sesi admin berakhir: JSON dapat 401 (bukan redirect ke halaman login), form biasa dialihkan.
if ($json && (!is_admin() || !admin_masih_ada())) {
    selesai_undangan(true, false, 'Sesi berakhir. Muat ulang halaman lalu masuk lagi.', [], 401);
}
require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php?panel=pengantin', true, 303);
    exit;
}

// Body melebihi post_max_size -> $_POST & $_FILES dikosongkan PHP.
if (empty($_POST) && empty($_FILES) && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
    selesai_undangan($json, false, 'Total ukuran gambar terlalu besar. Simpan beberapa gambar sekali saja (maksimal 12 MB per gambar).', [], 413);
}

if (!csrf_valid($_POST['csrf'] ?? null)) {
    selesai_undangan($json, false, 'Sesi kedaluwarsa. Muat ulang halaman lalu coba lagi.', [], 403);
}

[$teks, $mempelai, $galat] = validasi_undangan($_POST);
if ($galat !== null) {
    selesai_undangan($json, false, $galat, [], 422);
}

// Gambar opsional: kolom file kosong = gambar lama dipertahankan.
// 'kolom file' => [jenis, label]
$kolom_foto = [
    'cover_foto'   => ['undangan', 'Foto cover'],
    'pria_foto'    => ['pria',     'Foto mempelai pria'],
    'wanita_foto'  => ['wanita',   'Foto mempelai wanita'],
    'akad_foto'    => ['undangan', 'Foto akad nikah'],
    'resepsi_foto' => ['undangan', 'Foto resepsi'],
];

$baru = [];   // kolom file => ['file','lebar','tinggi']
foreach ($kolom_foto as $kolom => [$_, $label]) {
    $ada = isset($_FILES[$kolom]) && ($_FILES[$kolom]['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
    if (!$ada) continue;
    try {
        $baru[$kolom] = simpan_gambar($_FILES[$kolom]);
    } catch (RuntimeException $ex) {
        foreach ($baru as $b) hapus_unggahan($b['file']);   // batalkan yang sudah terlanjur tersimpan
        selesai_undangan($json, false, $label . ': ' . $ex->getMessage(), [], 422);
    }
}

$lama = [];   // path gambar lama yang dihapus setelah transaksi berhasil
try {
    $pdo = db();
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

    $pdo->commit();
} catch (Throwable $ex) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    foreach ($baru as $b) hapus_unggahan($b['file']);
    error_log('Simpan undangan gagal: ' . $ex->getMessage());
    selesai_undangan($json, false, 'Terjadi kesalahan saat menyimpan. Coba lagi.', [], 500);
}

// Berhasil: buang file lama (hanya yang hasil unggahan; gambar bawaan di media/ tidak disentuh).
foreach ($lama as $f) hapus_unggahan((string) $f);

$gambar = [];
foreach ($baru as $kolom => $b) $gambar[$kolom] = $b['file'];

selesai_undangan($json, true, 'Perubahan disimpan.', ['gambar' => $gambar]);
