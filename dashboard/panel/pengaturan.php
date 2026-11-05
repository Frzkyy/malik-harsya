<?php // Panel Pengaturan. Dipanggil dari dashboard/index.php. ?>

<section class="kartu">
    <?php if (is_superadmin()): ?>
        <p>Atur siapa saja yang bisa masuk sebagai admin.</p>
        <a class="tombol utama" href="kelola.php">Kelola admin</a>
    <?php else: ?>
        <p class="kosong">Hanya superadmin yang bisa mengatur admin.</p>
    <?php endif; ?>
</section>
