<?php
// Galeri dengan tata letak bebas (drag & ubah ukuran dari dashboard, tab Undangan -> Galeri).
//
// Model: kanvas 12 kolom; tiap foto = kotak (x, y, w, h) dalam satuan sel. Sel berbentuk persegi
// (lebarnya 1/12 lebar galeri), jadi tata letak ikut mengecil/membesar di semua ukuran layar
// dengan proporsi yang sama. Foto di-crop (object-fit: cover) mengikuti kotaknya.
// Default (belum pernah diatur): ukuran sama, maksimal 3 foto per baris, lalu baris berikutnya.
//
// Disimpan di kolom x, y, w, h tabel galeri (w = 0 = belum diatur).

const TATA_KOLOM       = 12;
const TATA_BARIS_MAKS  = 60;   // tinggi kanvas maksimal (baris)
const TATA_MAKS_FOTO   = 60;
const TATA_MAKS_BARU   = 20;   // foto baru per sekali simpan
const TATA_DEFAULT_W   = 4;    // 3 foto per baris
const TATA_DEFAULT_H   = 3;    // rasio 4:3

/** Tata letak bawaan untuk $n foto: [[x,y,w,h], ...] */
function galeri_tata_default(int $n): array
{
    $hasil = [];
    $per_baris = intdiv(TATA_KOLOM, TATA_DEFAULT_W);
    for ($i = 0; $i < $n; $i++) {
        $hasil[] = [
            ($i % $per_baris) * TATA_DEFAULT_W,
            intdiv($i, $per_baris) * TATA_DEFAULT_H,
            TATA_DEFAULT_W,
            TATA_DEFAULT_H,
        ];
    }
    return $hasil;
}

/**
 * Foto galeri + posisinya, urut dari atas ke bawah, kiri ke kanan (urutan ini juga dipakai lightbox).
 * Kalau ada foto yang belum punya tata letak, semua foto disusun dengan tata letak bawaan.
 * @return list<array{id:int,file:string,lebar:int,tinggi:int,x:int,y:int,w:int,h:int}>
 */
function ambil_galeri(): array
{
    $baris = db()->query('SELECT id, file, lebar, tinggi, x, y, w, h FROM galeri ORDER BY id')->fetchAll();
    foreach ($baris as &$b) {
        foreach (['id', 'lebar', 'tinggi', 'x', 'y', 'w', 'h'] as $k) $b[$k] = (int) $b[$k];
    }
    unset($b);

    $belum = array_filter($baris, static fn (array $b): bool => $b['w'] <= 0 || $b['h'] <= 0);
    if ($belum) {
        foreach (galeri_tata_default(count($baris)) as $i => [$x, $y, $w, $h]) {
            [$baris[$i]['x'], $baris[$i]['y'], $baris[$i]['w'], $baris[$i]['h']] = [$x, $y, $w, $h];
        }
    }

    usort($baris, static fn (array $a, array $b): int => [$a['y'], $a['x'], $a['id']] <=> [$b['y'], $b['x'], $b['id']]);
    return $baris;
}

/** Jumlah baris kanvas yang dipakai (untuk rasio tinggi galeri). Minimal 1. */
function galeri_jumlah_baris(array $galeri): int
{
    $maks = 1;
    foreach ($galeri as $g) $maks = max($maks, $g['y'] + $g['h']);
    return $maks;
}

/** Dua kotak saling menimpa? */
function tata_bertabrakan(array $a, array $b): bool
{
    return $a['x'] < $b['x'] + $b['w'] && $b['x'] < $a['x'] + $a['w']
        && $a['y'] < $b['y'] + $b['h'] && $b['y'] < $a['y'] + $a['h'];
}

/**
 * Validasi tata letak kiriman editor (JSON: [{"k":"g12","x":0,"y":0,"w":4,"h":3}, ...]).
 * k = "g<id>" untuk foto yang sudah ada, "n<angka>" untuk foto baru (file ikut diunggah di galeri_baru[k]).
 *
 * @param  int[]    $id_ada    id foto yang saat ini ada di database
 * @param  string[] $kunci_file kunci foto baru yang filenya ikut terkirim
 * @return array{0: list<array{k:string,x:int,y:int,w:int,h:int}>, 1: ?string} [tata letak, galat]
 */
function validasi_tata_galeri(string $json, array $id_ada, array $kunci_file): array
{
    $data = json_decode($json, true);
    if (!is_array($data) || !array_is_list($data)) return [[], 'Data tata letak galeri tidak valid.'];
    if (count($data) > TATA_MAKS_FOTO) return [[], 'Galeri maksimal ' . TATA_MAKS_FOTO . ' foto.'];

    $hasil = [];
    $dipakai = [];
    $baru = 0;
    foreach ($data as $it) {
        if (!is_array($it)) return [[], 'Data tata letak galeri tidak valid.'];
        $k = (string) ($it['k'] ?? '');
        if (isset($dipakai[$k])) return [[], 'Data tata letak galeri tidak valid (foto ganda).'];
        $dipakai[$k] = true;

        if (preg_match('/^g(\d+)$/', $k, $m)) {
            if (!in_array((int) $m[1], $id_ada, true)) return [[], 'Ada foto galeri yang sudah dihapus dari server. Muat ulang halaman.'];
        } elseif (preg_match('/^n\d+$/', $k)) {
            if (!in_array($k, $kunci_file, true)) return [[], 'File untuk foto baru galeri tidak ditemukan.'];
            $baru++;
        } else {
            return [[], 'Data tata letak galeri tidak valid.'];
        }

        $box = [];
        foreach (['x', 'y', 'w', 'h'] as $f) {
            if (!isset($it[$f]) || !is_int($it[$f])) return [[], 'Posisi/ukuran foto galeri harus berupa bilangan bulat.'];
            $box[$f] = $it[$f];
        }
        if ($box['w'] < 1 || $box['h'] < 1 || $box['x'] < 0 || $box['y'] < 0) return [[], 'Ukuran foto galeri minimal 1 kolom x 1 baris.'];
        if ($box['x'] + $box['w'] > TATA_KOLOM) return [[], 'Foto galeri melewati batas lebar (' . TATA_KOLOM . ' kolom).'];
        if ($box['y'] + $box['h'] > TATA_BARIS_MAKS) return [[], 'Tata letak galeri terlalu tinggi (maksimal ' . TATA_BARIS_MAKS . ' baris).'];

        foreach ($hasil as $lain) {
            if (tata_bertabrakan($box, $lain)) return [[], 'Ada foto galeri yang saling menimpa. Atur ulang posisinya.'];
        }
        $hasil[] = ['k' => $k] + $box;
    }
    if ($baru > TATA_MAKS_BARU) return [[], 'Maksimal ' . TATA_MAKS_BARU . ' foto baru sekali simpan.'];

    return [$hasil, null];
}
