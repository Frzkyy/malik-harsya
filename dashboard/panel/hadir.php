<?php
// Panel Daftar Hadir (hanya lihat): siapa saja yang sudah menjawab RSVP dan siapa yang belum.
// Dipanggil dari dashboard/index.php. Data tamu dikelola di panel Tamu Undangan.
require_once __DIR__ . '/../../includes/tamu.php';

$filter = (string) ($_GET['status'] ?? '');
if (!in_array($filter, ['hadir', 'ragu', 'tidak', 'belum'], true)) $filter = '';

$hari  = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
$bulan = [1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];

function waktu_hadir(?string $utc, array $hari, array $bulan): string
{
    if (!$utc) return '-';
    try {
        $d = new DateTime($utc, new DateTimeZone('UTC'));
        $d->setTimezone(new DateTimeZone('Asia/Jakarta'));
        return $d->format('H:i') . ' ' . $hari[(int) $d->format('w')] . ' '
            . $d->format('j') . ' ' . $bulan[(int) $d->format('n')] . ' ' . $d->format('Y');
    } catch (Throwable $ex) {
        return '-';
    }
}

$baris = [];
try {
    // Setiap tamu dengan jawaban terakhirnya, ditambah RSVP lama yang tidak terhubung ke tamu mana pun.
    $baris = db()->query(
        "SELECT t.nama AS nama, r.status AS status, r.jumlah AS jumlah, r.created_at AS waktu
           FROM tamu t
           LEFT JOIN rsvp r ON r.id = (SELECT MAX(id) FROM rsvp WHERE tamu_id = t.id)
         UNION ALL
         SELECT r.nama, r.status, r.jumlah, r.created_at
           FROM rsvp r WHERE r.tamu_id IS NULL"
    )->fetchAll();
} catch (Throwable $ex) {
    error_log('Panel hadir gagal memuat data: ' . $ex->getMessage());
}

// Rekap (dari semua baris, bukan hanya yang lolos filter).
$rekap = ['hadir' => 0, 'ragu' => 0, 'tidak' => 0, 'belum' => 0];
$orang_hadir = 0;
foreach ($baris as $b) {
    $k = $b['status'] ?: 'belum';
    $rekap[$k]++;
    if ($b['status'] === 'hadir') $orang_hadir += (int) $b['jumlah'];
}

// Urut: yang sudah menjawab (terbaru dulu), lalu yang belum menjawab (A-Z).
usort($baris, static function (array $a, array $b): int {
    $ja = $a['status'] !== null; $jb = $b['status'] !== null;
    if ($ja !== $jb) return $ja ? -1 : 1;
    if ($ja) return strcmp((string) $b['waktu'], (string) $a['waktu']);
    return strcasecmp($a['nama'], $b['nama']);
});

if ($filter !== '') {
    $baris = array_values(array_filter($baris, static fn (array $b): bool => ($b['status'] ?: 'belum') === $filter));
}

$opsi = ['' => 'Semua', 'hadir' => 'Hadir', 'ragu' => 'Mungkin Hadir', 'tidak' => 'Berhalangan Hadir', 'belum' => 'Belum Menjawab'];
?>

<section class="kartu rekap-hadir" aria-label="Ringkasan">
    <ul class="stat">
        <li><strong><?= $rekap['hadir'] ?></strong> hadir <small>(<?= $orang_hadir ?> orang)</small></li>
        <li><strong><?= $rekap['ragu'] ?></strong> mungkin hadir</li>
        <li><strong><?= $rekap['tidak'] ?></strong> berhalangan</li>
        <li><strong><?= $rekap['belum'] ?></strong> belum menjawab</li>
    </ul>
</section>

<section class="kartu tabel-hadir">
    <?php if (!$baris): ?>
        <p class="kosong-galeri">
            <?= $filter !== '' ? 'Tidak ada tamu dengan status "' . e($opsi[$filter]) . '".' : 'Belum ada tamu. Tambahkan dulu di panel Tamu Undangan.' ?>
        </p>
    <?php else: ?>
        <div class="gulung">
            <table>
                <thead>
                    <tr>
                        <th scope="col" class="no">No</th>
                        <th scope="col">Nama</th>
                        <th scope="col">Kehadiran</th>
                        <th scope="col">Waktu</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($baris as $i => $b): ?>
                        <tr>
                            <td class="no"><?= $i + 1 ?>.</td>
                            <td><?= e($b['nama']) ?></td>
                            <td class="tengah"><?= badge_status($b['status']) ?></td>
                            <td class="tengah"><?= e(waktu_hadir($b['waktu'], $hari, $bulan)) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<form class="filter-hadir" action="index.php" method="get">
    <input type="hidden" name="panel" value="hadir">
    <label for="f-status">Tampilkan</label>
    <select id="f-status" name="status">
        <?php foreach ($opsi as $v => $l): ?>
            <option value="<?= e($v) ?>"<?= $v === $filter ? ' selected' : '' ?>><?= e($l) ?></option>
        <?php endforeach; ?>
    </select>
    <button type="submit" class="tombol-ungu">Filter</button>
    <?php if ($filter !== ''): ?><a class="reset-cari" href="index.php?panel=hadir">Reset</a><?php endif; ?>
</form>
