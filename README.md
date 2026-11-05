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
│   └── db.php           #   koneksi & skema SQLite
├── assets/
│   └── css/             #   style.css (undangan + login), dashboard.css
├── dashboard/           # semua halaman setelah login
│   ├── index.php        #   layout + sidebar, memuat panel/<nama>.php
│   ├── panel/           #   pengantin, tamu, hadir, pengaturan
│   ├── pengantin_aksi.php
│   └── kelola.php       #   kelola admin (superadmin)
├── media/               # gambar; media/unggahan = upload (tidak dieksekusi)
├── data/                # database SQLite (di-ignore git, dibuat otomatis)
└── aktivitas/           # tugas kuliah (terpisah dari proyek undangan)
```

`/admin` diarahkan ke `/dashboard/`. File `.env` tetap di root proyek.
