<?php
// Handler POST galeri: tambah (boleh banyak file sekaligus) dan hapus foto.
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/upload.php';

require_admin();

const GALERI_MAKS_FOTO = 60;   // batas jumlah foto di galeri
const GALERI_MAKS_SEKALI = 20; // batas file per sekali unggah

function balik_galeri(string $tipe, string $pesan): never
{
    $_SESSION['flash_pengantin'] = ['tipe' => $tipe, 'pesan' => $pesan];
    header('Location: index.php?panel=pengantin#galeri', true, 303);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php?panel=pengantin#galeri', true, 303);
    exit;
}

// Kalau total body melebihi post_max_size, PHP mengosongkan $_POST & $_FILES.
if (empty($_POST) && empty($_FILES) && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
    balik_galeri('error', 'Total ukuran file terlalu besar. Unggah lebih sedikit foto sekaligus.');
}

if (!csrf_valid($_POST['csrf'] ?? null)) {
    balik_galeri('error', 'Sesi kedaluwarsa. Silakan coba lagi.');
}

$aksi = (string) ($_POST['aksi'] ?? '');

try {
    if ($aksi === 'tambah') {
        $files = daftar_file('foto');
        if (!$files) balik_galeri('error', 'Pilih minimal satu foto.');
        if (count($files) > GALERI_MAKS_SEKALI) {
            balik_galeri('error', 'Maksimal ' . GALERI_MAKS_SEKALI . ' foto sekali unggah.');
        }

        $jumlah = (int) db()->query('SELECT COUNT(*) FROM galeri')->fetchColumn();
        if ($jumlah + count($files) > GALERI_MAKS_FOTO) {
            balik_galeri('error', 'Galeri maksimal ' . GALERI_MAKS_FOTO . ' foto. Hapus beberapa dulu.');
        }

        $ins = db()->prepare('INSERT INTO galeri (file, lebar, tinggi) VALUES (?, ?, ?)');
        $berhasil = 0;
        $gagal = [];
        foreach ($files as $f) {
            try {
                $g = simpan_gambar($f);
                $ins->execute([$g['file'], $g['lebar'], $g['tinggi']]);
                $berhasil++;
            } catch (RuntimeException $ex) {
                $gagal[] = ($f['name'] ?? 'file') . ': ' . $ex->getMessage();
            }
        }

        if ($berhasil === 0) balik_galeri('error', implode(' | ', $gagal));
        $pesan = $berhasil . ' foto ditambahkan.';
        if ($gagal) $pesan .= ' Gagal: ' . implode(' | ', $gagal);
        balik_galeri($gagal ? 'error' : 'ok', $pesan);
    }

    if ($aksi === 'hapus') {
        $id = (int) ($_POST['id'] ?? 0);
        $s = db()->prepare('SELECT file FROM galeri WHERE id = ?');
        $s->execute([$id]);
        $file = $s->fetchColumn();
        if ($file === false) balik_galeri('error', 'Foto tidak ditemukan.');

        db()->prepare('DELETE FROM galeri WHERE id = ?')->execute([$id]);
        hapus_unggahan((string) $file);
        balik_galeri('ok', 'Foto dihapus dari galeri.');
    }
} catch (Throwable $ex) {
    error_log('Aksi galeri gagal: ' . $ex->getMessage());
    balik_galeri('error', 'Terjadi kesalahan. Coba lagi.');
}

balik_galeri('error', 'Data tidak valid.');
