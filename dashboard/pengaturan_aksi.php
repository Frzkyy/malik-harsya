<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/upload.php';

require_superadmin();

function balik_pengaturan(string $tipe, string $pesan): never
{
    $_SESSION['flash_pengantin'] = ['tipe' => $tipe, 'pesan' => $pesan];
    header('Location: index.php?panel=pengaturan', true, 303);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php?panel=pengaturan', true, 303);
    exit;
}

if (!csrf_valid($_POST['csrf'] ?? null)) {
    balik_pengaturan('error', 'Sesi kedaluwarsa. Silakan coba lagi.');
}

$aksi = (string) ($_POST['aksi'] ?? '');

if ($aksi === 'reset_nama') {
    try {
        // Nilai bawaan sama dengan isi awal di includes/db.php.
        db()->exec("UPDATE pengantin SET
            nama      = CASE jenis WHEN 'pria' THEN 'Malik Pratama, S.T' ELSE 'Harsya Rahma, S.H' END,
            panggilan = CASE jenis WHEN 'pria' THEN 'Malik' ELSE 'Harsya' END,
            ibu       = CASE jenis WHEN 'pria' THEN 'Siti Nurhaliza' ELSE 'Dewi Lestari' END,
            ayah      = CASE jenis WHEN 'pria' THEN 'Hendra Pratama' ELSE 'Ahmad Fauzi' END,
            alamat    = ''
        ");
        balik_pengaturan('ok', 'Nama dan biodata pengantin dikembalikan ke Malik & Harsya.');
    } catch (Throwable $ex) {
        error_log('Reset nama gagal: ' . $ex->getMessage());
        balik_pengaturan('error', 'Reset nama gagal. Periksa log PHP.');
    }
}

if ($aksi !== 'reset_gambar') {
    balik_pengaturan('error', 'Aksi tidak dikenal.');
}

try {
    $pdo = db();
    $pdo->beginTransaction();

    $foto_pengantin = $pdo->query('SELECT foto FROM pengantin')->fetchAll(PDO::FETCH_COLUMN);
    $foto_galeri = $pdo->query('SELECT file FROM galeri')->fetchAll(PDO::FETCH_COLUMN);

    $pdo->exec("UPDATE pengantin SET
        foto = CASE jenis
            WHEN 'pria' THEN 'media/perkenalan-mempelai-pria.jpeg'
            WHEN 'wanita' THEN 'media/perkenalan-mempelai-wanita.jpeg'
        END,
        foto_lebar = 0,
        foto_tinggi = 0
    ");

    $pdo->exec('DELETE FROM galeri');
    $stmt = $pdo->prepare('INSERT INTO galeri (file) VALUES (?)');
    foreach (['media/galeri-2.jpeg', 'media/galeri-1.jpeg', 'media/galeri-3.jpeg'] as $file) {
        $stmt->execute([$file]);
    }

    $pdo->commit();

    foreach (array_merge($foto_pengantin, $foto_galeri) as $file) {
        hapus_unggahan((string) $file);
    }

    balik_pengaturan('ok', 'Semua gambar berhasil dikembalikan ke gambar bawaan.');
} catch (Throwable $ex) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    error_log('Reset gambar gagal: ' . $ex->getMessage());
    balik_pengaturan('error', 'Reset gambar gagal. Periksa log PHP.');
}
