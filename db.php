<?php
require_once __DIR__ . '/config.php';

function db(): PDO
{
    static $pdo = null;
    if ($pdo) return $pdo;

    $path = env('DB_PATH', __DIR__ . '/data/rsvp.db');
    $dir  = dirname($path);

    if (!is_dir($dir) && !mkdir($dir, 0750, true) && !is_dir($dir)) {
        throw new RuntimeException("Folder DB tidak bisa dibuat: $dir");
    }

    $pdo = new PDO('sqlite:' . $path, null, null, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    $pdo->exec('PRAGMA journal_mode = WAL');
    $pdo->exec('PRAGMA busy_timeout = 5000');
    $pdo->exec('PRAGMA foreign_keys = ON');

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS rsvp (
            id         INTEGER PRIMARY KEY AUTOINCREMENT,
            nama       TEXT    NOT NULL,
            status     TEXT    NOT NULL CHECK (status IN ('hadir','tidak','ragu')),
            jumlah     INTEGER NOT NULL DEFAULT 1 CHECK (jumlah BETWEEN 0 AND 10),
            ucapan     TEXT,
            created_at TEXT    NOT NULL DEFAULT CURRENT_TIMESTAMP
        )
    ");
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_rsvp_created ON rsvp (created_at DESC)');

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS admin (
            id            INTEGER PRIMARY KEY AUTOINCREMENT,
            username      TEXT    NOT NULL UNIQUE,
            password_hash TEXT    NOT NULL,
            role          TEXT    NOT NULL DEFAULT 'admin' CHECK (role IN ('superadmin','admin')),
            created_at    TEXT    NOT NULL DEFAULT CURRENT_TIMESTAMP
        )
    ");

    // Seed superadmin pertama dari .env, hanya kalau tabel admin masih kosong.
    if ((int) $pdo->query('SELECT COUNT(*) FROM admin')->fetchColumn() === 0) {
        $u = strtolower(trim((string) env('SUPERADMIN_USER', '')));
        $p = (string) env('SUPERADMIN_PASSWORD', '');
        if ($u !== '' && $p !== '') {
            $pdo->prepare('INSERT INTO admin (username, password_hash, role) VALUES (?, ?, ?)')
                ->execute([$u, password_hash($p, PASSWORD_DEFAULT), 'superadmin']);
        }
    }

    return $pdo;
}
