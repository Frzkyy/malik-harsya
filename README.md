# malik-harsya

Undangan pernikahan + dashboard admin (PHP + SQLite).

## Struktur folder

```
/
├── index.php            # halaman undangan
├── submit.php           # handler form RSVP
├── login.php            # login admin (sudah login -> otomatis ke dashboard/)
├── logout.php
├── .htaccess
├── includes/            # bootstrap, tidak untuk diakses browser
│   ├── config.php       #   .env, session, CSRF, helper auth
│   ├── db.php           #   koneksi, skema & migrasi SQLite
│   └── upload.php       #   validasi, perkecil & simpan gambar unggahan
├── assets/
│   └── css/             #   style.css (undangan + login), dashboard.css
├── dashboard/           # semua halaman setelah login
│   ├── index.php        #   layout + sidebar, memuat panel/<nama>.php
│   ├── panel/           #   pengantin, tamu, hadir, pengaturan
│   ├── pengantin_aksi.php   #   simpan data + ganti foto pengantin
│   ├── galeri_aksi.php      #   tambah (banyak file) / hapus foto galeri
│   └── kelola.php       #   kelola admin (superadmin)
├── media/               # gambar; media/unggahan = upload (tidak dieksekusi)
├── data/                # database SQLite (di-ignore git, dibuat otomatis)
└── aktivitas/           # tugas kuliah (terpisah dari proyek undangan)
```

`/admin` diarahkan ke `/dashboard/`. File `.env` tetap di root proyek.

## Galeri & foto pengantin

Dikelola dari dashboard (`Pengantin`): tambah foto galeri (boleh banyak sekaligus), hapus dengan tombol ×,
dan ganti foto pengantin lewat tombol Edit. Format JPG/PNG/WebP, maks. 12 MB per file; gambar otomatis
diputar sesuai EXIF dan diperkecil (sisi terpanjang 2000 px). Rasio bebas: galeri di undangan memakai
baris "justified" (portrait/landscape/persegi bercampur tanpa dipotong) dan bisa diklik untuk diperbesar.
Database lama dimigrasi otomatis. Pastikan folder `media/unggahan/` bisa ditulis oleh PHP.
