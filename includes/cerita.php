<?php
// Halaman "Cerita Kami": 2 sampai 8 cerita (judul, deskripsi, foto), urutan bisa diatur dari dashboard.
// Disimpan di tabel `cerita`; nilai bawaan = cerita yang dulu tertulis langsung di index.php.
// Cerita ganjil tampil teks di kiri, cerita genap foto di kiri (bergantian otomatis).

const CERITA_MIN = 2;
const CERITA_MAKS = 8;

/** Foto cadangan kalau cerita baru belum diberi foto (bergantian); admin tinggal menggantinya. */
const CERITA_FOTO_CADANGAN = ['media/Story-1.png', 'media/Story-2.png', 'media/story-3.png', 'media/story-4.png'];

const CERITA_BAWAAN = [
    [
        'judul'     => 'Awal Bertemu (2019)',
        'deskripsi' => 'Takdir mempertemukan kami di sebuah pertemuan sederhana. Tak ada yang istimewa hari itu, selain dua senyum yang diam-diam saling mengingat.',
        'foto'      => 'media/Story-1.png',
    ],
    [
        'judul'     => 'Mengenal Lebih Dekat (2021)',
        'deskripsi' => 'Dari sapa yang singkat, percakapan tumbuh panjang. Hari demi hari, kami belajar bahwa nyaman bisa lahir dari hal yang paling biasa.',
        'foto'      => 'media/Story-2.png',
    ],
    [
        'judul'     => 'Menjalin Komitmen (2024)',
        'deskripsi' => 'Bersama waktu, rasa itu menemukan arahnya. Kami memilih untuk saling menggenggam, melangkah dalam suka dan duka dengan satu tujuan yang sama.',
        'foto'      => 'media/story-3.png',
    ],
    [
        'judul'     => 'Ikatan Pertunangan (2026)',
        'deskripsi' => 'Dengan restu keluarga, sebuah cincin menjadi tanda niat yang kian mantap. Dari sinilah kami menapaki jalan menuju hari yang telah lama kami doakan.',
        'foto'      => 'media/story-4.png',
    ],
];

/** @return list<array{id:int,urutan:int,judul:string,deskripsi:string,foto:string}> */
function ambil_cerita(): array
{
    try {
        $r = db()->query('SELECT id, urutan, judul, deskripsi, foto FROM cerita ORDER BY urutan, id')->fetchAll();
        foreach ($r as &$c) { $c['id'] = (int) $c['id']; $c['urutan'] = (int) $c['urutan']; }
        return $r;
    } catch (Throwable $ex) {
        error_log('Gagal memuat cerita: ' . $ex->getMessage());
        return [];
    }
}

/**
 * Validasi kiriman form: $_POST['cerita'][kunci] = ['judul' => ..., 'deskripsi' => ...], urutan = urutan di form.
 * Kunci: "c<id>" cerita yang sudah ada, "n<angka>" cerita baru.
 *
 * @param  int[] $id_ada id cerita yang ada di database
 * @return array{0: list<array{k:string,judul:string,deskripsi:string}>, 1: ?string}
 */
function validasi_cerita($masuk, array $id_ada): array
{
    if (!is_array($masuk)) return [[], 'Data cerita tidak valid.'];
    if (count($masuk) < CERITA_MIN) return [[], 'Cerita minimal ' . CERITA_MIN . '.'];
    if (count($masuk) > CERITA_MAKS) return [[], 'Cerita maksimal ' . CERITA_MAKS . '.'];

    $hasil = [];
    $no = 0;
    foreach ($masuk as $k => $c) {
        $no++;
        $k = (string) $k;
        if (!is_array($c)) return [[], 'Data cerita tidak valid.'];
        if (preg_match('/^c(\d+)$/', $k, $m)) {
            if (!in_array((int) $m[1], $id_ada, true)) return [[], 'Ada cerita yang sudah dihapus dari server. Muat ulang halaman.'];
        } elseif (!preg_match('/^n\d+$/', $k)) {
            return [[], 'Data cerita tidak valid.'];
        }

        $judul = trim(str_replace(["\r", "\n"], ' ', (string) ($c['judul'] ?? '')));
        $desk  = trim(str_replace(["\r\n", "\r"], "\n", (string) ($c['deskripsi'] ?? '')));
        if ($judul === '') return [[], "Judul cerita ke-$no wajib diisi."];
        if (mb_strlen($judul, 'UTF-8') > 80) return [[], "Judul cerita ke-$no maksimal 80 karakter."];
        if (mb_strlen($desk, 'UTF-8') > 500) return [[], "Deskripsi cerita ke-$no maksimal 500 karakter."];

        $hasil[] = ['k' => $k, 'judul' => $judul, 'deskripsi' => $desk];
    }
    return [$hasil, null];
}
