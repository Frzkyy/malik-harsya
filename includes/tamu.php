<?php
// Helper tamu undangan: slug untuk link pribadi, pencarian tamu, dan status RSVP-nya.

/** Slug yang tidak boleh dipakai karena bentrok dengan folder/file/rute yang sudah ada. */
const SLUG_DILARANG = [
    'login', 'logout', 'admin', 'dashboard', 'index', 'submit', 'media', 'assets',
    'includes', 'data', 'aktivitas', 'env', 'router',
];

/** "Said Fairuz Zacky" -> "said-fairuz-zacky" */
function buat_slug(string $teks): string
{
    $t = strtolower(trim($teks));
    if (function_exists('iconv')) {
        $a = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $t);
        if ($a !== false && $a !== '') $t = strtolower($a);
    }
    $t = preg_replace('/[^a-z0-9]+/', '-', $t);
    return substr(trim((string) $t, '-'), 0, 60);
}

/** Slug unik: tambah -2, -3, ... kalau sudah dipakai atau terlarang. */
function slug_unik(PDO $pdo, string $dasar, int $kecuali_id = 0): string
{
    $dasar = $dasar !== '' ? $dasar : 'tamu';
    $slug = $dasar;
    for ($i = 2; ; $i++) {
        $s = $pdo->prepare('SELECT 1 FROM tamu WHERE slug = ? AND id <> ?');
        $s->execute([$slug, $kecuali_id]);
        if (!in_array($slug, SLUG_DILARANG, true) && !$s->fetchColumn()) return $slug;
        $slug = substr($dasar, 0, 55) . '-' . $i;
    }
}

function slug_valid(string $slug): bool
{
    return (bool) preg_match('/^[a-z0-9](?:[a-z0-9-]{0,58}[a-z0-9])?$/', $slug);
}

/** Cari tamu dari slug; null kalau tidak ada / format salah. */
function cari_tamu(string $slug): ?array
{
    $slug = strtolower(trim($slug));
    if (!slug_valid($slug)) return null;
    $s = db()->prepare('SELECT id, nama, slug FROM tamu WHERE slug = ?');
    $s->execute([$slug]);
    return $s->fetch() ?: null;
}

const LABEL_STATUS_TAMU = [
    'hadir' => ['Hadir', 'hadir'],
    'tidak' => ['Berhalangan Hadir', 'tidak'],
    'ragu'  => ['Mungkin Hadir', 'ragu'],
    ''      => ['Belum Menjawab', 'belum'],
];
