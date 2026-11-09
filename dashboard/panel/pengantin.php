<?php
// Panel Undangan (dulu "Pengantin"): editor isi undangan + pratinjau live, lalu galeri.
// Dipanggil dari dashboard/index.php. Logika editor: assets/js/editor-undangan.js,
// penyimpanan: dashboard/undangan_aksi.php, nilai bawaan & validasi: includes/undangan.php.
//
// Bagian yang sudah bisa diedit: 1 Cover, 2 Kata pengantar, 3 Mempelai, 4 Tempat & waktu.
// Alamat, galeri, cerita kami, ucapan, dan RSVP menyusul (galeri sementara tetap di bawah).

require_once __DIR__ . '/../../includes/undangan.php';

$pengantin = [];
$galeri    = [];
$und       = UNDANGAN_BAWAAN;

try {
    $pengantin = ambil_pengantin();
    $galeri = db()->query('SELECT id, file, lebar, tinggi FROM galeri ORDER BY id')->fetchAll();
    $und = ambil_undangan();
} catch (Throwable $ex) {
    error_log('Panel undangan gagal memuat data: ' . $ex->getMessage());
}

// Nilai awal form mempelai (kalau baris belum ada, kolom dibiarkan kosong).
$mp = [];
foreach (['pria', 'wanita'] as $j) {
    $p = $pengantin[$j] ?? ['nama' => '', 'ibu' => '', 'ayah' => '', 'foto' => '', 'panggilan' => ''];
    $mp[$j] = [
        'nama' => (string) $p['nama'], 'panggilan' => nama_panggilan($p),
        'ayah' => (string) $p['ayah'], 'ibu' => (string) $p['ibu'], 'foto' => (string) $p['foto'],
    ];
}

/** <input type=text> */
function ed_teks(string $nama, string $label, string $nilai, int $maks, bool $wajib = false, string $ket = ''): void
{ ?>
    <label class="ed-field">
        <span class="ed-label"><?= e($label) ?><?= $wajib ? '' : ' <small>(opsional)</small>' ?></span>
        <input type="text" name="<?= e($nama) ?>" value="<?= e($nilai) ?>" maxlength="<?= $maks ?>"<?= $wajib ? ' required' : '' ?> autocomplete="off">
        <?php if ($ket !== ''): ?><small class="ed-ket"><?= e($ket) ?></small><?php endif; ?>
    </label>
<?php }

/** <textarea> */
function ed_area(string $nama, string $label, string $nilai, int $maks, int $baris = 4): void
{ ?>
    <label class="ed-field">
        <span class="ed-label"><?= e($label) ?></span>
        <textarea name="<?= e($nama) ?>" rows="<?= $baris ?>" maxlength="<?= $maks ?>"><?= e($nilai) ?></textarea>
    </label>
<?php }

/** Tanggal pernikahan: dipakai di Cover dan Tempat & Waktu, keduanya selalu sama. */
function ed_tanggal(string $nilai, string $ket): void
{ ?>
    <label class="ed-field">
        <span class="ed-label">Tanggal pernikahan</span>
        <input type="date" name="tanggal" value="<?= e($nilai) ?>" min="1900-01-01" max="2100-12-31" required>
        <small class="ed-ket"><?= e($ket) ?></small>
    </label>
<?php }

/** Pilih gambar + miniatur. Nama field file = kunci di undangan_aksi.php. */
function ed_foto(string $nama, string $label, string $path): void
{ ?>
    <div class="ed-foto" data-foto="<?= e($nama) ?>" data-path="<?= e($path) ?>">
        <img class="ed-thumb" src="../<?= e($path) ?>" alt="">
        <label class="ed-field">
            <span class="ed-label"><?= e($label) ?> <small>(opsional)</small></span>
            <input type="file" name="<?= e($nama) ?>" accept="image/jpeg,image/png,image/webp">
            <small class="ed-ket">JPG/PNG/WebP, maks. 12 MB. Kosongkan kalau tidak ingin mengganti.</small>
        </label>
    </div>
<?php }

/** Jam mulai, jam selesai (opsional), zona waktu. */
function ed_jam(string $awalan, array $und): void
{ ?>
    <div class="ed-baris ed-baris-jam">
        <label class="ed-field">
            <span class="ed-label">Mulai</span>
            <input type="time" name="<?= $awalan ?>_mulai" value="<?= e($und[$awalan . '_mulai']) ?>" required>
        </label>
        <label class="ed-field">
            <span class="ed-label">Selesai <small>(kosong = &ldquo;Selesai&rdquo;)</small></span>
            <input type="time" name="<?= $awalan ?>_selesai" value="<?= e($und[$awalan . '_selesai']) ?>">
        </label>
        <label class="ed-field">
            <span class="ed-label">Zona</span>
            <select name="<?= $awalan ?>_zona">
                <?php foreach (UNDANGAN_ZONA as $z): ?>
                    <option value="<?= $z ?>"<?= $und[$awalan . '_zona'] === $z ? ' selected' : '' ?>><?= $z ?></option>
                <?php endforeach; ?>
            </select>
        </label>
    </div>
<?php }

/** Satu mempelai: foto, nama lengkap, ayah, ibu. */
function ed_mempelai(string $jenis, string $judul, array $m): void
{ ?>
    <fieldset class="ed-sub">
        <legend><?= e($judul) ?></legend>
        <?php ed_foto($jenis . '_foto', 'Foto', $m['foto']); ?>
        <?php ed_teks($jenis . '_nama', 'Nama lengkap', $m['nama'], 100, true, 'Termasuk gelar, mis. Malik Pratama, S.T'); ?>
        <div class="ed-baris">
            <?php ed_teks($jenis . '_ayah', 'Nama ayah', $m['ayah'], 100); ?>
            <?php ed_teks($jenis . '_ibu', 'Nama ibu', $m['ibu'], 100); ?>
        </div>
    </fieldset>
<?php } ?>

<div class="editor" id="editor-undangan">

    <form class="editor-form" id="form-undangan" action="undangan_aksi.php" method="post" enctype="multipart/form-data">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">

        <p class="petunjuk">Ubah isi undangan di sini, hasilnya langsung tampil di pratinjau. Klik bagian pada pratinjau untuk membuka pengaturannya. Perubahan baru dilihat tamu setelah menekan <strong>Simpan perubahan</strong>.</p>

        <!-- 1. Cover -->
        <details class="bagian" name="bagian-undangan" data-bagian="cover" data-sasaran="cover-image" open>
            <summary><span class="no">1</span> Halaman Cover</summary>
            <div class="bagian-isi">
                <?php ed_teks('cover_judul', 'Judul', $und['cover_judul'], 60, true); ?>
                <div class="ed-baris">
                    <?php ed_teks('pria_panggilan', 'Nama panggilan pria', $mp['pria']['panggilan'], 30, true); ?>
                    <?php ed_teks('wanita_panggilan', 'Nama panggilan wanita', $mp['wanita']['panggilan'], 30, true); ?>
                </div>
                <?php ed_tanggal($und['tanggal'], 'Tampil di cover dan halaman Tempat & Waktu (hari ditentukan otomatis).'); ?>
                <?php ed_foto('cover_foto', 'Foto background cover', $und['cover_foto']); ?>
                <p class="ed-catatan">Nama tamu di cover diambil otomatis dari menu <a href="index.php?panel=tamu">Tamu Undangan</a>.</p>
            </div>
        </details>

        <!-- 2. Kata pengantar -->
        <details class="bagian" name="bagian-undangan" data-bagian="pengantar" data-sasaran="ucapan-tujuan">
            <summary><span class="no">2</span> Kata Pengantar</summary>
            <div class="bagian-isi">
                <?php ed_area('kata_pengantar', 'Kata pengantar', $und['kata_pengantar'], 800, 6); ?>
            </div>
        </details>

        <!-- 3. Data diri mempelai -->
        <details class="bagian" name="bagian-undangan" data-bagian="mempelai" data-sasaran="informasi-pengundang">
            <summary><span class="no">3</span> Data Diri Mempelai</summary>
            <div class="bagian-isi">
                <?php ed_teks('mempelai_judul', 'Judul', $und['mempelai_judul'], 40, true); ?>
                <?php ed_teks('mempelai_deskripsi', 'Deskripsi', $und['mempelai_deskripsi'], 150); ?>
                <?php ed_mempelai('pria', 'Mempelai pria', $mp['pria']); ?>
                <?php ed_mempelai('wanita', 'Mempelai wanita', $mp['wanita']); ?>
            </div>
        </details>

        <!-- 4. Tempat & waktu -->
        <details class="bagian" name="bagian-undangan" data-bagian="waktu" data-sasaran="waktu-acara">
            <summary><span class="no">4</span> Tempat &amp; Waktu</summary>
            <div class="bagian-isi">
                <?php ed_tanggal($und['tanggal'], 'Sama dengan tanggal di Cover; mengubah salah satunya mengubah keduanya.'); ?>

                <fieldset class="ed-sub" data-sasaran="acara-akad">
                    <legend>Akad nikah</legend>
                    <?php ed_jam('akad', $und); ?>
                    <?php ed_area('akad_deskripsi', 'Deskripsi', $und['akad_deskripsi'], 400, 4); ?>
                    <?php ed_foto('akad_foto', 'Foto akad nikah', $und['akad_foto']); ?>
                </fieldset>

                <fieldset class="ed-sub" data-sasaran="acara-resepsi">
                    <legend>Resepsi</legend>
                    <?php ed_jam('resepsi', $und); ?>
                    <?php ed_area('resepsi_deskripsi', 'Deskripsi', $und['resepsi_deskripsi'], 400, 4); ?>
                    <?php ed_foto('resepsi_foto', 'Foto resepsi', $und['resepsi_foto']); ?>
                </fieldset>
            </div>
        </details>

        <p class="ed-catatan">Bagian berikutnya (alamat, galeri, cerita kami, ucapan, RSVP) menyusul.</p>

        <div class="ed-bar">
            <span id="ed-status" class="ed-status" role="status" aria-live="polite">Belum ada perubahan.</span>
            <button type="submit" class="tombol utama" id="ed-simpan">Simpan perubahan</button>
        </div>
    </form>

    <aside class="pratinjau" id="pratinjau" aria-label="Pratinjau undangan">
        <div class="pv-bar">
            <strong>Pratinjau live</strong>
            <div class="pv-mode" role="group" aria-label="Ukuran layar pratinjau">
                <button type="button" data-mode="auto" aria-pressed="true" title="Mengikuti ukuran layar perangkat ini">Otomatis</button>
                <button type="button" data-mode="hp" aria-pressed="false">HP</button>
                <button type="button" data-mode="tablet" aria-pressed="false">Tablet</button>
                <button type="button" data-mode="desktop" aria-pressed="false">Desktop</button>
            </div>
            <button type="button" class="pv-lipat" id="pv-lipat" aria-expanded="true">Sembunyikan</button>
        </div>
        <div class="pv-layar" id="pv-layar">
            <div class="pv-kanvas" id="pv-kanvas">
                <iframe id="pv-frame" src="../index.php?pratinjau=1" title="Pratinjau undangan" loading="eager"></iframe>
            </div>
        </div>
    </aside>

</div>

<script src="../assets/js/editor-undangan.js" defer></script>

<div class="grid-pengantin">
    <section class="kartu galeri semua" id="galeri" aria-label="Galeri">

        <div class="kepala">
            <h3>Galeri <span class="hitung"><?= count($galeri) ?></span></h3>

            <form class="unggah" action="galeri_aksi.php" method="post" enctype="multipart/form-data">
                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="aksi" value="tambah">
                <input type="file" name="foto[]" accept="image/jpeg,image/png,image/webp" multiple required aria-label="Pilih foto galeri">
                <button type="submit" class="tombol utama">Tambah foto</button>
            </form>
        </div>

        <p class="petunjuk">Bisa pilih banyak foto sekaligus. Portrait, landscape, atau persegi semuanya boleh &mdash; undangan menyesuaikan sendiri.</p>

        <?php if ($galeri): ?>
            <ul class="kolase">
                <?php foreach ($galeri as $g): ?>
                    <li>
                        <img src="../<?= e($g['file']) ?>" alt="" loading="lazy"<?= $g['lebar'] > 0 ? ' width="' . (int) $g['lebar'] . '" height="' . (int) $g['tinggi'] . '"' : '' ?>>
                        <form action="galeri_aksi.php" method="post" onsubmit="return confirm('Hapus foto ini dari galeri?')">
                            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                            <input type="hidden" name="aksi" value="hapus">
                            <input type="hidden" name="id" value="<?= (int) $g['id'] ?>">
                            <button type="submit" aria-label="Hapus foto" title="Hapus foto">&times;</button>
                        </form>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <p class="kosong-galeri">Belum ada foto.</p>
        <?php endif; ?>

    </section>

</div>
