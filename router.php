<?php
// Router untuk server bawaan PHP:  php -S localhost:8000 router.php
// Meniru .htaccess: /login, /admin, /nama-tamu, dan memblokir data/, includes/, .env.
// (Di Apache file ini tidak dipakai; .htaccess yang bekerja.)

$path = rawurldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/');
$path = '/' . ltrim($path, '/');

if (preg_match('#^/(data|includes)(/|$)#', $path) || preg_match('#^/\.env#', $path)) {
    http_response_code(403);
    exit('Forbidden');
}

if ($path === '/login' || $path === '/login/') {
    require __DIR__ . '/login.php';
    return true;
}
if ($path === '/masuk' || $path === '/masuk/') {
    require __DIR__ . '/masuk.php';
    return true;
}
if ($path === '/admin' || $path === '/admin/') {
    header('Location: /dashboard/', true, 301);
    return true;
}

$file = __DIR__ . $path;
if ($path === '/' || is_file($file) || is_dir($file)) {
    return false; // biarkan server bawaan melayani file / folder yang ada
}

if ($path === '/tamu' || $path === '/tamu/') {
    require __DIR__ . '/index.php';
    return true;
}

if (preg_match('#^/([a-z0-9][a-z0-9-]*)/?$#', $path, $m)) {
    $_GET['tamu'] = $m[1];
    require __DIR__ . '/index.php';
    return true;
}

return false;
