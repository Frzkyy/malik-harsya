<?php
// Panel Tamu Undangan: daftar, cari, tambah, detail (link + status RSVP), edit, hapus.
// Dipanggil dari dashboard/index.php.
require_once __DIR__ . '/../../includes/tamu.php';

$q = trim((string) ($_GET['q'] ?? ''));
$tampil_tambah = isset($_GET['tambah']);
$id_detail = (int) ($_GET['detail'] ?? 0);
$id_edit   = (int) ($_GET['edit'] ?? 0);

// Alamat dasar undangan, mis. http://localhost:8000 (folder dashboard/ satu tingkat di bawah root).
$skema = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$basis = $skema . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . rtrim(str_replace('\\', '/', dirname(dirname($_SERVER['SCRIPT_NAME']))), '/');

$daftar = [];
$aktif  = null;   // tamu untuk kartu detail / edit di atas daftar
try {
    $sql = 'SELECT t.id, t.nama, t.slug, t.created_at, r.status, r.jumlah, r.ucapan, r.created_at AS waktu_rsvp
            FROM tamu t
            LEFT JOIN rsvp r ON r.id = (SELECT MAX(id) FROM rsvp WHERE tamu_id = t.id)';
    $param = [];
    if ($q !== '') {
        $sql .= " WHERE t.nama LIKE ? ESCAPE '\\'";
        $param[] = '%' . addcslashes($q, '%_\\') . '%';
    }
    $stmt = db()->prepare($sql . ' ORDER BY t.id DESC');
    $stmt->execute($param);
    $daftar = $stmt->fetchAll();

    $cari_id = $id_edit ?: $id_detail;
    if ($cari_id) {
        $s = db()->prepare(
            'SELECT t.id, t.nama, t.slug, r.status, r.jumlah, r.ucapan, r.created_at AS waktu_rsvp
             FROM tamu t LEFT JOIN rsvp r ON r.id = (SELECT MAX(id) FROM rsvp WHERE tamu_id = t.id)
             WHERE t.id = ?'
        );
        $s->execute([$cari_id]);
        $aktif = $s->fetch() ?: null;
    }
} catch (Throwable $ex) {
    error_log('Panel tamu gagal memuat data: ' . $ex->getMessage());
}

?>

<div class="bar-tamu kartu">
    <form class="cari" action="index.php" method="get" role="search">
        <input type="hidden" name="panel" value="tamu">
        <input type="search" name="q" value="<?= e($q) ?>" placeholder="Cari Tamu" aria-label="Cari tamu" maxlength="100">
        <button type="submit" class="tombol-biru">Cari</button>
        <?php if ($q !== ''): ?><a class="reset-cari" href="index.php?panel=tamu">Reset</a><?php endif; ?>
    </form>
    <a class="tombol-biru" href="index.php?panel=tamu&amp;tambah=1">+ Tamu Undangan</a>
</div>

<?php if ($tampil_tambah): ?>
    <section class="kartu form-tamu" aria-labelledby="h-tambah">
        <h2 id="h-tambah">Tambah Tamu</h2>
        <form action="tamu_aksi.php" method="post">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="aksi" value="tambah">
            <label>Nama tamu
                <input type="text" name="nama" maxlength="100" required autofocus placeholder="mis. Said Fairuz Zacky">
            </label>
            <label>Link <small>(opsional &mdash; kosongkan agar dibuat otomatis dari nama)</small>
                <input type="text" name="slug" maxlength="60" pattern="[a-z0-9][a-z0-9\-]*" placeholder="mis. said-fairuz">
            </label>
            <div class="aksi">
                <button type="submit" class="tombol-biru">Simpan</button>
                <a class="tombol" href="index.php?panel=tamu">Batal</a>
            </div>
        </form>
    </section>
<?php endif; ?>

<?php if ($aktif && $id_edit): ?>
    <section class="kartu form-tamu" aria-labelledby="h-edit">
        <h2 id="h-edit">Edit Tamu</h2>
        <form action="tamu_aksi.php" method="post">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="aksi" value="edit">
            <input type="hidden" name="id" value="<?= (int) $aktif['id'] ?>">
            <label>Nama tamu
                <input type="text" name="nama" value="<?= e($aktif['nama']) ?>" maxlength="100" required autofocus>
            </label>
            <label>Link <small>(kalau diubah, link lama berhenti berlaku)</small>
                <input type="text" name="slug" value="<?= e($aktif['slug']) ?>" maxlength="60" pattern="[a-z0-9][a-z0-9\-]*">
            </label>
            <div class="aksi">
                <button type="submit" class="tombol-biru">Simpan</button>
                <a class="tombol" href="index.php?panel=tamu&amp;detail=<?= (int) $aktif['id'] ?>">Batal</a>
            </div>
        </form>
    </section>
<?php elseif ($aktif && $id_detail): ?>
    <section class="kartu detail-tamu" aria-labelledby="h-detail">
        <div class="kepala">
            <h2 id="h-detail"><?= e($aktif['nama']) ?></h2>
        </div>

        <dl>
            <dt>Link undangan</dt>
            <dd>
                <span class="link-salin">
                    <input type="text" readonly value="<?= e($basis . '/tamu?id=' . (int) $aktif['id']) ?>" aria-label="Link undangan" onfocus="this.select()">
                    <button type="button" class="tombol salin">Salin</button>
                </span>
                <small>Atau: <code><?= e($basis . '/' . $aktif['slug']) ?></code></small>
            </dd>
            <dt>Status</dt>
            <dd><?= badge_status($aktif['status']) ?></dd>
            <?php if ($aktif['status']): ?>
                <dt>Jumlah hadir</dt> <dd><?= (int) $aktif['jumlah'] ?> orang</dd>
                <dt>Dijawab</dt>      <dd><?= e(waktu_wib($aktif['waktu_rsvp'])) ?></dd>
                <dt>Ucapan</dt>       <dd><?= $aktif['ucapan'] ? nl2br(e($aktif['ucapan'])) : '<span class="kosong">-</span>' ?></dd>
            <?php endif; ?>
        </dl>

        <div class="aksi">
            <a class="tombol-oranye" href="index.php?panel=tamu&amp;edit=<?= (int) $aktif['id'] ?>">Edit</a>
            <form action="tamu_aksi.php" method="post" onsubmit="return confirm('Hapus tamu ini? RSVP yang sudah masuk tetap tersimpan.')">
                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="aksi" value="hapus">
                <input type="hidden" name="id" value="<?= (int) $aktif['id'] ?>">
                <button type="submit" class="btn-bahaya">Hapus</button>
            </form>
            <a class="tombol" href="index.php?panel=tamu">Tutup</a>
        </div>
    </section>
<?php endif; ?>

<?php if (!$daftar): ?>
    <section class="kartu">
        <p class="kosong-galeri">
            <?= $q !== '' ? 'Tidak ada tamu yang cocok dengan "' . e($q) . '".' : 'Belum ada tamu. Klik "+ Tamu Undangan" untuk menambah.' ?>
        </p>
    </section>
<?php else: ?>
    <div class="grid-tamu">
        <?php foreach ($daftar as $t): ?>
            <article class="kartu kartu-tamu">
                <h3><?= e($t['nama']) ?></h3>
                <div class="ucapan-tamu<?= $t['ucapan'] ? '' : ' kosong-ucapan' ?>">
                    <?= $t['ucapan'] ? e($t['ucapan']) : 'Belum ada ucapan' ?>
                </div>
                <div class="aksi-tamu">
                    <a class="tombol-biru" href="index.php?panel=tamu&amp;detail=<?= (int) $t['id'] ?><?= $q !== '' ? '&amp;q=' . e(rawurlencode($q)) : '' ?>#h-detail">Detail</a>
                    <a class="tombol-oranye" href="index.php?panel=tamu&amp;edit=<?= (int) $t['id'] ?>#h-edit">Edit</a>
                    <?= badge_status($t['status']) ?>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<script>
document.querySelectorAll('.link-salin .salin').forEach(function (b) {
    b.addEventListener('click', function () {
        var i = b.parentNode.querySelector('input');
        i.select();
        (navigator.clipboard ? navigator.clipboard.writeText(i.value) : Promise.reject())
            .catch(function () { document.execCommand('copy'); })
            .then(function () { b.textContent = 'Tersalin'; setTimeout(function () { b.textContent = 'Salin'; }, 1500); });
    });
});
</script>
