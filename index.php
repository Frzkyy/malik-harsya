<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/tamu.php';
require_once __DIR__ . '/includes/undangan.php';
require_once __DIR__ . '/includes/galeri.php';
require_once __DIR__ . '/includes/cerita.php';

// Mode pratinjau: dipakai iframe di dashboard (tab Undangan), hanya untuk admin yang login.
// Gerbang & sesi tamu dilewati supaya tidak mengubah sesi siapa pun.
$pratinjau = isset($_GET['pratinjau']) && is_admin();

$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);
$old = $flash['old'] ?? [];

// Daftar ucapan: maksimal 5 per halaman, sisanya lewat tombol Berikutnya.
$per_hal     = 5;
$hal         = max(1, (int) ($_GET['hal'] ?? 1));
$total_ucapan = 0;
$daftar_ucapan = [];

try {
    $total_ucapan = (int) db()->query(
        "SELECT COUNT(*) FROM rsvp WHERE ucapan IS NOT NULL AND ucapan <> ''"
    )->fetchColumn();

    $total_hal = max(1, (int) ceil($total_ucapan / $per_hal));
    $hal       = min($hal, $total_hal);

    $stmt = db()->prepare(
        "SELECT nama, status, ucapan, created_at FROM rsvp
         WHERE ucapan IS NOT NULL AND ucapan <> ''
         ORDER BY created_at DESC, id DESC
         LIMIT :lim OFFSET :off"
    );
    $stmt->bindValue(':lim', $per_hal, PDO::PARAM_INT);
    $stmt->bindValue(':off', ($hal - 1) * $per_hal, PDO::PARAM_INT);
    $stmt->execute();
    $daftar_ucapan = $stmt->fetchAll();
} catch (Throwable $ex) {
    error_log('Gagal memuat ucapan: ' . $ex->getMessage());
    $total_hal = 1;
}

// Pseudo-login tamu (satu halaman): gerbang layar penuh yang "naik" setelah tamu masuk.
//  - sudah ada sesi tamu / "lihat sebagai tamu" -> tanpa gerbang
//  - buka lewat link pribadi (/nama-tamu, ?tamu=) -> otomatis masuk, gerbang menyambut dengan tombol "Buka Undangan"
//  - selain itu -> gerbang berisi form nama + "Lihat sebagai tamu"
$tamu = null;
$guest = false;
$gerbang = 'form';        // 'form' | 'sambut' | '' (tanpa gerbang)
$gerbang_error = (string) ($_SESSION['masuk_error'] ?? '');
unset($_SESSION['masuk_error']);

try {
    $slug_link = $pratinjau ? '' : trim((string) ($_GET['tamu'] ?? ''));
    if ($slug_link !== '') {
        $tamu = cari_tamu($slug_link);
        if ($tamu) {
            masuk_sebagai_tamu($tamu);
            $gerbang = 'sambut';
        } else {
            $gerbang_error = 'Link undangan tidak dikenali. Silakan masukkan nama Anda.';
        }
    } elseif (!$pratinjau && !empty($_SESSION['tamu_id'])) {
        $s = db()->prepare('SELECT id, nama, slug FROM tamu WHERE id = ?');
        $s->execute([(int) $_SESSION['tamu_id']]);
        $tamu = $s->fetch() ?: null;
        if ($tamu) {
            $gerbang = '';
        } else {
            keluar_tamu();   // tamu sudah dihapus admin
        }
    }
} catch (Throwable $ex) {
    error_log('Gagal memuat tamu: ' . $ex->getMessage());
}

if (!$tamu && !empty($_SESSION['tamu_guest'])) {
    $guest = true;
    $gerbang = '';
}
if ($pratinjau) $gerbang = '';
$nama_tamu = $tamu['nama'] ?? 'Bapak/Ibu';

$galeri = [];
try {
    $galeri = ambil_galeri();   // foto + posisinya (includes/galeri.php)
} catch (Throwable $ex) {
    error_log('Gagal memuat galeri: ' . $ex->getMessage());
}
$cerita = ambil_cerita();      // includes/cerita.php

try {
    $pengantin = ambil_pengantin();
} catch (Throwable $ex) {
    error_log('Gagal memuat pengantin: ' . $ex->getMessage());
    $pengantin = [];
}

// Rasio lebar/tinggi foto; foto yang sangat ekstrem dijepit supaya layout tetap rapi.
function rasio(int $w, int $h, float $min = 0.5, float $maks = 1.6): float
{
    if ($w < 1 || $h < 1) return 1 / 1.618;
    return round(max($min, min($maks, $w / $h)), 4);
}

function orang_tua(array $p): string
{
    $o = [];
    if ($p['ayah'] !== '') $o[] = 'Bapak ' . $p['ayah'];
    if ($p['ibu'] !== '')  $o[] = 'Ibu ' . $p['ibu'];
    return implode(' & ', $o);
}

$und = ambil_undangan();   // teks, tanggal, jam & gambar yang diedit dari dashboard

$nama_pria   = isset($pengantin['pria'])   ? nama_panggilan($pengantin['pria'])   : 'Malik';
$nama_wanita = isset($pengantin['wanita']) ? nama_panggilan($pengantin['wanita']) : 'Harsya';
$nama_pria   = $nama_pria   !== '' ? $nama_pria   : 'Malik';
$nama_wanita = $nama_wanita !== '' ? $nama_wanita : 'Harsya';

$label_status = ['hadir' => 'Hadir', 'ragu' => 'Belum pasti', 'tidak' => 'Tidak hadir'];
$bulan = [1 => 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];

function tgl_ucapan(string $utc, array $bulan): string
{
    try {
        $d = new DateTime($utc, new DateTimeZone('UTC'));
        $d->setTimezone(new DateTimeZone('Asia/Jakarta'));
        return $d->format('j') . ' ' . $bulan[(int) $d->format('n')] . ' ' . $d->format('Y');
    } catch (Throwable $ex) {
        return '';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <meta property="og:title" content="Undangan Pernikahan <?= e($nama_pria) ?> &amp; <?= e($nama_wanita) ?>">
    <meta property="og:image" content="media/belakang-belakangan-putih.jpeg">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@400;500;600;700&family=Great+Vibes&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="assets/css/style.css">

    <title><?= e($nama_pria) ?> &amp; <?= e($nama_wanita) ?></title>
</head>

<body<?= $gerbang !== '' ? ' class="terkunci"' : '' ?>>

    <?php if ($gerbang !== ''): ?>
        <section id="gerbang" aria-label="Masuk undangan">
            <div class="gerbang-isi">
                <p class="gerbang-judul"><?= e($und['cover_judul']) ?></p>
                <p class="gerbang-nama"><?= e($nama_pria) ?> <span>&amp;</span> <?= e($nama_wanita) ?></p>

                <p id="gerbang-error" class="pesan-login" role="alert" <?= $gerbang_error === '' ? 'hidden' : '' ?>><?= e($gerbang_error) ?></p>

                <?php if ($gerbang === 'sambut'): ?>
                    <p class="gerbang-sapa">Kepada Yth.<br><strong><?= e($nama_tamu) ?></strong></p>
                    <button type="button" id="gerbang-buka">Buka Undangan</button>
                <?php else: ?>
                    <p class="gerbang-sapa">Masukkan nama Anda sebagaimana tertulis di undangan.</p>

                    <form id="gerbang-form" action="masuk.php" method="post">
                        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                        <input type="hidden" name="aksi" value="masuk">
                        <label for="gerbang-nama" class="sr-only">Nama</label>
                        <input type="text" id="gerbang-nama" name="nama" maxlength="100" placeholder="Nama Anda" autocomplete="off" required>
                        <button type="submit">Masuk</button>
                    </form>

                    <form id="gerbang-guest" action="masuk.php" method="post">
                        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                        <input type="hidden" name="aksi" value="guest">
                        <button type="submit" class="btn-sekunder">Lihat sebagai tamu</button>
                    </form>
                <?php endif; ?>
            </div>
        </section>
    <?php endif; ?>

    <main id="bungkus">

        <section id="cover-image" style="background-image: url('<?= e(url_gambar_aman($und['cover_foto'], UNDANGAN_BAWAAN['cover_foto'])) ?>')">
            <p id="wedding-title"><?= e($und['cover_judul']) ?></p>

            <div id="couple-name">
                <span><?= e($nama_pria) ?></span>
                <span>&</span>
                <span><?= e($nama_wanita) ?></span>
            </div>

            <div id="cover-text">
                <p id="cover-invitation">Dengan hormat, kami mengundang</p>
                <p id="cover-name"><?= e($nama_tamu) ?></p>
                <a class="ganti-tamu" id="ganti-tamu" href="masuk.php?keluar=1"><?= $tamu ? 'Bukan Anda? Ganti' : 'Masuk dengan nama Anda' ?></a>
            </div>

            <p id="cover-date"><?= e(tanggal_indo($und['tanggal'])) ?></p>
        </section>

        <section id="ucapan-tujuan">
            <img src="media/ornamen-sudut-1.png" alt="">
            <img src="media/ornamen-lurus-atas-1.png" alt="">

            <p id="kata-pengantar"><?= nl2br(e($und['kata_pengantar'])) ?></p>

            <a class="btn" href="#rsvp">RSVP</a>

            <img src="media/ornamen-lurus-bawah-1.png" alt="">
            <img src="media/ornamen-sudut-1.png" alt="">
        </section>

        <section id="informasi-pengundang">

            <h1 id="mempelai-judul"><?= e($und['mempelai_judul']) ?></h1>

            <h2 id="mempelai-deskripsi"><?= e($und['mempelai_deskripsi']) ?></h2>

            <img id="ornamen-tengah" src="media/ornamen-lurus-tengah-1.png" alt="">

            <?php foreach (['pria' => 'Putra dari', 'wanita' => 'Putri dari'] as $jenis => $label): ?>
                <?php if (empty($pengantin[$jenis])) continue; $p = $pengantin[$jenis]; ?>
                <div id="mempelai-<?= $jenis ?>">

                    <img class="foto-mempelai" src="<?= e($p['foto']) ?>" alt="<?= e($p['nama']) ?>"
                         style="aspect-ratio: <?= rasio((int) $p['foto_lebar'], (int) $p['foto_tinggi']) ?>"
                         decoding="async">

                    <p id="teks-mempelai-<?= $jenis ?>">
                        <?= e($p['nama']) ?><br>
                        <?php if (orang_tua($p) !== ''): ?>
                            <?= $label ?><br>
                            <?= e(orang_tua($p)) ?>
                        <?php endif; ?>
                    </p>

                </div>
            <?php endforeach; ?>

        </section>

        <section id="waktu-acara">

            <h1>Tempat & Waktu</h1>

            <h2 class="tanggal-acara" id="tanggal-acara"><?= e(tanggal_indo($und['tanggal'], true)) ?></h2>

            <div class="waktu" id="acara-akad">

                <div class="waktu-isi">

                    <div class="waktu-info">

                        <p class="jam"><?= e(jam_acara($und['akad_mulai'], $und['akad_selesai'], $und['akad_zona'])) ?></p>

                        <h2>Akad Nikah</h2>

                        <p><?= nl2br(e($und['akad_deskripsi'])) ?></p>

                    </div>

                    <img src="<?= e(url_gambar_aman($und['akad_foto'], UNDANGAN_BAWAAN['akad_foto'])) ?>" alt="Suasana akad nikah">

                </div>

            </div>

            <div class="waktu" id="acara-resepsi">

                <div class="waktu-isi">

                    <div class="waktu-info">

                        <p class="jam"><?= e(jam_acara($und['resepsi_mulai'], $und['resepsi_selesai'], $und['resepsi_zona'])) ?></p>

                        <h2>Resepsi</h2>

                        <p><?= nl2br(e($und['resepsi_deskripsi'])) ?></p>

                    </div>

                    <img src="<?= e(url_gambar_aman($und['resepsi_foto'], UNDANGAN_BAWAAN['resepsi_foto'])) ?>" alt="Suasana resepsi">

                </div>

            </div>

        </section>

        <section id="lokasi-acara">

            <h1 id="alamat-judul"><?= e($und['alamat_judul']) ?></h1>

            <div id="alamat">

                <div>

                    <h2 id="alamat-lokasi"><?= e($und['alamat_lokasi']) ?></h2>

                    <p id="alamat-jalan"><?= e($und['alamat_jalan']) ?></p>

                    <p id="alamat-deskripsi"><?= nl2br(e($und['alamat_deskripsi'])) ?></p>

                    <?php $link_maps = url_maps($und); ?>
                    <a class="btn" id="alamat-maps" href="<?= e($link_maps) ?>" target="_blank" rel="noopener"<?= $link_maps === '' ? ' hidden' : '' ?>><?= e($und['alamat_tombol']) ?></a>

                </div>

                <div>

                    <img id="alamat-foto" src="<?= e(url_gambar_aman($und['alamat_foto'], UNDANGAN_BAWAAN['alamat_foto'])) ?>" alt="<?= e($und['alamat_lokasi']) ?>">

                </div>

            </div>

        </section>

        <section id="galeri">

            <h1 id="galeri-judul"><?= e($und['galeri_judul']) ?></h1>

            <?php /* Tata letak: kanvas 12 kolom, tiap foto = kotak (x, y, w, h). Lihat includes/galeri.php */ ?>
            <ul id="galeri-list" style="--baris: <?= galeri_jumlah_baris($galeri) ?>"<?= $galeri ? '' : ' hidden' ?>>
                <?php foreach ($galeri as $i => $g): ?>
                    <li data-k="g<?= $g['id'] ?>" style="--x: <?= $g['x'] ?>; --y: <?= $g['y'] ?>; --w: <?= $g['w'] ?>; --h: <?= $g['h'] ?>">
                        <button type="button" class="galeri-foto" data-i="<?= $i ?>" aria-label="Perbesar foto <?= $i + 1 ?>">
                            <img src="<?= e($g['file']) ?>" alt="Galeri foto <?= $i + 1 ?>" loading="lazy" decoding="async">
                        </button>
                    </li>
                <?php endforeach; ?>
            </ul>

            <dialog id="galeri-box" aria-label="Foto galeri">
                <button type="button" class="gb-tutup" aria-label="Tutup">&times;</button>
                <button type="button" class="gb-nav gb-prev" aria-label="Sebelumnya">&lsaquo;</button>
                <img id="gb-img" alt="">
                <button type="button" class="gb-nav gb-next" aria-label="Berikutnya">&rsaquo;</button>
                <p id="gb-hitung"></p>
            </dialog>

        </section>

        <section id="story">

            <h1 id="cerita-judul"><?= e($und['cerita_judul']) ?></h1>

            <img class="ornamen-story" src="media/ornamen-lurus-tengah-1.png" alt="">

            <h2 id="cerita-deskripsi"><?= e($und['cerita_deskripsi']) ?></h2>

            <?php foreach ($cerita as $i => $c):
                $kiri = $i % 2 === 0;   // ganjil: teks di kiri; genap: foto di kiri
                $teks = '<div class="cerita-text"><h3>' . e($c['judul']) . '</h3><p>' . nl2br(e($c['deskripsi'])) . '</p></div>';
                $foto = '<img src="' . e(url_gambar_aman($c['foto'], CERITA_FOTO_CADANGAN[$i % count(CERITA_FOTO_CADANGAN)])) . '" alt="' . e($c['judul']) . '">';
            ?>
                <div class="cerita <?= $kiri ? 'cerita-kiri' : 'cerita-kanan' ?>" data-k="c<?= $c['id'] ?>">
                    <?= $kiri ? $teks . $foto : $foto . $teks ?>
                </div>
            <?php endforeach; ?>

        </section>

        <section id="ucapan">

            <img class="ornamen-kiri-atas" src="media/ornamen-sudut-1.png" alt="">

            <h1 id="ucapan-judul"><?= e($und['ucapan_judul']) ?></h1>

            <img class="ornamen-tengah" src="media/ornamen-lurus-tengah-1.png" alt="">

            <p id="ucapan-teks"><?= nl2br(e($und['ucapan_teks'])) ?></p>

            <img class="ornamen-kanan-bawah" src="media/ornamen-sudut-1.png" alt="">

        </section>

        <section id="rsvp">

            <h1 id="rsvp-judul"><?= e($und['rsvp_judul']) ?></h1>

            <img class="ornamen-rsvp" src="media/ornamen-lurus-tengah-1.png" alt="">

            <p id="rsvp-deskripsi"><?= nl2br(e($und['rsvp_deskripsi'])) ?></p>

            <?php if ($flash): ?>
                <p class="pesan-rsvp pesan-<?= e($flash['tipe']) ?>" role="status">
                    <?= e($flash['pesan']) ?>
                </p>
            <?php endif; ?>

            <form id="form-rsvp"<?= ($tamu || $pratinjau) ? '' : ' hidden' ?> action="submit.php" method="post">

                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">

                <div class="jebakan" aria-hidden="true">
                    <label for="rsvp-website">Website</label>
                    <input type="text" id="rsvp-website" name="website" tabindex="-1" autocomplete="off">
                </div>

                <div class="field">
                    <label for="rsvp-nama">Nama</label>
                    <input type="text" id="rsvp-nama" name="nama" maxlength="100" autocomplete="name" value="<?= e($old['nama'] ?? ($tamu['nama'] ?? '')) ?>" required>
                </div>

                <fieldset class="field">
                    <legend>Kehadiran</legend>

                    <div class="pilihan">
                        <label><input type="radio" name="status" value="hadir" required <?= ($old['status'] ?? '') === 'hadir' ? 'checked' : '' ?>> Hadir</label>
                        <label><input type="radio" name="status" value="ragu" <?= ($old['status'] ?? '') === 'ragu' ? 'checked' : '' ?>> Belum pasti</label>
                        <label><input type="radio" name="status" value="tidak" <?= ($old['status'] ?? '') === 'tidak' ? 'checked' : '' ?>> Tidak hadir</label>
                    </div>
                </fieldset>

                <div class="field">
                    <label for="rsvp-jumlah">Jumlah tamu</label>
                    <input type="number" id="rsvp-jumlah" name="jumlah" min="1" max="5" value="<?= (int) ($old['jumlah'] ?? 1) ?: 1 ?>" required>
                </div>

                <div class="field">
                    <label for="rsvp-pesan">Ucapan &amp; doa</label>
                    <textarea id="rsvp-pesan" name="ucapan" rows="4" maxlength="500"><?= e($old['ucapan'] ?? '') ?></textarea>
                </div>

                <button type="submit">Kirim RSVP</button>

            </form>

            <div class="rsvp-terkunci" id="rsvp-terkunci"<?= ($tamu || $pratinjau) ? ' hidden' : '' ?>>
                    <p>RSVP hanya bisa diisi oleh tamu yang diundang. Silakan masuk dengan nama Anda.</p>
                    <a class="btn" href="masuk.php?keluar=1" id="rsvp-masuk">Masuk</a>
                </div>

            <div id="daftar-ucapan">

                <h2><span id="ucapan-doa-judul"><?= e($und['ucapan_doa_judul']) ?></span><?= $total_ucapan ? ' (' . $total_ucapan . ')' : '' ?></h2>

                <?php if (!$daftar_ucapan): ?>
                    <p class="ucapan-kosong" id="ucapan-kosong"><?= e($und['ucapan_kosong']) ?></p>
                <?php else: ?>
                    <ul class="ucapan-list">
                        <?php foreach ($daftar_ucapan as $u): ?>
                            <li class="ucapan-item">
                                <div class="ucapan-kepala">
                                    <strong class="ucapan-nama"><?= e($u['nama']) ?></strong>
                                    <span class="ucapan-status status-<?= e($u['status']) ?>"><?= e($label_status[$u['status']] ?? '') ?></span>
                                </div>
                                <p class="ucapan-isi"><?= nl2br(e($u['ucapan'])) ?></p>
                                <time class="ucapan-waktu"><?= e(tgl_ucapan($u['created_at'], $bulan)) ?></time>
                            </li>
                        <?php endforeach; ?>
                    </ul>

                    <?php if ($total_hal > 1): ?>
                        <nav class="ucapan-nav" aria-label="Halaman ucapan">
                            <?php if ($hal > 1): ?>
                                <a class="btn" href="?hal=<?= $hal - 1 ?>#daftar-ucapan" rel="prev">&larr; Sebelumnya</a>
                            <?php else: ?>
                                <span class="btn nonaktif">&larr; Sebelumnya</span>
                            <?php endif; ?>

                            <span class="ucapan-hal"><?= $hal ?> / <?= $total_hal ?></span>

                            <?php if ($hal < $total_hal): ?>
                                <a class="btn" href="?hal=<?= $hal + 1 ?>#daftar-ucapan" rel="next">Berikutnya &rarr;</a>
                            <?php else: ?>
                                <span class="btn nonaktif">Berikutnya &rarr;</span>
                            <?php endif; ?>
                        </nav>
                    <?php endif; ?>
                <?php endif; ?>

            </div>

        </section>

    </main>

    <?php if ($pratinjau): ?>
        <script src="assets/js/pratinjau.js"></script>
    <?php endif; ?>

    <script>
    // Gerbang tamu: kirim nama lewat fetch, lalu gerbang naik ke atas membuka undangan.
    (function () {
        var gerbang = document.getElementById('gerbang');
        if (!gerbang) return;

        var err = document.getElementById('gerbang-error');
        var form = document.getElementById('gerbang-form');
        var guest = document.getElementById('gerbang-guest');
        var buka = document.getElementById('gerbang-buka');

        function tampilError(t) { err.textContent = t; err.hidden = !t; }

        function naik() {
            gerbang.classList.add('naik');
            document.body.classList.remove('terkunci');
            window.scrollTo(0, 0);
            // Cadangan kalau animasi dimatikan (reduced motion): tetap disingkirkan.
            setTimeout(function () { gerbang.remove(); }, 1100);
        }

        function terapkan(d) {
            document.getElementById('cover-name').textContent = d.nama;
            var g = document.getElementById('ganti-tamu');
            if (g) g.textContent = d.mode === 'tamu' ? 'Bukan Anda? Ganti' : 'Masuk dengan nama Anda';

            var f = document.getElementById('form-rsvp');
            var k = document.getElementById('rsvp-terkunci');
            if (f) f.hidden = d.mode !== 'tamu';
            if (k) k.hidden = d.mode === 'tamu';
            var n = document.getElementById('rsvp-nama');
            if (n && d.mode === 'tamu' && !n.value) n.value = d.nama;
        }

        function kirim(el) {
            el.addEventListener('submit', function (e) {
                e.preventDefault();
                tampilError('');
                var tombol = el.querySelector('button');
                tombol.disabled = true;
                fetch('masuk.php', { method: 'POST', body: new FormData(el), headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
                    .then(function (r) { return r.json(); })
                    .then(function (d) {
                        if (d.ok) { terapkan(d); naik(); } else { tampilError(d.pesan || 'Gagal masuk.'); }
                    })
                    .catch(function () { tampilError('Tidak bisa terhubung. Coba lagi.'); })
                    .then(function () { tombol.disabled = false; });
            });
        }

        if (form) kirim(form);
        if (guest) kirim(guest);
        if (buka) buka.addEventListener('click', naik);
        if (form) document.getElementById('gerbang-nama').focus();
    })();
    </script>

    <script>
    (function () {
        var box = document.getElementById('galeri-box');
        if (!box || !box.showModal) return;

        var foto = Array.prototype.slice.call(document.querySelectorAll('#galeri-list img'));
        var img = document.getElementById('gb-img');
        var hitung = document.getElementById('gb-hitung');
        var now = 0;

        function tampil(i) {
            now = (i + foto.length) % foto.length;
            img.src = foto[now].currentSrc || foto[now].src;
            img.alt = foto[now].alt;
            hitung.textContent = (now + 1) + ' / ' + foto.length;
        }

        document.querySelectorAll('.galeri-foto').forEach(function (b) {
            b.addEventListener('click', function () {
                tampil(+b.dataset.i);
                box.showModal();
            });
        });

        box.querySelector('.gb-tutup').addEventListener('click', function () { box.close(); });
        box.querySelector('.gb-prev').addEventListener('click', function () { tampil(now - 1); });
        box.querySelector('.gb-next').addEventListener('click', function () { tampil(now + 1); });

        // Klik area gelap di luar foto = tutup.
        box.addEventListener('click', function (e) { if (e.target === box) box.close(); });

        box.addEventListener('keydown', function (e) {
            if (e.key === 'ArrowLeft') tampil(now - 1);
            if (e.key === 'ArrowRight') tampil(now + 1);
        });

        // Geser kiri/kanan di layar sentuh.
        var x0 = null;
        box.addEventListener('touchstart', function (e) { x0 = e.touches[0].clientX; }, { passive: true });
        box.addEventListener('touchend', function (e) {
            if (x0 === null) return;
            var dx = e.changedTouches[0].clientX - x0;
            if (Math.abs(dx) > 50) tampil(now + (dx < 0 ? 1 : -1));
            x0 = null;
        });
    })();
    </script>

</body>
</html>