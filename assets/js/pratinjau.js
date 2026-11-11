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
        'waktu-acara': 'waktu',
        'lokasi-acara': 'alamat',
        'galeri': 'galeri',
        'story': 'cerita',
        'ucapan': 'ucapan',
        'rsvp': 'rsvp'
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

    /** Sama dengan url_maps() di includes/undangan.php. */
    function urlMaps(d) {
        var link = (d.alamat_maps || '').trim();
        if (/^https?:\/\//i.test(link)) return link;
        var q = ((d.alamat_lokasi || '') + ' ' + (d.alamat_jalan || '')).trim();
        return q ? 'https://www.google.com/maps/search/?api=1&query=' + encodeURIComponent(q) : '';
    }

    function alamat(d) {
        isiTeks($('#alamat-judul'), d.alamat_judul);
        isiTeks($('#alamat-lokasi'), d.alamat_lokasi);
        isiTeks($('#alamat-jalan'), d.alamat_jalan);
        isiTeks($('#alamat-deskripsi'), d.alamat_deskripsi);
        var tombol = $('#alamat-maps');
        if (tombol) {
            var url = urlMaps(d);
            if (url) tombol.setAttribute('href', url); else tombol.removeAttribute('href');
            tombol.hidden = !url;
            isiTeks(tombol, d.alamat_tombol);
        }
        var foto = $('#alamat-foto');
        if (foto) { ubahGambar(foto, d.alamat_foto); foto.alt = d.alamat_lokasi || ''; }
    }

    /** Galeri: kotak (x, y, w, h) pada kanvas 12 kolom; elemen dicocokkan lewat data-k supaya foto tidak dimuat ulang. */
    function galeri(d) {
        var ul = $('#galeri-list');
        if (!ul || !d.galeri) return;

        var baris = 1;
        d.galeri.forEach(function (g) { baris = Math.max(baris, g.y + g.h); });
        ul.style.setProperty('--baris', baris);
        ul.hidden = !d.galeri.length;

        var ada = {};
        Array.prototype.forEach.call(ul.children, function (li) { ada[li.getAttribute('data-k')] = li; });

        var dipakai = {};
        d.galeri.forEach(function (g, i) {
            dipakai[g.k] = true;
            var li = ada[g.k];
            if (!li) {
                li = document.createElement('li');
                li.setAttribute('data-k', g.k);
                var b = document.createElement('button');
                b.type = 'button'; b.className = 'galeri-foto';
                var img = document.createElement('img');
                b.appendChild(img); li.appendChild(b);
                ada[g.k] = li;
            }
            li.style.setProperty('--x', g.x);
            li.style.setProperty('--y', g.y);
            li.style.setProperty('--w', g.w);
            li.style.setProperty('--h', g.h);
            var im = li.querySelector('img');
            im.alt = 'Galeri foto ' + (i + 1);
            ubahGambar(im, g.src);
            ul.appendChild(li);                      // urutan DOM = urutan atas-bawah, kiri-kanan
        });
        Object.keys(ada).forEach(function (k) { if (!dipakai[k]) ada[k].remove(); });
    }

    /** Cerita: kiri/kanan bergantian seperti di index.php; elemen dicocokkan lewat data-k. */
    function cerita(d) {
        isiTeks($('#cerita-judul'), d.cerita_judul);
        isiTeks($('#cerita-deskripsi'), d.cerita_deskripsi);
        var sec = $('#story');
        if (!sec || !d.cerita) return;

        var ada = {};
        sec.querySelectorAll('.cerita').forEach(function (el) { ada[el.getAttribute('data-k')] = el; });

        var dipakai = {};
        d.cerita.forEach(function (c, i) {
            dipakai[c.k] = true;
            var el = ada[c.k];
            if (!el) {
                el = document.createElement('div');
                el.setAttribute('data-k', c.k);
                var teks = document.createElement('div'); teks.className = 'cerita-text';
                teks.appendChild(document.createElement('h3'));
                teks.appendChild(document.createElement('p'));
                el.appendChild(teks);
                el.appendChild(document.createElement('img'));
                ada[c.k] = el;
            }
            var kiri = i % 2 === 0;
            el.className = 'cerita ' + (kiri ? 'cerita-kiri' : 'cerita-kanan');
            var t = el.querySelector('.cerita-text'), img = el.querySelector(':scope > img');
            isiTeks(t.querySelector('h3'), c.judul);
            isiTeks(t.querySelector('p'), c.deskripsi);
            img.alt = c.judul || '';
            ubahGambar(img, c.foto);
            if (kiri) { el.appendChild(t); el.appendChild(img); }    // teks kiri, foto kanan
            else { el.appendChild(img); el.appendChild(t); }          // foto kiri, teks kanan
            sec.appendChild(el);
        });
        Object.keys(ada).forEach(function (k) { if (!dipakai[k]) ada[k].remove(); });
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

        // 5-9
        alamat(d);
        isiTeks($('#galeri-judul'), d.galeri_judul);
        galeri(d);
        cerita(d);
        isiTeks($('#ucapan-judul'), d.ucapan_judul);
        isiTeks($('#ucapan-teks'), d.ucapan_teks);
        isiTeks($('#rsvp-judul'), d.rsvp_judul);
        isiTeks($('#rsvp-deskripsi'), d.rsvp_deskripsi);
        isiTeks($('#ucapan-doa-judul'), d.ucapan_doa_judul);
        isiTeks($('#ucapan-kosong'), d.ucapan_kosong);
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
        if (tautan) {
            e.preventDefault();
            e.stopPropagation();   // mis. lightbox galeri tidak ikut terbuka di pratinjau
        }

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
