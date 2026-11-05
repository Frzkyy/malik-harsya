<?php
// Panel Daftar Hadir. Dipanggil dari dashboard/index.php.

$stat = ['total' => 0, 'hadir' => 0, 'tamu' => 0, 'ucapan' => 0];

try {
    $r = db()->query(
        "SELECT COUNT(*) AS total,
                COALESCE(SUM(status = 'hadir'), 0) AS hadir,
                COALESCE(SUM(jumlah), 0) AS tamu,
                COALESCE(SUM(ucapan IS NOT NULL AND ucapan <> ''), 0) AS ucapan
         FROM rsvp"
    )->fetch();
    $stat = array_map('intval', $r);
} catch (Throwable $ex) {
    error_log('Panel hadir gagal memuat data: ' . $ex->getMessage());
}
?>

<section class="kartu">
    <ul class="stat">
        <li><strong><?= $stat['total'] ?></strong> RSVP masuk</li>
        <li><strong><?= $stat['hadir'] ?></strong> konfirmasi hadir</li>
        <li><strong><?= $stat['tamu'] ?></strong> total tamu</li>
        <li><strong><?= $stat['ucapan'] ?></strong> ucapan</li>
    </ul>
    <p class="kosong">Daftar lengkap siapa yang hadir menyusul.</p>
</section>
