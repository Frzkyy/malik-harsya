<?php
// Bootstrap: baca .env, mulai session, helper CSRF & escape.
// Folder ini (includes/) tidak untuk diakses browser; root proyek = satu tingkat di atasnya.

define('APP_ROOT', dirname(__DIR__));

/**
 * Alamat absolut-path ke dalam aplikasi (mis. "/dashboard/"), dihitung dari lokasi project terhadap document root.
 * Dipakai untuk redirect supaya hasilnya tidak bergantung pada tanda "../" atau "./" relatif
 * (yang di beberapa konfigurasi server/proxy bisa berubah jadi "//dashboard/").
 */
function app_url(string $path = ''): string
{
    $dr   = rtrim(str_replace('\\', '/', (string) realpath($_SERVER['DOCUMENT_ROOT'] ?? '')), '/');
    $root = str_replace('\\', '/', APP_ROOT);
    $base = ($dr !== '' && str_starts_with($root, $dr . '/')) ? substr($root, strlen($dr)) : '';
    return rtrim($base, '/') . '/' . ltrim($path, '/');
}

(function () {
    $file = APP_ROOT . '/.env';
    if (!is_readable($file)) return;

    foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#' || strpos($line, '=') === false) continue;
        [$k, $v] = explode('=', $line, 2);
        $_ENV[trim($k)] = trim($v, " \t\"'");
    }
})();

function env(string $key, ?string $default = null): ?string
{
    return $_ENV[$key] ?? $default;
}

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => !empty($_SERVER['HTTPS']),
    ]);
    session_start();
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_valid(?string $token): bool
{
    return is_string($token)
        && !empty($_SESSION['csrf'])
        && hash_equals($_SESSION['csrf'], $token);
}

function e(?string $s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function is_admin(): bool
{
    return !empty($_SESSION['admin_id']);
}

function is_superadmin(): bool
{
    return is_admin() && ($_SESSION['admin_role'] ?? '') === 'superadmin';
}

// Pastikan akun di sesi masih ada di database (admin yang dihapus langsung ditendang).
function admin_masih_ada(): bool
{
    require_once __DIR__ . '/db.php';
    try {
        $s = db()->prepare('SELECT role FROM admin WHERE id = ?');
        $s->execute([(int) $_SESSION['admin_id']]);
        $role = $s->fetchColumn();
        if (!$role) return false;
        $_SESSION['admin_role'] = $role;
        return true;
    } catch (Throwable $ex) {
        return false;
    }
}

function require_admin(): void
{
    if (!is_admin() || !admin_masih_ada()) {
        unset($_SESSION['admin_id'], $_SESSION['admin_user'], $_SESSION['admin_role']);
        header('Location: ' . app_url('login.php'), true, 303);
        exit;
    }
}

function require_superadmin(): void
{
    require_admin();
    if (!is_superadmin()) {
        header('Location: ' . app_url('dashboard/'), true, 303);
        exit;
    }
}
