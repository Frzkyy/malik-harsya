<?php // Panel Pengaturan. Dipanggil dari dashboard/index.php. ?>

<section class="kartu">
    <?php if (is_superadmin()): ?>
        <p>Atur siapa saja yang bisa masuk sebagai admin.</p>
        <a class="tombol utama" href="kelola.php">Kelola admin</a>
    <?php else: ?>
        <p class="kosong">Hanya superadmin yang bisa mengatur admin.</p>
    <?php endif; ?>
</section>

<section class="kartu pengaturan-reset">
    <h2>Reset gambar</h2>
    <p>Mengembalikan foto pengantin dan seluruh galeri ke gambar bawaan proyek. Foto yang pernah diunggah akan dihapus. Nama, biodata pengantin, RSVP, dan akun admin tidak akan berubah.</p>

    <?php if (is_superadmin()): ?>
        <form action="pengaturan_aksi.php" method="post" onsubmit="return confirm('Yakin ingin mereset semua gambar? Foto pengantin dan seluruh foto galeri akan dikembalikan ke gambar bawaan.');">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="aksi" value="reset_gambar">
            <button type="submit" class="tombol btn-bahaya">Reset semua gambar</button>
        </form>
    <?php else: ?>
        <p class="kosong">Hanya superadmin yang bisa mereset gambar.</p>
    <?php endif; ?>
</section>
