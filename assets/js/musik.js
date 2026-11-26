/*
 * Musik latar undangan (hanya dimuat di undangan asli, bukan di pratinjau dashboard).
 *
 * Alur:
 *  1. Gerbang tamu punya tombol "Musik: Nyala/Mati" (bawaan nyala).
 *  2. Saat tamu menekan Masuk / Buka Undangan / Lihat sebagai tamu, Musik.siapkan() dipanggil DI DALAM klik itu,
 *     sehingga browser mengizinkan suara diputar otomatis.
 *  3. Setelah gerbang naik, Musik.masuk() memunculkan tombol musik melayang (ikut tergulir bersama tamu)
 *     untuk menyalakan/mematikan musik kapan saja.
 *  4. Pilihan tamu (nyala/mati) dan posisi lagu disimpan di sessionStorage, jadi saat halaman dimuat ulang
 *     (mis. pindah halaman ucapan) lagu dilanjutkan dari posisi terakhir. Kalau browser menolak autoplay,
 *     lagu dimulai pada sentuhan/klik pertama.
 */
(function () {
    'use strict';

    var audio = document.getElementById('musik-audio');
    var tombol = document.getElementById('musik-tombol');
    if (!audio || !tombol) return;

    var gerbangTombol = document.getElementById('gerbang-musik');
    var gerbangStatus = document.getElementById('gerbang-musik-status');
    var KUNCI = 'undangan_musik';

    var volume = parseInt(audio.getAttribute('data-volume'), 10);
    audio.volume = Math.max(0.05, Math.min(1, (isNaN(volume) ? 60 : volume) / 100));

    var tersimpan = baca();
    var gerbangNyala = tersimpan.nyala !== false;      // pilihan di gerbang (bawaan: nyala)
    var diinginkan = false;                             // tamu ingin musik berbunyi
    var sudahMasuk = !gerbangTombol;                   // tanpa gerbang = tamu sudah di dalam undangan

    function baca() {
        try { return JSON.parse(sessionStorage.getItem(KUNCI)) || {}; } catch (_) { return {}; }
    }
    function simpan(data) {
        try { sessionStorage.setItem(KUNCI, JSON.stringify(Object.assign(baca(), data))); } catch (_) {}
    }

    /* ---------- Tampilan tombol ---------- */

    function tampil() {
        var bunyi = diinginkan && !audio.paused;
        tombol.classList.toggle('bunyi', bunyi);
        tombol.setAttribute('aria-pressed', bunyi ? 'true' : 'false');
        tombol.setAttribute('aria-label', bunyi ? 'Matikan musik' : 'Putar musik');
        tombol.setAttribute('title', bunyi ? 'Matikan musik' : 'Putar musik');
    }

    function tampilGerbang() {
        if (!gerbangTombol) return;
        gerbangTombol.setAttribute('aria-pressed', gerbangNyala ? 'true' : 'false');
        gerbangTombol.classList.toggle('mati', !gerbangNyala);
        if (gerbangStatus) gerbangStatus.textContent = gerbangNyala ? 'Nyala' : 'Mati';
    }

    /* ---------- Putar / henti ---------- */

    function putar() {
        var p;
        try { p = audio.play(); } catch (_) { p = null; }
        if (p && p.then) {
            p.then(tampil, function () { menungguSentuhan(); tampil(); });
        } else {
            tampil();
        }
    }

    function henti() {
        audio.pause();
        tampil();
    }

    /** Autoplay ditolak browser (mis. halaman dimuat ulang): mulai pada interaksi pertama. */
    var menunggu = false;
    function menungguSentuhan() {
        if (menunggu || !diinginkan) return;
        menunggu = true;
        var acara = ['pointerdown', 'touchend', 'keydown'];
        function mulai() {
            acara.forEach(function (a) { document.removeEventListener(a, mulai, true); });
            menunggu = false;
            if (diinginkan && audio.paused) putar();
        }
        acara.forEach(function (a) { document.addEventListener(a, mulai, true); });
    }

    /* ---------- Gerbang tamu ---------- */

    if (gerbangTombol) {
        tampilGerbang();
        gerbangTombol.addEventListener('click', function () {
            gerbangNyala = !gerbangNyala;
            tampilGerbang();
            simpan({ nyala: gerbangNyala });
            // Kalau lagu sudah terlanjur diputar (tamu menekan Masuk lalu server menolak), toggle mati = berhenti.
            if (!gerbangNyala && !audio.paused) { diinginkan = false; henti(); }
        });
    }

    var Musik = {
        /** Dipanggil di dalam klik tamu (Masuk / Buka Undangan / Lihat sebagai tamu). */
        siapkan: function () {
            if (!gerbangNyala) { diinginkan = false; simpan({ nyala: false }); return; }
            diinginkan = true;
            simpan({ nyala: true });
            putar();
        },
        /** Gerbang gagal dibuka (nama ditolak / jaringan): hentikan lagu yang baru dimulai. */
        batal: function () {
            diinginkan = false;
            henti();
        },
        /** Gerbang sudah naik: tamu ada di dalam undangan -> tombol melayang muncul. */
        masuk: function () {
            sudahMasuk = true;
            tombol.hidden = false;
            tombol.classList.add('muncul');
            tampil();
        }
    };
    window.Musik = Musik;

    /* ---------- Tombol melayang ---------- */

    tombol.addEventListener('click', function () {
        if (diinginkan && !audio.paused) {
            diinginkan = false;
            simpan({ nyala: false });
            henti();
        } else {
            diinginkan = true;
            simpan({ nyala: true });
            putar();
        }
    });

    audio.addEventListener('play', tampil);
    audio.addEventListener('pause', tampil);

    /* ---------- Lanjutkan setelah halaman dimuat ulang ---------- */

    // Posisi lagu disimpan berkala; dipulihkan saat halaman dimuat lagi (hanya untuk lagu yang sama).
    var sumber = audio.getAttribute('src');
    var terakhir = 0;
    audio.addEventListener('timeupdate', function () {
        var t = Date.now();
        if (t - terakhir < 1000) return;
        terakhir = t;
        simpan({ posisi: audio.currentTime, sumber: sumber });
    });
    window.addEventListener('pagehide', function () {
        if (!audio.paused) simpan({ posisi: audio.currentTime, sumber: sumber });
    });

    if (sudahMasuk && tersimpan.nyala === true) {
        // Tanpa gerbang dan tamu sebelumnya menyalakan musik: lanjutkan (atau mulai pada sentuhan pertama).
        diinginkan = true;
        if (tersimpan.sumber === sumber && tersimpan.posisi > 0) {
            audio.addEventListener('loadedmetadata', function () {
                try { audio.currentTime = tersimpan.posisi; } catch (_) {}
            }, { once: true });
        }
        putar();
    }
    tampil();
})();
