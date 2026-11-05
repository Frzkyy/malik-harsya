<?php
// Handler POST untuk panel Pengantin di dashboard admin (hanya teks).
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';

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

if (!csrf_valid($_POST['csrf'] ?? null)) {
    balik_pengantin('error', 'Sesi kedaluwarsa. Silakan coba lagi.');
}

$jenis = (string) ($_POST['jenis'] ?? '');
if (($_POST['aksi'] ?? '') !== 'simpan' || !in_array($jenis, ['pria', 'wanita'], true)) {
    balik_pengantin('error', 'Data tidak valid.');
}

$panjang = static fn (string $s, int $maks): bool => (bool) preg_match('/^.{0,' . $maks . '}$/su', $s);

$nama   = trim((string) ($_POST['nama'] ?? ''));
$ibu    = trim((string) ($_POST['ibu'] ?? ''));
$ayah   = trim((string) ($_POST['ayah'] ?? ''));
$alamat = trim((string) ($_POST['alamat'] ?? ''));

if ($nama === '' || !$panjang($nama, 100)) {
    balik_pengantin('error', 'Nama wajib diisi (maksimal 100 karakter).', $jenis);
}
if (!$panjang($ibu, 100) || !$panjang($ayah, 100)) {
    balik_pengantin('error', 'Nama ibu/ayah maksimal 100 karakter.', $jenis);
}
if (!$panjang($alamat, 300)) {
    balik_pengantin('error', 'Alamat maksimal 300 karakter.', $jenis);
}

try {
    db()->prepare('UPDATE pengantin SET nama = ?, ibu = ?, ayah = ?, alamat = ? WHERE jenis = ?')
        ->execute([$nama, $ibu, $ayah, $alamat, $jenis]);
} catch (Throwable $ex) {
    error_log('Simpan pengantin gagal: ' . $ex->getMessage());
    balik_pengantin('error', 'Terjadi kesalahan. Coba lagi.', $jenis);
}

balik_pengantin('ok', 'Data pengantin ' . $jenis . ' disimpan.');
