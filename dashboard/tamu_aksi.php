<?php
// Handler POST panel Tamu Undangan: tambah, edit, hapus.
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/tamu.php';

require_admin();

function balik_tamu(string $tipe, string $pesan, string $query = ''): never
{
    $_SESSION['flash_pengantin'] = ['tipe' => $tipe, 'pesan' => $pesan];
    header('Location: ' . app_url('dashboard/index.php?panel=tamu' . $query), true, 303);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    balik_tamu('ok', 'Silakan pilih aksi dari daftar tamu.');
}
if (!csrf_valid($_POST['csrf'] ?? null)) {
    balik_tamu('error', 'Sesi kedaluwarsa. Silakan coba lagi.');
}

$aksi  = (string) ($_POST['aksi'] ?? '');
$id    = (int) ($_POST['id'] ?? 0);
$nama  = trim((string) ($_POST['nama'] ?? ''));
$slug_input = strtolower(trim((string) ($_POST['slug'] ?? '')));

$panjang_ok = static fn (string $s): bool => (bool) preg_match('/^.{1,100}$/su', $s);

try {
    $pdo = db();

    if ($aksi === 'tambah' || $aksi === 'edit') {
        $balik_q = $aksi === 'tambah' ? '&tambah=1' : '&edit=' . $id;

        if (!$panjang_ok($nama)) {
            balik_tamu('error', 'Nama tamu wajib diisi (maksimal 100 karakter).', $balik_q);
        }
        if ($slug_input !== '' && !slug_valid($slug_input)) {
            balik_tamu('error', 'Link hanya boleh huruf kecil, angka, dan tanda - (maksimal 60 karakter).', $balik_q);
        }
        if ($slug_input !== '' && in_array($slug_input, SLUG_DILARANG, true)) {
            balik_tamu('error', 'Link "' . $slug_input . '" tidak boleh dipakai. Pilih yang lain.', $balik_q);
        }
    }

    if ($aksi === 'tambah') {
        if ($slug_input !== '') {
            $s = $pdo->prepare('SELECT 1 FROM tamu WHERE slug = ?');
            $s->execute([$slug_input]);
            if ($s->fetchColumn()) balik_tamu('error', 'Link "' . $slug_input . '" sudah dipakai tamu lain.', '&tambah=1');
            $slug = $slug_input;
        } else {
            $slug = slug_unik($pdo, buat_slug($nama));
        }
        $pdo->prepare('INSERT INTO tamu (nama, slug) VALUES (?, ?)')->execute([$nama, $slug]);
        balik_tamu('ok', 'Tamu "' . $nama . '" ditambahkan.', '&detail=' . (int) $pdo->lastInsertId());
    }

    if ($aksi === 'edit') {
        $s = $pdo->prepare('SELECT slug FROM tamu WHERE id = ?');
        $s->execute([$id]);
        $slug_lama = $s->fetchColumn();
        if ($slug_lama === false) balik_tamu('error', 'Tamu tidak ditemukan.');

        $slug = $slug_lama;
        if ($slug_input !== '' && $slug_input !== $slug_lama) {
            $s = $pdo->prepare('SELECT 1 FROM tamu WHERE slug = ? AND id <> ?');
            $s->execute([$slug_input, $id]);
            if ($s->fetchColumn()) balik_tamu('error', 'Link "' . $slug_input . '" sudah dipakai tamu lain.', '&edit=' . $id);
            $slug = $slug_input;
        }
        $pdo->prepare('UPDATE tamu SET nama = ?, slug = ? WHERE id = ?')->execute([$nama, $slug, $id]);
        $pesan = 'Data tamu disimpan.';
        if ($slug !== $slug_lama) $pesan .= ' Link lama tidak berlaku lagi, kirim ulang link yang baru.';
        balik_tamu('ok', $pesan, '&detail=' . $id);
    }

    if ($aksi === 'hapus') {
        $s = $pdo->prepare('SELECT nama FROM tamu WHERE id = ?');
        $s->execute([$id]);
        $n = $s->fetchColumn();
        if ($n === false) balik_tamu('error', 'Tamu tidak ditemukan.');

        $pdo->beginTransaction();
        // RSVP & ucapan tetap tersimpan, hanya tidak lagi terhubung ke tamu ini.
        $pdo->prepare('UPDATE rsvp SET tamu_id = NULL WHERE tamu_id = ?')->execute([$id]);
        $pdo->prepare('DELETE FROM tamu WHERE id = ?')->execute([$id]);
        $pdo->commit();
        balik_tamu('ok', 'Tamu "' . $n . '" dihapus. RSVP yang sudah masuk tetap tersimpan.');
    }
} catch (Throwable $ex) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    error_log('Aksi tamu gagal: ' . $ex->getMessage());
    balik_tamu('error', 'Terjadi kesalahan. Coba lagi.');
}

balik_tamu('error', 'Aksi tidak dikenal.');
