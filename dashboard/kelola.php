<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';

require_superadmin();

function balik_kelola(string $tipe, string $pesan): never
{
    $_SESSION['flash_kelola'] = ['tipe' => $tipe, 'pesan' => $pesan];
    header('Location: ' . app_url('dashboard/kelola.php'), true, 303);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_valid($_POST['csrf'] ?? null)) {
        balik_kelola('error', 'Sesi kedaluwarsa. Silakan coba lagi.');
    }

    $aksi = (string) ($_POST['aksi'] ?? '');

    try {
        if ($aksi === 'tambah') {
            $u = strtolower(trim((string) ($_POST['username'] ?? '')));
            $p = (string) ($_POST['password'] ?? '');

            if (!preg_match('/^[a-z0-9_]{3,30}$/', $u)) {
                balik_kelola('error', 'Username 3-30 karakter: huruf kecil, angka, atau underscore.');
            }
            if (strlen($p) < 8) {
                balik_kelola('error', 'Password minimal 8 karakter.');
            }

            $cek = db()->prepare('SELECT 1 FROM admin WHERE username = ?');
            $cek->execute([$u]);
            if ($cek->fetchColumn()) {
                balik_kelola('error', 'Username sudah dipakai.');
            }

            db()->prepare('INSERT INTO admin (username, password_hash, role) VALUES (?, ?, ?)')
                ->execute([$u, password_hash($p, PASSWORD_DEFAULT), 'admin']);
            balik_kelola('ok', "Admin \"$u\" ditambahkan.");
        }

        if ($aksi === 'hapus') {
            $id = (int) ($_POST['id'] ?? 0);
            // Hanya role admin yang bisa dihapus; superadmin aman.
            $st = db()->prepare("DELETE FROM admin WHERE id = ? AND role = 'admin'");
            $st->execute([$id]);
            balik_kelola($st->rowCount() ? 'ok' : 'error', $st->rowCount() ? 'Admin dihapus.' : 'Admin tidak bisa dihapus.');
        }

        if ($aksi === 'reset') {
            $id = (int) ($_POST['id'] ?? 0);
            $p  = (string) ($_POST['password'] ?? '');
            if (strlen($p) < 8) {
                balik_kelola('error', 'Password minimal 8 karakter.');
            }
            $st = db()->prepare('UPDATE admin SET password_hash = ? WHERE id = ?');
            $st->execute([password_hash($p, PASSWORD_DEFAULT), $id]);
            balik_kelola($st->rowCount() ? 'ok' : 'error', $st->rowCount() ? 'Password diganti.' : 'Admin tidak ditemukan.');
        }
    } catch (Throwable $ex) {
        error_log('Kelola admin gagal: ' . $ex->getMessage());
        balik_kelola('error', 'Terjadi kesalahan. Coba lagi.');
    }

    balik_kelola('error', 'Aksi tidak dikenal.');
}

$flash = $_SESSION['flash_kelola'] ?? null;
unset($_SESSION['flash_kelola']);

$daftar = db()->query(
    "SELECT id, username, role, created_at FROM admin
     ORDER BY (role = 'superadmin') DESC, username"
)->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@400;500;600;700&family=Great+Vibes&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">

    <title>Kelola Admin</title>
</head>

<body>
    <main id="login" class="lebar">

        <h1>Kelola Admin</h1>

        <p class="login-sub"><a href="index.php?panel=pengaturan">&larr; Kembali</a></p>

        <?php if ($flash): ?>
            <p class="pesan-login <?= $flash['tipe'] === 'ok' ? 'pesan-sukses' : '' ?>" role="status"><?= e($flash['pesan']) ?></p>
        <?php endif; ?>

        <ul class="daftar-admin">
            <?php foreach ($daftar as $a): ?>
                <li class="admin-baris">
                    <div class="admin-info">
                        <strong><?= e($a['username']) ?></strong>
                        <span class="badge-role"><?= e($a['role']) ?></span>
                    </div>

                    <form action="kelola.php" method="post" class="admin-aksi">
                        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                        <input type="hidden" name="aksi" value="reset">
                        <input type="hidden" name="id" value="<?= (int) $a['id'] ?>">
                        <input type="password" name="password" placeholder="Password baru" autocomplete="new-password" minlength="8" required>
                        <button type="submit">Ganti</button>
                    </form>

                    <?php if ($a['role'] === 'admin'): ?>
                        <form action="kelola.php" method="post" class="admin-aksi" onsubmit="return confirm('Hapus admin <?= e($a['username']) ?>?');">
                            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                            <input type="hidden" name="aksi" value="hapus">
                            <input type="hidden" name="id" value="<?= (int) $a['id'] ?>">
                            <button type="submit" class="btn-bahaya">Hapus</button>
                        </form>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>

        <h2>Tambah admin</h2>

        <form action="kelola.php" method="post">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="aksi" value="tambah">

            <div class="field">
                <label for="baru-username">Username</label>
                <input type="text" id="baru-username" name="username" autocapitalize="none" maxlength="30" required>
            </div>

            <div class="field">
                <label for="baru-password">Password (min. 8 karakter)</label>
                <input type="password" id="baru-password" name="password" autocomplete="new-password" minlength="8" required>
            </div>

            <button type="submit">Tambah</button>
        </form>

    </main>
</body>
</html>
