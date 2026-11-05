<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

require_admin();

$stat = ['total' => 0, 'hadir' => 0, 'tamu' => 0, 'ucapan' => 0];
try {
    $r = db()->query(
        "SELECT COUNT(*) AS total,
                COALESCE(SUM(status = 'hadir'), 0) AS hadir,
                COALESCE(SUM(jumlah), 0) AS tamu,
                COALESCE(SUM(ucapan IS NOT NULL AND ucapan <> ''), 0) AS ucapan
         FROM rsvp"
    )->fetch();
    $stat = array_map('intval', $r);
} catch (Throwable $ex) {
    error_log('Admin gagal baca statistik: ' . $ex->getMessage());
}
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
    <link rel="stylesheet" href="style.css">

    <title>Admin</title>
</head>

<body>
    <main id="login">

        <h1>Admin</h1>

        <p class="login-sub">
            Halo, <?= e($_SESSION['admin_user']) ?>
            <span class="badge-role"><?= is_superadmin() ? 'superadmin' : 'admin' ?></span>
        </p>

        <ul class="admin-stat">
            <li><strong><?= $stat['total'] ?></strong> RSVP masuk</li>
            <li><strong><?= $stat['hadir'] ?></strong> konfirmasi hadir</li>
            <li><strong><?= $stat['tamu'] ?></strong> total tamu</li>
            <li><strong><?= $stat['ucapan'] ?></strong> ucapan</li>
        </ul>

        <?php if (is_superadmin()): ?>
            <a class="btn" href="kelola.php">Kelola admin</a>
        <?php endif; ?>

        <form action="logout.php" method="post">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <button type="submit">Keluar</button>
        </form>

    </main>
</body>
</html>
