<?php
// Endpoint masuk tamu (pseudo-login: cukup nama atau link, tanpa password).
// Dipanggil dari gerbang di index.php: fetch -> JSON; tanpa JavaScript -> redirect biasa.
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/tamu.php';

if (isset($_GET['keluar'])) {
    keluar_tamu();
    header('Location: ' . app_url(), true, 303);
    exit;
}

$json = stripos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false;

function selesai_masuk(bool $json, array $data): never
{
    if ($json) {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }
    if (empty($data['ok'])) $_SESSION['masuk_error'] = $data['pesan'] ?? 'Gagal masuk.';
    header('Location: ' . app_url(), true, 303);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . app_url(), true, 303);
    exit;
}

if (!csrf_valid($_POST['csrf'] ?? null)) {
    selesai_masuk($json, ['ok' => false, 'pesan' => 'Sesi kedaluwarsa. Muat ulang halaman lalu coba lagi.']);
}

if (($_POST['aksi'] ?? '') === 'guest') {
    masuk_sebagai_guest();
    selesai_masuk($json, ['ok' => true, 'mode' => 'guest', 'nama' => 'Bapak/Ibu']);
}

$isi = trim((string) ($_POST['nama'] ?? ''));
try {
    $tamu = cari_tamu_login($isi);
} catch (Throwable $ex) {
    error_log('Masuk tamu gagal: ' . $ex->getMessage());
    selesai_masuk($json, ['ok' => false, 'pesan' => 'Terjadi kesalahan. Coba lagi.']);
}

if (!$tamu) {
    selesai_masuk($json, ['ok' => false, 'pesan' => $isi === ''
        ? 'Silakan isi nama Anda.'
        : 'Nama tidak ditemukan. Periksa penulisannya, atau lihat sebagai tamu.']);
}

masuk_sebagai_tamu($tamu);
selesai_masuk($json, ['ok' => true, 'mode' => 'tamu', 'nama' => $tamu['nama'], 'slug' => $tamu['slug']]);
