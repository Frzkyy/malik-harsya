<?php
// Dashboard admin: layout + sidebar. Isi tiap panel ada di dashboard/panel/<nama>.php.
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';

require_admin();

$panel_list = [
    'pengantin'  => 'Undangan',
    'tamu'       => 'Tamu Undangan',
    'hadir'      => 'Daftar Hadir',
    'pengaturan' => 'Pengaturan',
];
$panel = (string) ($_GET['panel'] ?? 'pengantin');
if (!isset($panel_list[$panel])) $panel = 'pengantin';

$flash = $_SESSION['flash_pengantin'] ?? null;
unset($_SESSION['flash_pengantin']);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">

    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <?php if ($panel === 'pengantin'): ?>
        <link rel="stylesheet" href="../assets/css/editor-undangan.css">
    <?php endif; ?>

    <title><?= e($panel_list[$panel]) ?> · Dashboard</title>
</head>

<body class="dash">

    <aside class="sisi">
        <nav aria-label="Menu dashboard">
            <?php foreach ($panel_list as $k => $label): ?>
                <a href="index.php?panel=<?= $k ?>"<?= $k === $panel ? ' class="aktif" aria-current="page"' : '' ?>><?= e($label) ?></a>
            <?php endforeach; ?>
            <a class="kembali" href="../">&larr; Kembali ke undangan</a>
        </nav>

        <div class="akun">
            <span><?= e($_SESSION['admin_user']) ?> <em class="role"><?= is_superadmin() ? 'superadmin' : 'admin' ?></em></span>
            <form action="../logout.php" method="post">
                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                <button type="submit" class="tombol-teks">Keluar</button>
            </form>
        </div>
    </aside>

    <main class="isi">

        <h1><?= e($panel_list[$panel]) ?></h1>

        <?php if ($flash): ?>
            <p class="pesan <?= $flash['tipe'] === 'ok' ? 'ok' : 'galat' ?>" role="status"><?= e($flash['pesan']) ?></p>
        <?php endif; ?>

        <?php require __DIR__ . '/panel/' . $panel . '.php'; ?>

    </main>
</body>
</html>
