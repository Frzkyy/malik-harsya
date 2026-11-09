<?php
// Helper unggah gambar (galeri & foto pengantin).
// Gambar divalidasi isinya (bukan cuma ekstensi), diputar sesuai EXIF, diperkecil,
// lalu disimpan ulang sebagai JPEG/PNG/WebP baru -> metadata & payload tersembunyi terbuang.

const UNGGAH_DIR   = 'media/unggahan';        // relatif terhadap APP_ROOT
const UNGGAH_MAKS  = 12 * 1024 * 1024;        // 12 MB per file
const UNGGAH_SISI  = 2000;                    // sisi terpanjang setelah diperkecil (px)
const UNGGAH_TIPE  = [
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'image/webp' => 'webp',
];

/**
 * Simpan satu file hasil $_FILES.
 * @return array{file: string, lebar: int, tinggi: int}  file = path relatif (media/unggahan/xxx.jpg)
 * @throws RuntimeException pesan aman untuk ditampilkan ke admin
 */
function simpan_gambar(array $f): array
{
    $err = $f['error'] ?? UPLOAD_ERR_NO_FILE;
    if ($err === UPLOAD_ERR_INI_SIZE || $err === UPLOAD_ERR_FORM_SIZE) {
        throw new RuntimeException('Ukuran file terlalu besar (maksimal 12 MB).');
    }
    if ($err !== UPLOAD_ERR_OK || !is_uploaded_file($f['tmp_name'] ?? '')) {
        throw new RuntimeException('File gagal diunggah.');
    }
    if (($f['size'] ?? 0) > UNGGAH_MAKS) {
        throw new RuntimeException('Ukuran file terlalu besar (maksimal 12 MB).');
    }

    $tmp  = $f['tmp_name'];
    // fileinfo tidak selalu aktif di hosting; getimagesize() membaca header gambar dan cukup untuk ini.
    $mime = class_exists('finfo')
        ? (new finfo(FILEINFO_MIME_TYPE))->file($tmp)
        : (@getimagesize($tmp)['mime'] ?? '');
    if (!isset(UNGGAH_TIPE[$mime])) {
        throw new RuntimeException('Format tidak didukung. Gunakan JPG, PNG, atau WebP.');
    }

    $info = @getimagesize($tmp);
    if (!$info || $info[0] < 1 || $info[1] < 1 || $info[0] * $info[1] > 80_000_000) {
        throw new RuntimeException('File bukan gambar yang valid.');
    }

    // Tanpa GD (sebagian hosting): simpan file asli yang sudah lolos validasi isi gambar.
    // Konsekuensinya gambar tidak diperkecil dan metadatanya tidak dibuang.
    if (!function_exists('imagecreatetruecolor') || !function_exists('imagejpeg')) {
        return simpan_asli($tmp, $mime, (int) $info[0], (int) $info[1]);
    }

    $src = match ($mime) {
        'image/jpeg' => @imagecreatefromjpeg($tmp),
        'image/png'  => @imagecreatefrompng($tmp),
        'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($tmp) : false,
    };
    if (!$src) {
        throw new RuntimeException('Gambar rusak atau tidak bisa dibaca.');
    }

    // Putar sesuai EXIF (foto dari HP sering "tidur" kalau tidak diputar).
    if ($mime === 'image/jpeg' && function_exists('exif_read_data')) {
        $exif = @exif_read_data($tmp);
        $o = (int) ($exif['Orientation'] ?? 1);
        $derajat = [3 => 180, 6 => -90, 8 => 90][$o] ?? 0;
        if ($derajat !== 0 && ($r = imagerotate($src, $derajat, 0))) {
            $src = $r;
        }
    }

    $w = imagesx($src);
    $h = imagesy($src);
    $skala = min(1.0, UNGGAH_SISI / max($w, $h));
    if ($skala < 1.0) {
        $baru = imagescale($src, max(1, (int) round($w * $skala)), max(1, (int) round($h * $skala)), IMG_BICUBIC);
        if ($baru) {
            $src = $baru;
            $w = imagesx($src);
            $h = imagesy($src);
        }
    }

    $dir = APP_ROOT . '/' . UNGGAH_DIR;
    if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
        throw new RuntimeException('Folder unggahan tidak bisa dibuat.');
    }

    $ext  = UNGGAH_TIPE[$mime];
    $nama = bin2hex(random_bytes(10)) . '.' . $ext;
    $tuju = $dir . '/' . $nama;

    $ok = match ($ext) {
        'jpg'  => imagejpeg($src, $tuju, 85),
        'png'  => imagepng($src, $tuju, 6),
        'webp' => function_exists('imagewebp') && imagewebp($src, $tuju, 85),
    };
    if (!$ok) {
        throw new RuntimeException('Gagal menyimpan gambar.');
    }
    @chmod($tuju, 0644);

    return ['file' => UNGGAH_DIR . '/' . $nama, 'lebar' => $w, 'tinggi' => $h];
}

/** Hapus file hanya kalau memang hasil unggahan (foto bawaan di media/ tidak disentuh). */
function hapus_unggahan(string $path): void
{
    if (!preg_match('#^' . preg_quote(UNGGAH_DIR, '#') . '/[a-f0-9]{20}\.(jpg|png|webp)$#', $path)) return;
    $full = APP_ROOT . '/' . $path;
    if (is_file($full)) @unlink($full);
}

/** Ukuran gambar dari file yang sudah ada di disk; [0, 0] kalau tidak terbaca. */
function ukuran_gambar(string $path): array
{
    $i = @getimagesize(APP_ROOT . '/' . $path);
    return $i ? [(int) $i[0], (int) $i[1]] : [0, 0];
}

/** Susun $_FILES['x'] (multi-file) menjadi daftar file tunggal. */
function daftar_file(string $key): array
{
    $f = $_FILES[$key] ?? null;
    if (!$f || !is_array($f['name'])) return $f && ($f['error'] ?? 4) !== UPLOAD_ERR_NO_FILE ? [$f] : [];
    $hasil = [];
    foreach ($f['name'] as $i => $_) {
        if ($f['error'][$i] === UPLOAD_ERR_NO_FILE) continue;
        $hasil[] = [
            'name' => $f['name'][$i], 'type' => $f['type'][$i], 'tmp_name' => $f['tmp_name'][$i],
            'error' => $f['error'][$i], 'size' => $f['size'][$i],
        ];
    }
    return $hasil;
}

/** Simpan file apa adanya (dipakai kalau ekstensi GD tidak ada). Nama & ekstensi dibuat sendiri dari tipe hasil validasi. */
function simpan_asli(string $tmp, string $mime, int $w, int $h): array
{
    // Foto HP yang "tidur" ditandai lewat EXIF; browser memutarnya sendiri, jadi tukar lebar/tinggi agar rasio benar.
    if ($mime === 'image/jpeg' && function_exists('exif_read_data')) {
        $o = (int) (@exif_read_data($tmp)['Orientation'] ?? 1);
        if ($o >= 5 && $o <= 8) [$w, $h] = [$h, $w];
    }

    $dir = APP_ROOT . '/' . UNGGAH_DIR;
    if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
        throw new RuntimeException('Folder unggahan tidak bisa dibuat.');
    }

    $nama = bin2hex(random_bytes(10)) . '.' . UNGGAH_TIPE[$mime];
    if (!move_uploaded_file($tmp, $dir . '/' . $nama)) {
        throw new RuntimeException('Gagal menyimpan gambar. Periksa izin tulis folder ' . UNGGAH_DIR . '/.');
    }
    @chmod($dir . '/' . $nama, 0644);

    return ['file' => UNGGAH_DIR . '/' . $nama, 'lebar' => $w, 'tinggi' => $h];
}
