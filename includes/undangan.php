<?php
// Isi undangan yang bisa diedit dari dashboard (tab Undangan): teks, tanggal, jam, dan gambar.
//
// - Nilai bawaan = teks/gambar yang dulu tertulis langsung di index.php, jadi tampilan awal tidak berubah.
// - Database (tabel `undangan`, kunci -> nilai) hanya menyimpan nilai yang pernah diubah.
// - Nama panggilan, nama lengkap, orang tua, dan foto mempelai tetap di tabel `pengantin`.
// - Nama tamu TIDAK dikelola di sini (ada di menu Tamu Undangan + gerbang di index.php).

const UNDANGAN_BAWAAN = [
    // 1. Cover
    'cover_judul' => 'The Wedding of',
    'cover_foto'  => 'media/belakang-belakangan-putih.jpeg',
    'tanggal'     => '1945-08-17',              // dipakai di cover & halaman Tempat dan Waktu

    // 2. Kata pengantar
    'kata_pengantar' => 'Dengan segala hormat, kami mengundang Bapak/Ibu/Saudara/i untuk hadir dan memberikan doa restu pada hari pernikahan kami. Mohon konfirmasi kehadiran Anda melalui form RSVP di bawah.',

    // 3. Data diri mempelai (judul + deskripsi; nama & foto ada di tabel pengantin)
    'mempelai_judul'     => 'Mempelai',
    'mempelai_deskripsi' => 'Dua insan, satu tujuan',

    // 4. Tempat dan waktu
    'akad_mulai'      => '08:00',
    'akad_selesai'    => '10:00',
    'akad_zona'       => 'WIB',
    'akad_deskripsi'  => 'Di hadapan keluarga tercinta, dua hati terikat dalam janji suci yang diucapkan dengan khidmat dan penuh syukur.',
    'akad_foto'       => 'media/footage-1.jpg',

    'resepsi_mulai'     => '15:00',
    'resepsi_selesai'   => '20:00',
    'resepsi_zona'      => 'WIB',
    'resepsi_deskripsi' => 'Senja menjadi saksi kebahagiaan yang kami rayakan bersama keluarga dan sahabat, dalam hangatnya doa, tawa, dan kebersamaan.',
    'resepsi_foto'      => 'media/footage-2.jpg',
];

/** Kunci yang berisi path gambar (di tabel undangan). */
const UNDANGAN_KUNCI_FOTO = ['cover_foto', 'akad_foto', 'resepsi_foto'];

const UNDANGAN_ZONA = ['WIB', 'WITA', 'WIT'];

/** Aturan teks: kunci => [wajib, panjang maksimal, label untuk pesan galat]. */
const UNDANGAN_ATURAN_TEKS = [
    'cover_judul'        => [true,  60,  'Judul cover'],
    'kata_pengantar'     => [false, 800, 'Kata pengantar'],
    'mempelai_judul'     => [true,  40,  'Judul halaman mempelai'],
    'mempelai_deskripsi' => [false, 150, 'Deskripsi halaman mempelai'],
    'akad_deskripsi'     => [false, 400, 'Deskripsi akad nikah'],
    'resepsi_deskripsi'  => [false, 400, 'Deskripsi resepsi'],
];

/** Aturan teks mempelai (tabel pengantin): kolom => [wajib, panjang maksimal, label]. */
const UNDANGAN_ATURAN_MEMPELAI = [
    'panggilan' => [true,  30,  'Nama panggilan'],
    'nama'      => [true,  100, 'Nama lengkap'],
    'ayah'      => [false, 100, 'Nama ayah'],
    'ibu'       => [false, 100, 'Nama ibu'],
];

const BULAN_INDO = [1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
const HARI_INDO  = [1 => 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'];

/** Nilai undangan = bawaan ditimpa isi database. Kalau database bermasalah, pakai bawaan. */
function ambil_undangan(): array
{
    $hasil = UNDANGAN_BAWAAN;
    try {
        foreach (db()->query('SELECT kunci, nilai FROM undangan')->fetchAll() as $r) {
            if (array_key_exists($r['kunci'], $hasil)) $hasil[$r['kunci']] = (string) $r['nilai'];
        }
    } catch (Throwable $ex) {
        error_log('Gagal memuat isi undangan: ' . $ex->getMessage());
    }
    return $hasil;
}

/** "2026-08-17" -> "17 Agustus 2026" (atau "Senin, 17 Agustus 2026" kalau $dengan_hari). */
function tanggal_indo(string $iso, bool $dengan_hari = false): string
{
    $d = DateTime::createFromFormat('!Y-m-d', $iso);
    if (!$d) return '';
    $teks = (int) $d->format('j') . ' ' . BULAN_INDO[(int) $d->format('n')] . ' ' . $d->format('Y');
    return $dengan_hari ? HARI_INDO[(int) $d->format('N')] . ', ' . $teks : $teks;
}

/** "08:00","10:00","WIB" -> "08:00 - 10:00 WIB"; tanpa jam selesai -> "08:00 WIB - Selesai". */
function jam_acara(string $mulai, string $selesai, string $zona): string
{
    return $selesai !== '' ? "$mulai - $selesai $zona" : "$mulai $zona - Selesai";
}

/** Path gambar yang aman dipakai di atribut style (url(...)); selain itu pakai cadangan. */
function url_gambar_aman(string $path, string $cadangan): string
{
    return preg_match('#^[A-Za-z0-9/_.-]+$#', $path) ? $path : $cadangan;
}

function jam_valid(string $v): bool
{
    return (bool) preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $v);
}

function tanggal_valid(string $v): bool
{
    $d = DateTime::createFromFormat('!Y-m-d', $v);
    return $d && $d->format('Y-m-d') === $v && (int) $d->format('Y') >= 1900 && (int) $d->format('Y') <= 2100;
}

/**
 * Validasi kiriman form editor.
 * @return array{0: array, 1: array, 2: ?string} [teks undangan, mempelai[jenis][kolom], pesan galat atau null]
 */
function validasi_undangan(array $in): array
{
    $ambil = static fn (string $k): string => trim(str_replace(["\r\n", "\r"], "\n", (string) ($in[$k] ?? '')));
    $panjang = static fn (string $s, int $maks): bool => mb_strlen($s, 'UTF-8') <= $maks;

    $u = [];
    foreach (UNDANGAN_ATURAN_TEKS as $k => [$wajib, $maks, $label]) {
        $v = $ambil($k);
        if ($wajib && $v === '') return [[], [], "$label wajib diisi."];
        if (!$panjang($v, $maks)) return [[], [], "$label maksimal $maks karakter."];
        $u[$k] = $v;
    }

    $u['tanggal'] = $ambil('tanggal');
    if (!tanggal_valid($u['tanggal'])) return [[], [], 'Tanggal pernikahan tidak valid.'];

    foreach (['akad' => 'akad nikah', 'resepsi' => 'resepsi'] as $a => $nama) {
        $u["{$a}_mulai"]   = $ambil("{$a}_mulai");
        $u["{$a}_selesai"] = $ambil("{$a}_selesai");
        $u["{$a}_zona"]    = $ambil("{$a}_zona");
        if (!jam_valid($u["{$a}_mulai"])) return [[], [], "Jam mulai $nama wajib diisi dengan jam yang valid."];
        if ($u["{$a}_selesai"] !== '' && !jam_valid($u["{$a}_selesai"])) return [[], [], "Jam selesai $nama tidak valid."];
        if (!in_array($u["{$a}_zona"], UNDANGAN_ZONA, true)) return [[], [], "Zona waktu $nama tidak valid."];
    }

    $m = [];
    foreach (['pria' => 'mempelai pria', 'wanita' => 'mempelai wanita'] as $jenis => $nama) {
        foreach (UNDANGAN_ATURAN_MEMPELAI as $kol => [$wajib, $maks, $label]) {
            $v = $ambil("{$jenis}_{$kol}");
            if ($wajib && $v === '') return [[], [], "$label $nama wajib diisi."];
            if (!$panjang($v, $maks)) return [[], [], "$label $nama maksimal $maks karakter."];
            $m[$jenis][$kol] = str_replace("\n", ' ', $v);   // satu baris
        }
    }

    // Judul/jenis satu baris tidak boleh ada baris baru.
    foreach (['cover_judul', 'mempelai_judul', 'mempelai_deskripsi'] as $k) {
        $u[$k] = str_replace("\n", ' ', $u[$k]);
    }

    return [$u, $m, null];
}

/** Simpan nilai (upsert) ke tabel undangan. Panggil di dalam transaksi. */
function simpan_undangan(PDO $pdo, array $nilai): void
{
    $s = $pdo->prepare('INSERT INTO undangan (kunci, nilai) VALUES (?, ?)
                        ON CONFLICT(kunci) DO UPDATE SET nilai = excluded.nilai');
    foreach ($nilai as $k => $v) {
        if (array_key_exists($k, UNDANGAN_BAWAAN)) $s->execute([$k, (string) $v]);
    }
}
