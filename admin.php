<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

require_admin();

$panel_list = [
    'pengantin' => 'Pengantin',
    'tamu'      => 'Tamu Undangan',
    'hadir'     => 'Daftar Hadir',
    'pengaturan' => 'Pengaturan',
];
$panel = (string) ($_GET['panel'] ?? 'pengantin');
if (!isset($panel_list[$panel])) $panel = 'pengantin';

$flash = $_SESSION['flash_pengantin'] ?? null;
unset($_SESSION['flash_pengantin']);

$pengantin = [];
$galeri    = ['pria' => [], 'wanita' => []];
$stat      = ['total' => 0, 'hadir' => 0, 'tamu' => 0, 'ucapan' => 0];

try {
    if ($panel === 'pengantin') {
        $pengantin = ambil_pengantin();
        foreach (db()->query('SELECT id, jenis, file FROM galeri ORDER BY id') as $g) {
            $galeri[$g['jenis']][] = $g;
        }
    }
    if ($panel === 'hadir') {
        $r = db()->query(
            "SELECT COUNT(*) AS total,
                    COALESCE(SUM(status = 'hadir'), 0) AS hadir,
                    COALESCE(SUM(jumlah), 0) AS tamu,
                    COALESCE(SUM(ucapan IS NOT NULL AND ucapan <> ''), 0) AS ucapan
             FROM rsvp"
        )->fetch();
        $stat = array_map('intval', $r);
    }
} catch (Throwable $ex) {
    error_log('Admin gagal memuat data: ' . $ex->getMessage());
}

$edit = (string) ($_GET['edit'] ?? '');
if (!in_array($edit, ['pria', 'wanita'], true)) $edit = '';

$judul_jenis = ['pria' => 'Pengantin Pria', 'wanita' => 'Pengantin Wanita'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">

    <link rel="stylesheet" href="dashboard.css">

    <title><?= e($panel_list[$panel]) ?> · Dashboard</title>
</head>

<body class="dash">

    <aside class="sisi">
        <nav aria-label="Menu dashboard">
            <?php foreach ($panel_list as $k => $label): ?>
                <a href="admin.php?panel=<?= $k ?>"<?= $k === $panel ? ' class="aktif" aria-current="page"' : '' ?>><?= e($label) ?></a>
            <?php endforeach; ?>
            <a class="kembali" href="./">&larr; Kembali ke undangan</a>
        </nav>

        <div class="akun">
            <span><?= e($_SESSION['admin_user']) ?> <em class="role"><?= is_superadmin() ? 'superadmin' : 'admin' ?></em></span>
            <form action="logout.php" method="post">
                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                <button type="submit" class="tombol-teks">Keluar</button>
            </form>
        </div>
    </aside>

    <main class="isi">

        <h1><?= e($panel_list[$panel]) ?></h1>

        <?php if ($flash): ?>
            <p class="pesan <?= $flash['tipe'] === 'ok' ? 'ok' : 'galat' ?>" role="status"><?= e($flash['pesan']) ?></p>
        <?php endif; ?>

        <?php if ($panel === 'pengantin'): ?>

            <div class="grid-pengantin">
                <?php foreach ($judul_jenis as $jenis => $judul):
                    $p = $pengantin[$jenis] ?? null;
                    if (!$p) continue;
                    $mengedit = $edit === $jenis;
                ?>
                    <section class="kartu profil" id="profil-<?= $jenis ?>" aria-labelledby="h-<?= $jenis ?>">

                        <div class="foto">
                            <img src="<?= e($p['foto']) ?>" alt="Foto <?= e($judul) ?>">
                        </div>

                        <?php if (!$mengedit): ?>

                            <div class="data">
                                <div class="kepala">
                                    <h2 id="h-<?= $jenis ?>"><?= e($judul) ?></h2>
                                    <a class="tombol" href="admin.php?panel=pengantin&amp;edit=<?= $jenis ?>#profil-<?= $jenis ?>">Edit</a>
                                </div>
                                <dl>
                                    <dt>Nama</dt>   <dd><?= e($p['nama']) ?: '<span class="kosong">-</span>' ?></dd>
                                    <dt>Ibu</dt>    <dd><?= e($p['ibu']) ?: '<span class="kosong">-</span>' ?></dd>
                                    <dt>Ayah</dt>   <dd><?= e($p['ayah']) ?: '<span class="kosong">-</span>' ?></dd>
                                    <dt>Alamat</dt> <dd><?= e($p['alamat']) ?: '<span class="kosong">-</span>' ?></dd>
                                </dl>
                            </div>

                        <?php else: ?>

                            <form class="data" action="pengantin_aksi.php" method="post">
                                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                                <input type="hidden" name="aksi" value="simpan">
                                <input type="hidden" name="jenis" value="<?= $jenis ?>">

                                <div class="kepala">
                                    <h2 id="h-<?= $jenis ?>"><?= e($judul) ?></h2>
                                </div>

                                <label>Nama
                                    <input type="text" name="nama" value="<?= e($p['nama']) ?>" maxlength="100" required autofocus>
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
                                <div class="aksi">
                                    <button type="submit" class="tombol utama">Simpan</button>
                                    <a class="tombol" href="admin.php?panel=pengantin#profil-<?= $jenis ?>">Batal</a>
                                </div>
                            </form>

                        <?php endif; ?>
                    </section>
                <?php endforeach; ?>

                <?php foreach ($judul_jenis as $jenis => $judul): ?>
                    <section class="kartu galeri" aria-label="Galeri <?= e($judul) ?>">

                        <div class="kepala">
                            <h3>Galeri <?= $jenis ?> <span class="hitung"><?= count($galeri[$jenis]) ?></span></h3>
                        </div>

                        <?php if ($galeri[$jenis]): ?>
                            <ul class="kolase">
                                <?php foreach ($galeri[$jenis] as $g): ?>
                                    <li>
                                        <img src="<?= e($g['file']) ?>" alt="" loading="lazy">
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php else: ?>
                            <p class="kosong-galeri">Belum ada foto.</p>
                        <?php endif; ?>

                    </section>
                <?php endforeach; ?>
            </div>

        <?php elseif ($panel === 'hadir'): ?>

            <section class="kartu">
                <ul class="stat">
                    <li><strong><?= $stat['total'] ?></strong> RSVP masuk</li>
                    <li><strong><?= $stat['hadir'] ?></strong> konfirmasi hadir</li>
                    <li><strong><?= $stat['tamu'] ?></strong> total tamu</li>
                    <li><strong><?= $stat['ucapan'] ?></strong> ucapan</li>
                </ul>
                <p class="kosong">Daftar lengkap siapa yang hadir menyusul.</p>
            </section>

        <?php elseif ($panel === 'pengaturan'): ?>

            <section class="kartu">
                <?php if (is_superadmin()): ?>
                    <p>Atur siapa saja yang bisa masuk sebagai admin.</p>
                    <a class="tombol utama" href="kelola.php">Kelola admin</a>
                <?php else: ?>
                    <p class="kosong">Hanya superadmin yang bisa mengatur admin.</p>
                <?php endif; ?>
            </section>

        <?php else: ?>

            <section class="kartu">
                <p class="kosong">Panel ini menyusul.</p>
            </section>

        <?php endif; ?>

    </main>
</body>
</html>
