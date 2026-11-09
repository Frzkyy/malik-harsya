<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';

if (is_admin()) {
    header('Location: ' . app_url('dashboard/'), true, 303);
    exit;
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $now = time();

    if (($_SESSION['login_lock'] ?? 0) > $now) {
        $error = 'Terlalu banyak percobaan. Coba lagi sebentar lagi.';
    } elseif (!csrf_valid($_POST['csrf'] ?? null)) {
        $error = 'Sesi kedaluwarsa. Silakan coba lagi.';
    } else {
        $username = strtolower(trim((string) ($_POST['username'] ?? '')));
        $password = (string) ($_POST['password'] ?? '');

        $row = null;
        try {
            $s = db()->prepare('SELECT id, username, password_hash, role FROM admin WHERE username = ?');
            $s->execute([$username]);
            $row = $s->fetch() ?: null;
        } catch (Throwable $ex) {
            error_log('Login gagal baca DB: ' . $ex->getMessage());
        }

        // Selalu jalankan password_verify supaya waktu respons sama (username ada/tidak).
        $hash = $row['password_hash'] ?? password_hash('dummy', PASSWORD_DEFAULT);
        $ok   = password_verify($password, $hash) && $row !== null;

        if ($ok) {
            session_regenerate_id(true);
            $_SESSION['admin_id']   = (int) $row['id'];
            $_SESSION['admin_user'] = $row['username'];
            $_SESSION['admin_role'] = $row['role'];
            unset($_SESSION['login_gagal'], $_SESSION['login_lock'], $_SESSION['csrf']);
            header('Location: ' . app_url('dashboard/'), true, 303);
            exit;
        }

        usleep(500000);

        $gagal = ($_SESSION['login_gagal'] ?? 0) + 1;
        $_SESSION['login_gagal'] = $gagal;
        if ($gagal >= 5) {
            $_SESSION['login_lock']  = $now + 60;
            $_SESSION['login_gagal'] = 0;
        }

        $error = 'Username atau password salah.';
    }
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
    <link rel="stylesheet" href="assets/css/style.css">

    <title>Login Admin</title>
</head>

<body>
    <main id="login">

        <a class="kembali-undangan" href="./">&larr; Kembali ke undangan</a>

        <h1>Login</h1>

        <p class="login-sub">Khusus admin.</p>

        <?php if ($error): ?>
            <p class="pesan-login" role="alert"><?= e($error) ?></p>
        <?php endif; ?>

        <form action="login.php" method="post">

            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">

            <div class="field">
                <label for="login-username">Username</label>
                <input type="text" id="login-username" name="username" autocomplete="username" autocapitalize="none" required autofocus>
            </div>

            <div class="field">
                <label for="login-password">Password</label>
                <input type="password" id="login-password" name="password" autocomplete="current-password" required>
            </div>

            <button type="submit">Masuk</button>

        </form>

    </main>
</body>
</html>
