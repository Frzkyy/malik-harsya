<?php
// Panel Pengantin: data mempelai + galeri. Dipanggil dari dashboard/index.php.

$pengantin = [];
$galeri    = [];

try {
    $pengantin = ambil_pengantin();
    $galeri = db()->query('SELECT id, file, lebar, tinggi FROM galeri ORDER BY id')->fetchAll();
} catch (Throwable $ex) {
    error_log('Panel pengantin gagal memuat data: ' . $ex->getMessage());
}

$edit = (string) ($_GET['edit'] ?? '');
if (!in_array($edit, ['pria', 'wanita'], true)) $edit = '';

$judul_jenis = ['pria' => 'Pengantin Pria', 'wanita' => 'Pengantin Wanita'];
?>
<div class="grid-pengantin">
    <?php foreach ($judul_jenis as $jenis => $judul):
        $p = $pengantin[$jenis] ?? null;
        if (!$p) continue;
        $mengedit = $edit === $jenis;
    ?>
        <section class="kartu profil" id="profil-<?= $jenis ?>" aria-labelledby="h-<?= $jenis ?>">

            <div class="foto">
                <img src="../<?= e($p['foto']) ?>" alt="Foto <?= e($judul) ?>"<?= $p['foto_lebar'] > 0 ? ' width="' . (int) $p['foto_lebar'] . '" height="' . (int) $p['foto_tinggi'] . '"' : '' ?>>
            </div>

            <?php if (!$mengedit): ?>

                <div class="data">
                    <div class="kepala">
                        <h2 id="h-<?= $jenis ?>"><?= e($judul) ?></h2>
                        <a class="tombol" href="index.php?panel=pengantin&amp;edit=<?= $jenis ?>#profil-<?= $jenis ?>">Edit</a>
                    </div>
                    <dl>
                        <dt>Nama</dt>   <dd><?= e($p['nama']) ?: '<span class="kosong">-</span>' ?></dd>
                        <dt>Panggilan</dt> <dd><?= e(nama_panggilan($p)) ?: '<span class="kosong">-</span>' ?></dd>
                        <dt>Ibu</dt>    <dd><?= e($p['ibu']) ?: '<span class="kosong">-</span>' ?></dd>
                        <dt>Ayah</dt>   <dd><?= e($p['ayah']) ?: '<span class="kosong">-</span>' ?></dd>
                        <dt>Alamat</dt> <dd><?= e($p['alamat']) ?: '<span class="kosong">-</span>' ?></dd>
                    </dl>
                </div>

            <?php else: ?>

                <form class="data" action="pengantin_aksi.php" method="post" enctype="multipart/form-data">
                    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="aksi" value="simpan">
                    <input type="hidden" name="jenis" value="<?= $jenis ?>">

                    <div class="kepala">
                        <h2 id="h-<?= $jenis ?>"><?= e($judul) ?></h2>
                    </div>

                    <label>Nama
                        <input type="text" name="nama" value="<?= e($p['nama']) ?>" maxlength="100" required autofocus>
                    </label>
                    <label>Nama panggilan <small>(tampil di cover undangan)</small>
                        <input type="text" name="panggilan" value="<?= e(nama_panggilan($p)) ?>" maxlength="30" required>
                    </label>
                    <label>Ibu
                        <input type="text" name="ibu" value="<?= e($p['ibu']) ?>" maxlength="100">
                    </label>
                    <label>Ayah
                        <input type="text" name="ayah" value="<?= e($p['ayah']) ?>" maxlength="100">
                    </label>
                    <label>Alamat
                        <textarea name="alamat" rows="3" maxlength="300"><?= e($p['alamat']) ?></textarea>
                    </label>
                    <label>Ganti foto <small>(opsional · JPG/PNG/WebP, maks. 12 MB · rasio bebas)</small>
                        <input type="file" name="foto" accept="image/jpeg,image/png,image/webp">
                    </label>
                    <div class="aksi">
                        <button type="submit" class="tombol utama">Simpan</button>
                        <a class="tombol" href="index.php?panel=pengantin#profil-<?= $jenis ?>">Batal</a>
                    </div>
                </form>

            <?php endif; ?>
        </section>
    <?php endforeach; ?>

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
