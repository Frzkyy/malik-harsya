<?php
// Handler POST untuk panel Pengantin di dashboard admin (teks + ganti foto).
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/upload.php';

require_admin();

function balik_pengantin(string $tipe, string $pesan, string $edit = ''): never
{
    $_SESSION['flash_pengantin'] = ['tipe' => $tipe, 'pesan' => $pesan];
    $q = $edit !== '' ? '&edit=' . $edit : '';
    header('Location: index.php?panel=pengantin' . $q, true, 303);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php?panel=pengantin', true, 303);
    exit;
}

// Body melebihi post_max_size -> $_POST & $_FILES dikosongkan PHP.
if (empty($_POST) && empty($_FILES) && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
    balik_pengantin('error', 'Ukuran file terlalu besar (maksimal 12 MB).');
}

if (!csrf_valid($_POST['csrf'] ?? null)) {
    balik_pengantin('error', 'Sesi kedaluwarsa. Silakan coba lagi.');
}

$jenis = (string) ($_POST['jenis'] ?? '');
if (($_POST['aksi'] ?? '') !== 'simpan' || !in_array($jenis, ['pria', 'wanita'], true)) {
    balik_pengantin('error', 'Data tidak valid.');
}

$panjang = static fn (string $s, int $maks): bool => (bool) preg_match('/^.{0,' . $maks . '}$/su', $s);

$nama   = trim((string) ($_POST['nama'] ?? ''));
$panggilan = trim((string) ($_POST['panggilan'] ?? ''));
$ibu    = trim((string) ($_POST['ibu'] ?? ''));
$ayah   = trim((string) ($_POST['ayah'] ?? ''));
$alamat = trim((string) ($_POST['alamat'] ?? ''));

if ($nama === '' || !$panjang($nama, 100)) {
    balik_pengantin('error', 'Nama wajib diisi (maksimal 100 karakter).', $jenis);
}
if ($panggilan === '' || !$panjang($panggilan, 30)) {
    balik_pengantin('error', 'Nama panggilan wajib diisi (maksimal 30 karakter).', $jenis);
}
if (!$panjang($ibu, 100) || !$panjang($ayah, 100)) {
    balik_pengantin('error', 'Nama ibu/ayah maksimal 100 karakter.', $jenis);
}
if (!$panjang($alamat, 300)) {
    balik_pengantin('error', 'Alamat maksimal 300 karakter.', $jenis);
}

// Foto opsional: kosong = foto lama dipertahankan.
$foto_baru = null;
$ada_foto  = isset($_FILES['foto']) && ($_FILES['foto']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
if ($ada_foto) {
    try {
        $foto_baru = simpan_gambar($_FILES['foto']);
    } catch (RuntimeException $ex) {
        balik_pengantin('error', 'Foto: ' . $ex->getMessage(), $jenis);
    }
}

try {
    $pdo = db();
    $lama = (string) $pdo->query('SELECT foto FROM pengantin WHERE jenis = ' . $pdo->quote($jenis))->fetchColumn();

    if ($foto_baru) {
        $pdo->prepare('UPDATE pengantin SET nama = ?, panggilan = ?, ibu = ?, ayah = ?, alamat = ?, foto = ?, foto_lebar = ?, foto_tinggi = ? WHERE jenis = ?')
            ->execute([$nama, $panggilan, $ibu, $ayah, $alamat, $foto_baru['file'], $foto_baru['lebar'], $foto_baru['tinggi'], $jenis]);
        hapus_unggahan($lama);
    } else {
        $pdo->prepare('UPDATE pengantin SET nama = ?, panggilan = ?, ibu = ?, ayah = ?, alamat = ? WHERE jenis = ?')
            ->execute([$nama, $panggilan, $ibu, $ayah, $alamat, $jenis]);
    }
} catch (Throwable $ex) {
    if ($foto_baru) hapus_unggahan($foto_baru['file']);
    error_log('Simpan pengantin gagal: ' . $ex->getMessage());
    balik_pengantin('error', 'Terjadi kesalahan. Coba lagi.', $jenis);
}

balik_pengantin('ok', 'Data pengantin ' . $jenis . ' disimpan.');
