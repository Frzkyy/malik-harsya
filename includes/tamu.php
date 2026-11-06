<?php
// Helper tamu undangan: slug untuk link pribadi, pencarian tamu, dan status RSVP-nya.

/** Slug yang tidak boleh dipakai karena bentrok dengan folder/file/rute yang sudah ada. */
const SLUG_DILARANG = [
    'login', 'logout', 'admin', 'dashboard', 'index', 'submit', 'media', 'assets',
    'includes', 'data', 'aktivitas', 'env', 'router', 'masuk',
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

/** Cari tamu dari yang diketik di halaman masuk: link (slug) atau nama lengkap, tanpa peduli huruf besar/kecil. */
function cari_tamu_login(string $input): ?array
{
    $input = trim($input);
    if ($input === '' || !preg_match('/^.{1,100}$/su', $input)) return null;

    $s = db()->prepare('SELECT id, nama, slug FROM tamu WHERE slug = ? OR lower(nama) = lower(?) ORDER BY id LIMIT 1');
    $s->execute([buat_slug($input), $input]);
    return $s->fetch() ?: null;
}

/** Tandai sesi sebagai tamu tertentu (pseudo-login: tanpa password). */
function masuk_sebagai_tamu(array $tamu): void
{
    session_regenerate_id(true);
    $_SESSION['tamu_id'] = (int) $tamu['id'];
    unset($_SESSION['tamu_guest']);
}

function masuk_sebagai_guest(): void
{
    session_regenerate_id(true);
    unset($_SESSION['tamu_id']);
    $_SESSION['tamu_guest'] = true;
}

function keluar_tamu(): void
{
    unset($_SESSION['tamu_id'], $_SESSION['tamu_guest']);
}

/** UTC dari database -> "6 Nov 2026, 08:24 WIB" */
function waktu_wib(?string $utc): string
{
    if (!$utc) return '-';
    try {
        $d = new DateTime($utc, new DateTimeZone('UTC'));
        $d->setTimezone(new DateTimeZone('Asia/Jakarta'));
        return $d->format('j M Y, H:i') . ' WIB';
    } catch (Throwable $ex) {
        return '-';
    }
}

function badge_status(?string $status): string
{
    [$label, $kelas] = LABEL_STATUS_TAMU[$status ?? ''] ?? LABEL_STATUS_TAMU[''];
    return '<span class="status-tamu ' . $kelas . '">' . e($label) . '</span>';
}
