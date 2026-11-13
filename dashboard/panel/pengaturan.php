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
    <h2>Reset semua</h2>
    <p>Mengembalikan <strong>seluruh isi landing page</strong> ke bawaan: tanggal, jam acara, alamat, kata pengantar, ucapan, nama &amp; biodata pengantin, cerita, galeri, dan semua foto. Foto yang pernah diunggah akan dihapus. Data RSVP, daftar tamu, dan akun admin <strong>tidak</strong> berubah. Tindakan ini tidak bisa dibatalkan.</p>

    <?php if (is_superadmin()): ?>
        <form action="pengaturan_aksi.php" method="post" onsubmit="return confirm('Yakin ingin mereset SEMUA isi landing page (teks, tanggal, foto, galeri, cerita, nama pengantin) ke bawaan? Tindakan ini tidak bisa dibatalkan.') && prompt('Ketik RESET untuk melanjutkan') === 'RESET';">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="aksi" value="reset_semua">
            <button type="submit" class="tombol btn-bahaya">Reset semua</button>
        </form>
    <?php else: ?>
        <p class="kosong">Hanya superadmin yang bisa mereset.</p>
    <?php endif; ?>
</section>

<section class="kartu pengaturan-reset">
    <h2>Reset gambar</h2>
    <p>Mengembalikan foto pengantin, foto cover, akad nikah, resepsi, lokasi, foto cerita, dan seluruh galeri (beserta tata letaknya) ke gambar bawaan proyek. Foto yang pernah diunggah akan dihapus. Teks undangan, judul &amp; deskripsi cerita, nama, biodata pengantin, RSVP, dan akun admin tidak akan berubah.</p>

    <?php if (is_superadmin()): ?>
        <form action="pengaturan_aksi.php" method="post" onsubmit="return confirm('Yakin ingin mereset semua gambar? Foto pengantin, cover, akad nikah, resepsi, lokasi, cerita, dan seluruh foto galeri akan dikembalikan ke gambar bawaan.');">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="aksi" value="reset_gambar">
            <button type="submit" class="tombol btn-bahaya">Reset semua gambar</button>
        </form>
    <?php else: ?>
        <p class="kosong">Hanya superadmin yang bisa mereset gambar.</p>
    <?php endif; ?>
</section>

<section class="kartu pengaturan-reset">
    <h2>Reset nama pengantin</h2>
    <p>Mengembalikan nama lengkap, nama panggilan, orang tua, dan alamat kedua pengantin ke bawaan (Malik &amp; Harsya). Foto, galeri, RSVP, dan akun admin tidak akan berubah.</p>

    <?php if (is_superadmin()): ?>
        <form action="pengaturan_aksi.php" method="post" onsubmit="return confirm('Yakin ingin mereset nama dan biodata pengantin ke Malik & Harsya?');">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="aksi" value="reset_nama">
            <button type="submit" class="tombol btn-bahaya">Reset nama pengantin</button>
        </form>
    <?php else: ?>
        <p class="kosong">Hanya superadmin yang bisa mereset nama pengantin.</p>
    <?php endif; ?>
</section>
