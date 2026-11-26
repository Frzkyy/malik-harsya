<?php
// Panel Undangan (dulu "Pengantin"): editor seluruh isi undangan + pratinjau live.
// Dipanggil dari dashboard/index.php. Logika editor: assets/js/editor-undangan.js
// (+ editor-galeri.js untuk galeri drag, editor-cerita.js untuk daftar cerita),
// penyimpanan: dashboard/undangan_aksi.php, nilai bawaan & validasi: includes/undangan.php.
//
// Bagian: 1 Cover, 2 Kata pengantar, 3 Mempelai, 4 Tempat & waktu, 5 Alamat, 6 Galeri,
//         7 Cerita kami, 8 Ucapan, 9 RSVP + Ucapan & Doa, 10 Gift (QRIS & SeaBank), 11 Musik.

require_once __DIR__ . '/../../includes/undangan.php';
require_once __DIR__ . '/../../includes/galeri.php';
require_once __DIR__ . '/../../includes/cerita.php';

$pengantin = [];
$galeri    = [];
$cerita    = [];
$und       = UNDANGAN_BAWAAN;

try {
    $pengantin = ambil_pengantin();
    $und = ambil_undangan();
    $galeri = ambil_galeri();
    $cerita = ambil_cerita();
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

/** <input type=url> (opsional) */
function ed_url(string $nama, string $label, string $nilai, string $ket = ''): void
{ ?>
    <label class="ed-field">
        <span class="ed-label"><?= e($label) ?> <small>(opsional)</small></span>
        <input type="url" name="<?= e($nama) ?>" value="<?= e($nilai) ?>" maxlength="500" pattern="https?://.+" placeholder="https://maps.app.goo.gl/..." autocomplete="off" inputmode="url">
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
function ed_foto(string $nama, string $label, string $path, bool $bisa_hapus = false): void
{ ?>
    <div class="ed-foto" data-foto="<?= e($nama) ?>" data-path="<?= e($path) ?>">
        <img class="ed-thumb" src="<?= $path !== '' ? '../' . e($path) : '' ?>" alt=""<?= $path === '' ? ' hidden' : '' ?>>
        <div class="ed-foto-isi">
            <label class="ed-field">
                <span class="ed-label"><?= e($label) ?> <small>(opsional)</small></span>
                <input type="file" name="<?= e($nama) ?>" accept="image/jpeg,image/png,image/webp">
                <small class="ed-ket">JPG/PNG/WebP, maks. 12 MB. Kosongkan kalau tidak ingin mengganti.</small>
            </label>
            <?php if ($bisa_hapus): ?>
                <span class="ed-hapus"<?= $path === '' ? ' hidden' : '' ?>>
                    <input type="hidden" name="<?= e($nama) ?>_hapus" value="0">
                    <label class="ed-cek"><input type="checkbox" name="<?= e($nama) ?>_hapus" value="1"> Hapus gambar ini</label>
                </span>
            <?php endif; ?>
        </div>
    </div>
<?php }

/** Saklar nyala/mati (checkbox + hidden "0" supaya nilai tidak hilang saat tidak dicentang). */
function ed_saklar(string $nama, string $label, string $nilai, string $ket = ''): void
{ ?>
    <div class="ed-field ed-saklar">
        <input type="hidden" name="<?= e($nama) ?>" value="0">
        <label class="ed-cek ed-cek-besar">
            <input type="checkbox" name="<?= e($nama) ?>" value="1"<?= $nilai === '1' ? ' checked' : '' ?>>
            <span><?= e($label) ?></span>
        </label>
        <?php if ($ket !== ''): ?><small class="ed-ket"><?= e($ket) ?></small><?php endif; ?>
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

        <!-- 5. Alamat -->
        <details class="bagian" name="bagian-undangan" data-bagian="alamat" data-sasaran="lokasi-acara">
            <summary><span class="no">5</span> Alamat</summary>
            <div class="bagian-isi">
                <?php ed_teks('alamat_judul', 'Judul', $und['alamat_judul'], 40, true); ?>
                <?php ed_teks('alamat_lokasi', 'Lokasi', $und['alamat_lokasi'], 100, true, 'Nama tempat, mis. Hotel Aryaduta Pekanbaru'); ?>
                <?php ed_teks('alamat_jalan', 'Nama jalan', $und['alamat_jalan'], 200); ?>
                <?php ed_area('alamat_deskripsi', 'Deskripsi', $und['alamat_deskripsi'], 600, 5); ?>
                <?php ed_url('alamat_maps', 'Link Google Maps', $und['alamat_maps'], 'Kosongkan untuk membuat link pencarian otomatis dari lokasi + nama jalan.'); ?>
                <?php ed_teks('alamat_tombol', 'Teks tombol peta', $und['alamat_tombol'], 30, true); ?>
                <?php ed_foto('alamat_foto', 'Gambar lokasi', $und['alamat_foto']); ?>
            </div>
        </details>

        <!-- 6. Galeri -->
        <details class="bagian" name="bagian-undangan" data-bagian="galeri" data-sasaran="galeri">
            <summary><span class="no">6</span> Galeri</summary>
            <div class="bagian-isi">
                <?php ed_teks('galeri_judul', 'Judul', $und['galeri_judul'], 40, true); ?>

                <div class="gl" id="gl" data-kolom="<?= TATA_KOLOM ?>" data-baris-maks="<?= TATA_BARIS_MAKS ?>"
                     data-maks-foto="<?= TATA_MAKS_FOTO ?>" data-maks-baru="<?= TATA_MAKS_BARU ?>"
                     data-awal="<?= e(json_encode(array_map(static fn (array $g): array => [
                         'k' => 'g' . $g['id'], 'src' => $g['file'], 'x' => $g['x'], 'y' => $g['y'], 'w' => $g['w'], 'h' => $g['h'],
                     ], $galeri), JSON_UNESCAPED_SLASHES)) ?>">

                    <div class="gl-alat">
                        <label class="tombol gl-tambah">
                            + Tambah foto
                            <input type="file" id="gl-berkas" accept="image/jpeg,image/png,image/webp" multiple hidden>
                        </label>
                        <button type="button" class="tombol" id="gl-susun" title="Semua foto diberi ukuran sama, 3 per baris">Susun otomatis</button>
                        <button type="button" class="tombol" id="gl-rapat" title="Geser semua foto ke atas untuk menutup ruang kosong">Rapatkan</button>
                        <span class="gl-hitung" id="gl-hitung"></span>
                    </div>

                    <p class="gl-pesan" id="gl-pesan" role="status" aria-live="polite" hidden></p>

                    <div class="gl-kanvas" id="gl-kanvas" aria-label="Tata letak galeri"></div>

                    <div class="gl-panel" id="gl-panel" hidden>
                        <div class="gl-panel-kepala">
                            <strong id="gl-nama">Foto</strong>
                            <button type="button" class="btn-bahaya" id="gl-hapus">Hapus foto</button>
                        </div>
                        <div class="gl-ukur">
                            <label class="ed-field"><span class="ed-label">Lebar <small>(kolom)</small></span>
                                <input type="number" form="tanpa-form" id="gl-w" min="1" max="<?= TATA_KOLOM ?>" step="1" inputmode="numeric"></label>
                            <label class="ed-field"><span class="ed-label">Tinggi <small>(baris)</small></span>
                                <input type="number" form="tanpa-form" id="gl-h" min="1" max="<?= TATA_BARIS_MAKS ?>" step="1" inputmode="numeric"></label>
                            <label class="ed-field"><span class="ed-label">Mulai dari kolom</span>
                                <input type="number" form="tanpa-form" id="gl-x" min="1" max="<?= TATA_KOLOM ?>" step="1" inputmode="numeric"></label>
                            <label class="ed-field"><span class="ed-label">Mulai dari baris</span>
                                <input type="number" form="tanpa-form" id="gl-y" min="1" max="<?= TATA_BARIS_MAKS ?>" step="1" inputmode="numeric"></label>
                        </div>
                        <small class="ed-ket" id="gl-info"></small>
                    </div>

                    <p class="ed-catatan">Seret foto untuk memindahkan, seret sudut kanan bawahnya untuk mengubah ukuran, atau ketik ukurannya di bawah foto yang dipilih.
                        Satu kolom = 1/12 lebar galeri, satu baris setinggi satu kolom, sehingga ukurannya ikut menyesuaikan layar apa pun.
                        Dengan keyboard: tombol panah memindah, Shift + panah mengubah ukuran, Delete menghapus.</p>
                </div>
            </div>
        </details>

        <!-- 7. Cerita kami -->
        <details class="bagian" name="bagian-undangan" data-bagian="cerita" data-sasaran="story">
            <summary><span class="no">7</span> Cerita Kami</summary>
            <div class="bagian-isi">
                <?php ed_teks('cerita_judul', 'Judul', $und['cerita_judul'], 40, true); ?>
                <?php ed_area('cerita_deskripsi', 'Deskripsi awal', $und['cerita_deskripsi'], 400, 3); ?>

                <div class="cr" id="cr" data-min="<?= CERITA_MIN ?>" data-maks="<?= CERITA_MAKS ?>"
                     data-cadangan="<?= e(json_encode(CERITA_FOTO_CADANGAN)) ?>"
                     data-awal="<?= e(json_encode(array_map(static fn (array $c): array => [
                         'k' => 'c' . $c['id'], 'judul' => $c['judul'], 'deskripsi' => $c['deskripsi'], 'foto' => $c['foto'],
                     ], $cerita), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) ?>">
                    <div class="cr-daftar" id="cr-daftar"></div>
                    <div class="cr-bawah">
                        <button type="button" class="tombol" id="cr-tambah">+ Tambah cerita</button>
                        <span class="gl-hitung" id="cr-hitung"></span>
                    </div>
                    <p class="ed-catatan">Minimal <?= CERITA_MIN ?> dan maksimal <?= CERITA_MAKS ?> cerita. Posisi foto kiri/kanan bergantian otomatis. Cerita baru tanpa foto memakai foto contoh sampai Anda menggantinya.</p>
                </div>
            </div>
        </details>

        <!-- 8. Ucapan -->
        <details class="bagian" name="bagian-undangan" data-bagian="ucapan" data-sasaran="ucapan">
            <summary><span class="no">8</span> Ucapan</summary>
            <div class="bagian-isi">
                <?php ed_teks('ucapan_judul', 'Judul', $und['ucapan_judul'], 40, true); ?>
                <?php ed_area('ucapan_teks', 'Ucapan', $und['ucapan_teks'], 900, 7); ?>
            </div>
        </details>

        <!-- 9. RSVP + Ucapan & Doa -->
        <details class="bagian" name="bagian-undangan" data-bagian="rsvp" data-sasaran="rsvp">
            <summary><span class="no">9</span> RSVP, Ucapan &amp; Doa</summary>
            <div class="bagian-isi">
                <?php ed_teks('rsvp_judul', 'Judul RSVP', $und['rsvp_judul'], 40, true); ?>
                <?php ed_area('rsvp_deskripsi', 'Deskripsi awal RSVP', $und['rsvp_deskripsi'], 400, 3); ?>

                <fieldset class="ed-sub" data-sasaran="daftar-ucapan">
                    <legend>Daftar ucapan &amp; doa</legend>
                    <?php ed_teks('ucapan_doa_judul', 'Judul', $und['ucapan_doa_judul'], 40, true); ?>
                    <?php ed_teks('ucapan_kosong', 'Teks saat belum ada ucapan', $und['ucapan_kosong'], 200); ?>
                    <p class="ed-catatan">Isi ucapan dari tamu muncul otomatis dan dikelola lewat menu Daftar Hadir.</p>
                </fieldset>
            </div>
        </details>

        <!-- 10. Gift -->
        <details class="bagian" name="bagian-undangan" data-bagian="gift" data-sasaran="gift">
            <summary><span class="no">10</span> Gift (QRIS &amp; SeaBank)</summary>
            <div class="bagian-isi">
                <?php ed_teks('gift_judul', 'Judul', $und['gift_judul'], 40, true); ?>
                <?php ed_area('gift_deskripsi', 'Deskripsi', $und['gift_deskripsi'], 400, 3); ?>

                <fieldset class="ed-sub" data-sasaran="gift-qris">
                    <legend>QRIS</legend>
                    <?php ed_saklar('gift_qris_aktif', 'Tampilkan kartu QRIS', $und['gift_qris_aktif'], 'Kartu baru tampil di undangan setelah gambar QRIS diunggah.'); ?>
                    <?php ed_teks('gift_qris_judul', 'Judul kartu', $und['gift_qris_judul'], 30, true); ?>
                    <?php ed_teks('gift_qris_nama', 'Nama pemilik / merchant', $und['gift_qris_nama'], 100); ?>
                    <?php ed_foto('gift_qris_foto', 'Gambar QRIS', (string) $und['gift_qris_foto'], true); ?>
                </fieldset>

                <fieldset class="ed-sub" data-sasaran="gift-bank">
                    <legend>Transfer bank</legend>
                    <?php ed_saklar('gift_bank_aktif', 'Tampilkan kartu transfer', $und['gift_bank_aktif']); ?>
                    <?php ed_teks('gift_bank_judul', 'Nama bank', $und['gift_bank_judul'], 30, true, 'Mis. SeaBank'); ?>
                    <?php ed_teks('gift_bank_nama', 'Nama pemilik rekening', $und['gift_bank_nama'], 100); ?>
                    <label class="ed-field">
                        <span class="ed-label">Nomor rekening</span>
                        <input type="text" name="gift_bank_nomor" value="<?= e($und['gift_bank_nomor']) ?>" maxlength="30" inputmode="numeric" autocomplete="off">
                        <small class="ed-ket">Angka saja (boleh dengan spasi/tanda hubung). Tombol &ldquo;Salin&rdquo; di undangan menyalin angkanya saja.</small>
                    </label>
                </fieldset>
            </div>
        </details>

        <!-- 11. Musik -->
        <details class="bagian" name="bagian-undangan" data-bagian="musik" data-sasaran="cover-image">
            <summary><span class="no">11</span> Musik Latar</summary>
            <div class="bagian-isi" id="musik-editor" data-path="<?= e((string) $und['musik_file']) ?>">
                <?php ed_saklar('musik_aktif', 'Aktifkan musik', $und['musik_aktif'], 'Menampilkan tombol musik di halaman masuk tamu dan tombol musik melayang di undangan. Perlu file musik.'); ?>
                <?php ed_teks('musik_judul', 'Judul lagu', $und['musik_judul'], 80, false, 'Tampil di samping tombol musik pada halaman masuk tamu.'); ?>

                <div class="ed-field ed-musik">
                    <span class="ed-label">File musik <small>(opsional bila sudah ada)</small></span>
                    <audio id="musik-dengar" controls preload="none"<?= $und['musik_file'] === '' ? ' hidden' : ' src="../' . e((string) $und['musik_file']) . '"' ?>></audio>
                    <p class="ed-ket" id="musik-kosong"<?= $und['musik_file'] === '' ? '' : ' hidden' ?>>Belum ada musik diunggah.</p>
                    <input type="file" name="musik_file" id="musik-berkas" accept=".mp3,.ogg,.m4a,.wav,audio/mpeg,audio/ogg,audio/mp4,audio/wav">
                    <small class="ed-ket" id="musik-ket">MP3/OGG/M4A/WAV, maks. 10 MB. Kosongkan kalau tidak ingin mengganti.</small>
                    <span class="ed-hapus" id="musik-hapus-wadah"<?= $und['musik_file'] === '' ? ' hidden' : '' ?>>
                        <input type="hidden" name="musik_file_hapus" value="0">
                        <label class="ed-cek"><input type="checkbox" name="musik_file_hapus" value="1"> Hapus musik ini</label>
                    </span>
                </div>

                <label class="ed-field">
                    <span class="ed-label">Volume awal <small>(5-100)</small></span>
                    <input type="number" name="musik_volume" value="<?= musik_volume($und) ?>" min="5" max="100" step="5" inputmode="numeric" required>
                </label>
                <p class="ed-catatan">Musik diputar otomatis saat tamu menekan <em>Masuk</em>, <em>Buka Undangan</em>, atau <em>Lihat sebagai tamu</em> (selama tombol musik di halaman masuk dalam keadaan nyala). Browser hanya mengizinkan suara otomatis setelah ada klik, karena itu tombol itu diletakkan di sana. Tombol musik melayang membiarkan tamu menyalakan/mematikannya kapan saja. Di pratinjau, musik tidak diputar.</p>
            </div>
        </details>

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
<script src="../assets/js/editor-galeri.js" defer></script>
<script src="../assets/js/editor-cerita.js" defer></script>
<script src="../assets/js/editor-gift-musik.js" defer></script>
