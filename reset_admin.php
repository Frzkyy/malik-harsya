<?php
// Atur ulang password admin dari terminal (tidak bisa dibuka lewat browser).
//
//   php reset_admin.php                      -> tampilkan database yang dipakai & daftar admin
//   php reset_admin.php <username> <password> -> ganti password admin yang SUDAH ada
//   php reset_admin.php <username> <password> --buat -> buat akun superadmin baru (hanya kalau username belum ada)
//   php reset_admin.php <username> <password> --ganti -> HAPUS SEMUA superadmin, lalu sisakan satu akun superadmin ini
//                                                       (akun dengan role 'admin' biasa tidak disentuh)
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';

$pdo = db();
$path = env('DB_PATH', 'data/rsvp.db');
$full = preg_match('#^(/|[A-Za-z]:[\\\\/])#', $path) ? $path : APP_ROOT . '/' . $path;
echo "Database : $full\n";

$admin = $pdo->query('SELECT username, role FROM admin ORDER BY id')->fetchAll();
echo "Admin    : " . ($admin ? implode(', ', array_map(fn ($a) => $a['username'] . " ({$a['role']})", $admin)) : '(kosong)') . "\n";

if ($argc < 3) {
    echo "\nUntuk ganti password: php reset_admin.php <username> <password>\n";
    exit(0);
}

$user = strtolower(trim($argv[1]));
$pass = $argv[2];
if ($user === '' || $pass === '') {
    fwrite(STDERR, "Username dan password tidak boleh kosong.\n");
    exit(1);
}

$hash = password_hash($pass, PASSWORD_DEFAULT);

if (in_array('--ganti', $argv, true)) {
    $pdo->beginTransaction();
    $hapus = $pdo->exec("DELETE FROM admin WHERE role = 'superadmin'");
    // Kalau ada admin biasa dengan username yang sama, ambil alih jadi superadmin supaya username tetap unik.
    $pdo->prepare('DELETE FROM admin WHERE username = ?')->execute([$user]);
    $pdo->prepare("INSERT INTO admin (username, password_hash, role) VALUES (?, ?, 'superadmin')")->execute([$user, $hash]);
    $pdo->commit();
    echo "\n$hapus superadmin lama dihapus. Sekarang hanya ada satu superadmin: '$user'.\n";
    exit(0);
}
$s = $pdo->prepare('SELECT id FROM admin WHERE username = ?');
$s->execute([$user]);

if ($s->fetchColumn()) {
    $pdo->prepare('UPDATE admin SET password_hash = ? WHERE username = ?')->execute([$hash, $user]);
    echo "\nPassword '$user' diganti.\n";
} elseif (!in_array('--buat', $argv, true)) {
    fwrite(STDERR, "\nUsername '$user' tidak ada di daftar admin di atas, jadi tidak ada yang diubah.\n"
        . "Periksa penulisan username. Untuk membuat akun baru, tambahkan --buat di akhir perintah.\n");
    exit(1);
} else {
    $pdo->prepare("INSERT INTO admin (username, password_hash, role) VALUES (?, ?, 'superadmin')")->execute([$user, $hash]);
    echo "\nAkun superadmin '$user' dibuat.\n";
}
