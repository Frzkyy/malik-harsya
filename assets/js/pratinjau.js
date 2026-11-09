/*
 * Pratinjau live undangan.
 * Hanya dimuat oleh index.php saat dibuka dengan ?pratinjau=1 oleh admin yang login
 * (iframe di dashboard, tab Undangan). Menerima data dari dashboard lewat postMessage
 * lalu memperbarui elemen undangan yang SAMA dengan yang dilihat tamu, jadi CSS &
 * tampilan responsifnya otomatis identik dengan undangan asli.
 *
 * Semua teks dimasukkan lewat textContent/createTextNode (bukan innerHTML).
 */
(function () {
    'use strict';

    var ASAL = location.origin;
    var BULAN = ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
    var HARI = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];

    // id bagian undangan -> nama bagian di editor dashboard
    var BAGIAN = {
        'cover-image': 'cover',
        'ucapan-tujuan': 'pengantar',
        'informasi-pengundang': 'mempelai',
        'waktu-acara': 'waktu'
    };

    function $(sel) { return document.querySelector(sel); }

    /** Isi teks, baris baru -> <br> (sama seperti nl2br di PHP). */
    function isiTeks(el, teks) {
        if (!el) return;
        while (el.firstChild) el.removeChild(el.firstChild);
        String(teks == null ? '' : teks).split('\n').forEach(function (baris, i) {
            if (i) el.appendChild(document.createElement('br'));
            el.appendChild(document.createTextNode(baris));
        });
    }

    function tanggalIndo(iso, denganHari) {
        var m = /^(\d{4})-(\d{2})-(\d{2})$/.exec(iso || '');
        if (!m) return '';
        var y = +m[1], bln = +m[2], tgl = +m[3];
        var d = new Date(Date.UTC(y, bln - 1, tgl));
        if (d.getUTCMonth() !== bln - 1 || d.getUTCDate() !== tgl) return '';
        var teks = tgl + ' ' + BULAN[bln] + ' ' + y;
        return denganHari ? HARI[d.getUTCDay()] + ', ' + teks : teks;
    }

    function jamAcara(mulai, selesai, zona) {
        return selesai ? mulai + ' - ' + selesai + ' ' + zona : mulai + ' ' + zona + ' - Selesai';
    }

    /** Sama dengan rasio() di index.php: rasio foto dijepit 0.5 - 1.6. */
    function rasio(w, h) {
        if (!w || !h) return 1 / 1.618;
        return Math.round(Math.max(0.5, Math.min(1.6, w / h)) * 10000) / 10000;
    }

    /** Sama dengan orang_tua() di index.php. */
    function orangTua(ayah, ibu) {
        var o = [];
        if (ayah) o.push('Bapak ' + ayah);
        if (ibu) o.push('Ibu ' + ibu);
        return o.join(' & ');
    }

    function ubahGambar(img, src) {
        if (!img || !src || img.getAttribute('data-src') === src) return;
        img.setAttribute('data-src', src);
        img.src = src;
    }

    function mempelai(jenis, d, label) {
        var p = $('#teks-mempelai-' + jenis);
        var wadah = $('#mempelai-' + jenis);
        if (!p || !wadah) return;

        var nama = d[jenis + '_nama'] || '';
        var ortu = orangTua(d[jenis + '_ayah'], d[jenis + '_ibu']);

        while (p.firstChild) p.removeChild(p.firstChild);
        p.appendChild(document.createTextNode(nama));
        p.appendChild(document.createElement('br'));
        if (ortu) {
            p.appendChild(document.createTextNode(label));
            p.appendChild(document.createElement('br'));
            p.appendChild(document.createTextNode(ortu));
        }

        var img = wadah.querySelector('.foto-mempelai');
        if (img) {
            img.alt = nama;
            var src = d[jenis + '_foto'];
            if (src && img.getAttribute('data-src') !== src) {
                ubahGambar(img, src);
                // Rasio mengikuti foto baru (seperti saat dirender server).
                var tes = new Image();
                tes.onload = function () { img.style.aspectRatio = rasio(tes.naturalWidth, tes.naturalHeight); };
                tes.src = src;
            }
        }
    }

    function acara(id, d, awalan) {
        var el = document.getElementById(id);
        if (!el) return;
        isiTeks(el.querySelector('.jam'), jamAcara(d[awalan + '_mulai'], d[awalan + '_selesai'], d[awalan + '_zona']));
        isiTeks(el.querySelector('.waktu-info > p:last-child'), d[awalan + '_deskripsi']);
        ubahGambar(el.querySelector('.waktu-isi > img'), d[awalan + '_foto']);
    }

    function terapkan(d) {
        // 1. Cover (nama tamu sengaja TIDAK disentuh)
        isiTeks($('#wedding-title'), d.cover_judul);
        var nama = document.querySelectorAll('#couple-name span');
        if (nama.length >= 3) {
            isiTeks(nama[0], d.pria_panggilan);
            isiTeks(nama[2], d.wanita_panggilan);
        }
        isiTeks($('#cover-date'), tanggalIndo(d.tanggal, false));
        var cover = $('#cover-image');
        if (cover && d.cover_foto && cover.getAttribute('data-src') !== d.cover_foto) {
            cover.setAttribute('data-src', d.cover_foto);
            cover.style.backgroundImage = 'url("' + d.cover_foto.replace(/["\\\n]/g, '') + '")';
        }

        // 2. Kata pengantar
        isiTeks($('#kata-pengantar'), d.kata_pengantar);

        // 3. Mempelai
        isiTeks($('#mempelai-judul'), d.mempelai_judul);
        isiTeks($('#mempelai-deskripsi'), d.mempelai_deskripsi);
        mempelai('pria', d, 'Putra dari');
        mempelai('wanita', d, 'Putri dari');

        // 4. Tempat & waktu
        isiTeks($('#tanggal-acara'), tanggalIndo(d.tanggal, true));
        acara('acara-akad', d, 'akad');
        acara('acara-resepsi', d, 'resepsi');
    }

    function gulirKe(id) {
        var el = document.getElementById(id);
        if (el) el.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    window.addEventListener('message', function (e) {
        if (e.origin !== ASAL || e.source !== window.parent || !e.data) return;
        if (e.data.type === 'undangan:data' && e.data.data) terapkan(e.data.data);
        if (e.data.type === 'undangan:gulir') gulirKe(String(e.data.id));
    });

    // Di pratinjau tidak ada yang berpindah halaman / terkirim.
    document.addEventListener('click', function (e) {
        var tautan = e.target.closest && e.target.closest('a, button');
        if (tautan) e.preventDefault();

        // Klik bagian undangan -> editor membuka bagian yang sesuai.
        var bagian = e.target.closest && e.target.closest('section[id]');
        if (bagian && BAGIAN[bagian.id]) {
            window.parent.postMessage({ type: 'undangan:fokus', bagian: BAGIAN[bagian.id] }, ASAL);
        }
    }, true);
    document.addEventListener('submit', function (e) { e.preventDefault(); }, true);

    document.documentElement.classList.add('pratinjau');
    window.parent.postMessage({ type: 'undangan:siap' }, ASAL);
})();
