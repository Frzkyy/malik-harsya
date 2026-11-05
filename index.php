<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';

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

$galeri = [];
try {
    $galeri = db()->query('SELECT file, lebar, tinggi FROM galeri ORDER BY id')->fetchAll();
} catch (Throwable $ex) {
    error_log('Gagal memuat galeri: ' . $ex->getMessage());
}

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

    <meta property="og:title" content="Undangan Pernikahan Malik & Harsya">
    <meta property="og:image" content="media/belakang-belakangan-putih.jpeg">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@400;500;600;700&family=Great+Vibes&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="assets/css/style.css">

    <title>Malik & Harsya</title>
</head>

<body>
    <main id="bungkus">

        <section id="cover-image">
            <p id="wedding-title">The Wedding of</p>

            <div id="couple-name">
                <span>Malik</span>
                <span>&</span>
                <span>Harsya</span>
            </div>

            <div id="cover-text">
                <p id="cover-invitation">Dengan hormat, kami mengundang</p>
                <p id="cover-name">Walter White</p>
            </div>

            <p id="cover-date">17 Agustus 1945</p>
        </section>

        <section id="ucapan-tujuan">
            <img src="media/ornamen-sudut-1.png" alt="">
            <img src="media/ornamen-lurus-atas-1.png" alt="">

            <p>
                Dengan segala hormat, kami mengundang Bapak/Ibu/Saudara/i
                untuk hadir dan memberikan doa restu pada hari pernikahan kami.
                Mohon konfirmasi kehadiran Anda melalui form RSVP di bawah.
            </p>

            <a class="btn" href="#rsvp">RSVP</a>

            <img src="media/ornamen-lurus-bawah-1.png" alt="">
            <img src="media/ornamen-sudut-1.png" alt="">
        </section>

        <section id="informasi-pengundang">

            <h1>Mempelai</h1>

            <h2>Dua insan, satu tujuan</h2>

            <img id="ornamen-tengah" src="media/ornamen-lurus-tengah-1.png" alt="">

            <?php foreach (['pria' => 'Putra dari', 'wanita' => 'Putri dari'] as $jenis => $label): ?>
                <?php if (empty($pengantin[$jenis])) continue; $p = $pengantin[$jenis]; ?>
                <div id="mempelai-<?= $jenis ?>">

                    <img class="foto-mempelai" src="<?= e($p['foto']) ?>" alt="<?= e($p['nama']) ?>"
                         style="aspect-ratio: <?= rasio((int) $p['foto_lebar'], (int) $p['foto_tinggi']) ?>"
                         decoding="async">

                    <p>
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

            <h2 class="tanggal-acara">Senin, 17 Agustus 1945</h2>

            <div class="waktu">

                <div class="waktu-isi">

                    <div class="waktu-info">

                        <p class="jam">08:00 - 10:00 WIB</p>

                        <h2>Akad Nikah</h2>

                        <p>
                            Di hadapan keluarga tercinta, dua hati terikat
                            dalam janji suci yang diucapkan dengan khidmat
                            dan penuh syukur.
                        </p>

                    </div>

                    <img src="media/footage-1.jpg" alt="Suasana akad nikah">

                </div>

            </div>

            <div class="waktu">

                <div class="waktu-isi">

                    <div class="waktu-info">

                        <p class="jam">15:00 - 20:00 WIB</p>

                        <h2>Resepsi</h2>

                        <p>
                            Senja menjadi saksi kebahagiaan yang kami rayakan
                            bersama keluarga dan sahabat, dalam hangatnya
                            doa, tawa, dan kebersamaan.
                        </p>

                    </div>

                    <img src="media/footage-2.jpg" alt="Suasana resepsi">

                </div>

            </div>

        </section>

        <section id="lokasi-acara">

            <h1>Alamat</h1>

            <div id="alamat">

                <div>

                    <h2>Hotel Aryaduta Pekanbaru</h2>

                    <p>
                        Jl. Diponegoro No. 34, Pekanbaru, Riau 28116
                    </p>

                    <p>
                        Di jantung Kota Bertuah, di antara hangatnya doa dan
                        indahnya kebersamaan, kami akan mengikat dua hati dalam
                        satu janji. Hari ini menjadi awal dari langkah panjang
                        yang akan kami tempuh bersama, menyusuri setiap musim
                        kehidupan dengan cinta, kesetiaan, dan rasa syukur.
                    </p>

                    <a class="btn" href="https://www.google.com/maps/search/?api=1&query=Hotel+Aryaduta+Pekanbaru" target="_blank" rel="noopener">Buka di Maps</a>

                </div>

                <div>

                    <img src="media/alamat-arya-duta.png" alt="Hotel Aryaduta Pekanbaru">

                </div>

            </div>

        </section>
                <section id="galeri">

            <h1>Galeri</h1>

            <?php if ($galeri): ?>
                <ul id="galeri-list">
                    <?php foreach ($galeri as $i => $g):
                        $w = (int) $g['lebar']; $h = (int) $g['tinggi'];
                        $r = $w > 0 && $h > 0 ? round($w / $h, 4) : 1.5;
                    ?>
                        <li style="--r: <?= $r ?>">
                            <button type="button" class="galeri-foto" data-i="<?= $i ?>" aria-label="Perbesar foto <?= $i + 1 ?>">
                                <img src="<?= e($g['file']) ?>" alt="Galeri foto <?= $i + 1 ?>"
                                     <?= $w > 0 ? 'width="' . $w . '" height="' . $h . '"' : '' ?>
                                     loading="lazy" decoding="async">
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
            <?php endif; ?>

        </section>

        <section id="story">

            <h1>Cerita Kami</h1>

            <img class="ornamen-story" src="media/ornamen-lurus-tengah-1.png" alt="">

            <h2>
                Setiap perjalanan bermula dari satu pertemuan. Inilah kisah
                singkat kami, dari langkah pertama hingga janji yang kami
                rajut bersama.
            </h2>

            <div class="cerita cerita-kiri">

                <div class="cerita-text">

                    <h3>Awal Bertemu (2019)</h3>

                    <p>
                        Takdir mempertemukan kami di sebuah pertemuan sederhana.
                        Tak ada yang istimewa hari itu, selain dua senyum yang
                        diam-diam saling mengingat.
                    </p>

                </div>

                <img src="media/Story-1.png" alt="Awal Bertemu">

            </div>

            <div class="cerita cerita-kanan">

                <img src="media/Story-2.png" alt="Mengenal Lebih Dekat">

                <div class="cerita-text">

                    <h3>Mengenal Lebih Dekat (2021)</h3>

                    <p>
                        Dari sapa yang singkat, percakapan tumbuh panjang.
                        Hari demi hari, kami belajar bahwa nyaman bisa lahir
                        dari hal yang paling biasa.
                    </p>

                </div>

            </div>

            <div class="cerita cerita-kiri">

                <div class="cerita-text">

                    <h3>Menjalin Komitmen (2024)</h3>

                    <p>
                        Bersama waktu, rasa itu menemukan arahnya. Kami memilih
                        untuk saling menggenggam, melangkah dalam suka dan duka
                        dengan satu tujuan yang sama.
                    </p>

                </div>

                <img src="media/story-3.png" alt="Menjalin Komitmen">

            </div>

            <div class="cerita cerita-kanan">

                <img src="media/story-4.png" alt="Ikatan Pertunangan">

                <div class="cerita-text">

                    <h3>Ikatan Pertunangan (2026)</h3>

                    <p>
                        Dengan restu keluarga, sebuah cincin menjadi tanda niat
                        yang kian mantap. Dari sinilah kami menapaki jalan menuju
                        hari yang telah lama kami doakan.
                    </p>

                </div>

            </div>

        </section>

        <section id="ucapan">

            <img class="ornamen-kiri-atas" src="media/ornamen-sudut-1.png" alt="">

            <h1>Ucapan</h1>

            <img class="ornamen-tengah" src="media/ornamen-lurus-tengah-1.png" alt="">

            <p>
                Terima kasih atas setiap doa, ucapan, dan perhatian yang mengalir
                kepada kami. Bagi kami, kehadiran dan doa restu dari keluarga,
                sahabat, serta orang-orang terkasih bagaikan cahaya yang
                menerangi perjalanan menuju hari bahagia ini. Semoga setiap
                doa baik yang terucap menjadi keberkahan yang menaungi rumah
                tangga kami, tumbuh bersama kebahagiaan, keharmonisan, dan
                ketulusan dalam setiap langkah yang kami jalani bersama.
            </p>

            <img class="ornamen-kanan-bawah" src="media/ornamen-sudut-1.png" alt="">

        </section>

        <section id="rsvp">

            <h1>RSVP</h1>

            <img class="ornamen-rsvp" src="media/ornamen-lurus-tengah-1.png" alt="">

            <p>
                Mohon konfirmasi kehadiran Anda agar kami dapat
                mempersiapkan segalanya dengan sebaik mungkin.
            </p>

            <?php if ($flash): ?>
                <p class="pesan-rsvp pesan-<?= e($flash['tipe']) ?>" role="status">
                    <?= e($flash['pesan']) ?>
                </p>
            <?php endif; ?>

            <form id="form-rsvp" action="submit.php" method="post">

                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">

                <div class="jebakan" aria-hidden="true">
                    <label for="rsvp-website">Website</label>
                    <input type="text" id="rsvp-website" name="website" tabindex="-1" autocomplete="off">
                </div>

                <div class="field">
                    <label for="rsvp-nama">Nama</label>
                    <input type="text" id="rsvp-nama" name="nama" maxlength="100" autocomplete="name" value="<?= e($old['nama'] ?? '') ?>" required>
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

            <div id="daftar-ucapan">

                <h2>Ucapan &amp; Doa<?= $total_ucapan ? ' (' . $total_ucapan . ')' : '' ?></h2>

                <?php if (!$daftar_ucapan): ?>
                    <p class="ucapan-kosong">Belum ada ucapan. Jadilah yang pertama menyampaikan doa.</p>
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