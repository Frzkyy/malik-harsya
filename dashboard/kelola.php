<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/tamu.php';   // waktu_wib()

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

    <link rel="stylesheet" href="../assets/css/dashboard.css?v=<?= (int) @filemtime(__DIR__ . '/../assets/css/dashboard.css') ?>">
    <link rel="stylesheet" href="../assets/css/kelola.css?v=<?= (int) @filemtime(__DIR__ . '/../assets/css/kelola.css') ?>">

    <title>Kelola Admin · Dashboard</title>
</head>

<body class="dash">

    <aside class="sisi">
        <nav aria-label="Menu dashboard">
            <a href="index.php?panel=pengantin">Undangan</a>
            <a href="index.php?panel=tamu">Tamu Undangan</a>
            <a href="index.php?panel=hadir">Daftar Hadir</a>
            <a href="index.php?panel=pengaturan" class="aktif" aria-current="page">Pengaturan</a>
            <a class="kembali" href="../">&larr; Kembali ke undangan</a>
        </nav>

        <div class="akun">
            <span><?= e($_SESSION['admin_user']) ?> <em class="role">superadmin</em></span>
            <form action="../logout.php" method="post">
                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                <button type="submit" class="tombol-teks">Keluar</button>
            </form>
        </div>
    </aside>

    <main class="isi">

        <a class="kelola-kembali" href="index.php?panel=pengaturan">&larr; Kembali ke Pengaturan</a>
        <h1>Kelola Admin</h1>

        <?php if ($flash): ?>
            <p class="pesan <?= $flash['tipe'] === 'ok' ? 'ok' : 'galat' ?>" role="status"><?= e($flash['pesan']) ?></p>
        <?php endif; ?>

        <div class="kelola-grid">

            <section class="kartu kelola-daftar" aria-labelledby="h-daftar">
                <div class="kepala">
                    <h2 id="h-daftar">Daftar admin</h2>
                    <span class="kelola-jumlah"><?= count($daftar) ?> akun</span>
                </div>

                <ul class="kelola-list">
                    <?php foreach ($daftar as $a):
                        $super = $a['role'] === 'superadmin';
                        $saya  = (int) $a['id'] === (int) ($_SESSION['admin_id'] ?? 0);
                    ?>
                        <li class="kelola-baris">
                            <div class="kelola-atas">
                                <span class="kelola-avatar<?= $super ? ' super' : '' ?>" aria-hidden="true"><?= e(strtoupper(substr($a['username'], 0, 1))) ?></span>
                                <div class="kelola-nama">
                                    <strong><?= e($a['username']) ?><?= $saya ? ' <small>(Anda)</small>' : '' ?></strong>
                                    <span>Dibuat <?= e(waktu_wib($a['created_at'])) ?></span>
                                </div>
                                <span class="kelola-role<?= $super ? ' super' : '' ?>"><?= e($a['role']) ?></span>
                            </div>

                            <div class="kelola-aksi">
                                <details class="kelola-ganti">
                                    <summary>Ganti password</summary>
                                    <form action="kelola.php" method="post">
                                        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                                        <input type="hidden" name="aksi" value="reset">
                                        <input type="hidden" name="id" value="<?= (int) $a['id'] ?>">
                                        <input type="password" name="password" placeholder="Password baru (min. 8 karakter)" autocomplete="new-password" minlength="8" required aria-label="Password baru untuk <?= e($a['username']) ?>">
                                        <button type="submit" class="tombol utama">Simpan</button>
                                    </form>
                                </details>

                                <?php if (!$super): ?>
                                    <form action="kelola.php" method="post" onsubmit="return confirm('Hapus admin <?= e($a['username']) ?>?');">
                                        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                                        <input type="hidden" name="aksi" value="hapus">
                                        <input type="hidden" name="id" value="<?= (int) $a['id'] ?>">
                                        <button type="submit" class="kelola-hapus">Hapus</button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </section>

            <section class="kartu kelola-tambah" aria-labelledby="h-tambah">
                <h2 id="h-tambah">Tambah admin</h2>
                <p class="kelola-bantu">Admin baru bisa mengelola undangan dan tamu, tapi tidak bisa mengatur akun admin.</p>

                <form action="kelola.php" method="post">
                    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="aksi" value="tambah">

                    <label for="baru-username">Username
                        <small>3-30 karakter: huruf kecil, angka, underscore</small>
                    </label>
                    <input type="text" id="baru-username" name="username" autocapitalize="none" maxlength="30" pattern="[a-z0-9_]{3,30}" required>

                    <label for="baru-password">Password
                        <small>minimal 8 karakter</small>
                    </label>
                    <input type="password" id="baru-password" name="password" autocomplete="new-password" minlength="8" required>

                    <button type="submit" class="tombol utama kelola-submit">Tambah admin</button>
                </form>
            </section>

        </div>

    </main>
</body>
</html>
