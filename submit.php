<?php
require_once __DIR__ . '/includes/db.php';

function balik(string $tipe, string $pesan, array $old = []): never
{
    $_SESSION['flash'] = ['tipe' => $tipe, 'pesan' => $pesan, 'old' => $old];
    header('Location: ./#rsvp', true, 303);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ./#rsvp', true, 303);
    exit;
}

// Honeypot: field ini disembunyikan, manusia tidak mengisinya.
if (!empty($_POST['website'])) {
    balik('ok', 'Terima kasih, RSVP Anda sudah kami terima.');
}

if (!csrf_valid($_POST['csrf'] ?? null)) {
    balik('error', 'Sesi kedaluwarsa. Silakan coba kirim lagi.');
}

$nama   = trim((string) ($_POST['nama'] ?? ''));
$status = (string) ($_POST['status'] ?? '');
$jumlah = filter_var($_POST['jumlah'] ?? 1, FILTER_VALIDATE_INT);
$ucapan = trim((string) ($_POST['ucapan'] ?? ''));

$old = ['nama' => $nama, 'status' => $status, 'jumlah' => $jumlah, 'ucapan' => $ucapan];

// Regex /u sekaligus memastikan string valid UTF-8 (tanpa perlu mbstring).
if (!preg_match('/^.{1,100}$/su', $nama)) {
    balik('error', 'Nama wajib diisi (maksimal 100 karakter).', $old);
}
if (!in_array($status, ['hadir', 'ragu', 'tidak'], true)) {
    balik('error', 'Pilih salah satu status kehadiran.', $old);
}
if ($jumlah === false || $jumlah < 1 || $jumlah > 5) {
    balik('error', 'Jumlah tamu harus antara 1 sampai 5.', $old);
}
if ($ucapan !== '' && !preg_match('/^.{1,500}$/su', $ucapan)) {
    balik('error', 'Ucapan maksimal 500 karakter.', $old);
}

if ($status === 'tidak') {
    $jumlah = 0;
}

try {
    $stmt = db()->prepare(
        'INSERT INTO rsvp (nama, status, jumlah, ucapan) VALUES (?, ?, ?, ?)'
    );
    $stmt->execute([$nama, $status, $jumlah, $ucapan !== '' ? $ucapan : null]);
} catch (Throwable $ex) {
    error_log('RSVP gagal disimpan: ' . $ex->getMessage());
    balik('error', 'Maaf, RSVP belum tersimpan. Silakan coba lagi sebentar lagi.', $old);
}

// Token sekali pakai: ganti setelah sukses.
unset($_SESSION['csrf']);

balik('ok', 'Terima kasih, RSVP Anda sudah kami terima.');
