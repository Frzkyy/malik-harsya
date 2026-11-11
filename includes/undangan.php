<?php
// Isi undangan yang bisa diedit dari dashboard (tab Undangan): teks, tanggal, jam, dan gambar.
//
// - Nilai bawaan = teks/gambar yang dulu tertulis langsung di index.php, jadi tampilan awal tidak berubah.
// - Database (tabel `undangan`, kunci -> nilai) hanya menyimpan nilai yang pernah diubah.
// - Nama panggilan, nama lengkap, orang tua, dan foto mempelai tetap di tabel `pengantin`.
// - Nama tamu TIDAK dikelola di sini (ada di menu Tamu Undangan + gerbang di index.php).
// - Foto galeri (tata letak drag) ada di includes/galeri.php, daftar cerita di includes/cerita.php.

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

    // 5. Alamat
    'alamat_judul'     => 'Alamat',
    'alamat_lokasi'    => 'Hotel Aryaduta Pekanbaru',
    'alamat_jalan'     => 'Jl. Diponegoro No. 34, Pekanbaru, Riau 28116',
    'alamat_deskripsi' => 'Di jantung Kota Bertuah, di antara hangatnya doa dan indahnya kebersamaan, kami akan mengikat dua hati dalam satu janji. Hari ini menjadi awal dari langkah panjang yang akan kami tempuh bersama, menyusuri setiap musim kehidupan dengan cinta, kesetiaan, dan rasa syukur.',
    'alamat_maps'      => 'https://www.google.com/maps/search/?api=1&query=Hotel+Aryaduta+Pekanbaru',   // kosong = dibuat otomatis dari lokasi + jalan
    'alamat_tombol'    => 'Buka di Maps',
    'alamat_foto'      => 'media/alamat-arya-duta.png',

    // 6. Galeri (foto & tata letak: includes/galeri.php)
    'galeri_judul' => 'Galeri',

    // 7. Cerita kami (daftar cerita: includes/cerita.php)
    'cerita_judul'     => 'Cerita Kami',
    'cerita_deskripsi' => 'Setiap perjalanan bermula dari satu pertemuan. Inilah kisah singkat kami, dari langkah pertama hingga janji yang kami rajut bersama.',

    // 8. Ucapan
    'ucapan_judul' => 'Ucapan',
    'ucapan_teks'  => 'Terima kasih atas setiap doa, ucapan, dan perhatian yang mengalir kepada kami. Bagi kami, kehadiran dan doa restu dari keluarga, sahabat, serta orang-orang terkasih bagaikan cahaya yang menerangi perjalanan menuju hari bahagia ini. Semoga setiap doa baik yang terucap menjadi keberkahan yang menaungi rumah tangga kami, tumbuh bersama kebahagiaan, keharmonisan, dan ketulusan dalam setiap langkah yang kami jalani bersama.',

    // 9. RSVP dan Ucapan & Doa
    'rsvp_judul'       => 'RSVP',
    'rsvp_deskripsi'   => 'Mohon konfirmasi kehadiran Anda agar kami dapat mempersiapkan segalanya dengan sebaik mungkin.',
    'ucapan_doa_judul' => 'Ucapan & Doa',
    'ucapan_kosong'    => 'Belum ada ucapan. Jadilah yang pertama menyampaikan doa.',
];

/** Kunci yang berisi path gambar (di tabel undangan). */
const UNDANGAN_KUNCI_FOTO = ['cover_foto', 'akad_foto', 'resepsi_foto', 'alamat_foto'];

const UNDANGAN_ZONA = ['WIB', 'WITA', 'WIT'];

/** Aturan teks: kunci => [wajib, panjang maksimal, label untuk pesan galat, boleh banyak baris]. */
const UNDANGAN_ATURAN_TEKS = [
    'cover_judul'        => [true,  60,  'Judul cover',                false],
    'kata_pengantar'     => [false, 800, 'Kata pengantar',             true],
    'mempelai_judul'     => [true,  40,  'Judul halaman mempelai',     false],
    'mempelai_deskripsi' => [false, 150, 'Deskripsi halaman mempelai', false],
    'akad_deskripsi'     => [false, 400, 'Deskripsi akad nikah',       true],
    'resepsi_deskripsi'  => [false, 400, 'Deskripsi resepsi',          true],

    'alamat_judul'       => [true,  40,  'Judul halaman alamat',       false],
    'alamat_lokasi'      => [true,  100, 'Nama lokasi',                false],
    'alamat_jalan'       => [false, 200, 'Nama jalan',                 false],
    'alamat_deskripsi'   => [false, 600, 'Deskripsi alamat',           true],
    'alamat_tombol'      => [true,  30,  'Teks tombol peta',           false],

    'galeri_judul'       => [true,  40,  'Judul galeri',               false],

    'cerita_judul'       => [true,  40,  'Judul halaman cerita',       false],
    'cerita_deskripsi'   => [false, 400, 'Deskripsi awal cerita',      true],

    'ucapan_judul'       => [true,  40,  'Judul halaman ucapan',       false],
    'ucapan_teks'        => [false, 900, 'Teks ucapan',                true],

    'rsvp_judul'         => [true,  40,  'Judul RSVP',                 false],
    'rsvp_deskripsi'     => [false, 400, 'Deskripsi RSVP',             true],
    'ucapan_doa_judul'   => [true,  40,  'Judul Ucapan & Doa',        false],
    'ucapan_kosong'      => [false, 200, 'Teks saat belum ada ucapan', false],
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

/** Link tombol peta: link yang diisi admin, atau pencarian Google Maps dari lokasi + jalan. '' = tombol disembunyikan. */
function url_maps(array $u): string
{
    $link = trim((string) $u['alamat_maps']);
    if ($link !== '' && preg_match('#^https?://#i', $link)) return $link;
    $q = trim($u['alamat_lokasi'] . ' ' . $u['alamat_jalan']);
    return $q === '' ? '' : 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode($q);
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
    foreach (UNDANGAN_ATURAN_TEKS as $k => [$wajib, $maks, $label, $banyak_baris]) {
        $v = $ambil($k);
        if (!$banyak_baris) $v = trim(str_replace("\n", ' ', $v));   // judul/nama = satu baris
        if ($wajib && $v === '') return [[], [], "$label wajib diisi."];
        if (!$panjang($v, $maks)) return [[], [], "$label maksimal $maks karakter."];
        $u[$k] = $v;
    }

    // Link peta: kosong = otomatis dari lokasi + jalan; kalau diisi harus http(s).
    $u['alamat_maps'] = $ambil('alamat_maps');
    if ($u['alamat_maps'] !== '' && (!preg_match('#^https?://#i', $u['alamat_maps']) || !filter_var($u['alamat_maps'], FILTER_VALIDATE_URL))) {
        return [[], [], 'Link Google Maps harus diawali http:// atau https:// dan berupa alamat web yang valid.'];
    }
    if (!$panjang($u['alamat_maps'], 500)) return [[], [], 'Link Google Maps maksimal 500 karakter.'];

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
