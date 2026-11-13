/*
 * Editor galeri (dashboard -> Undangan -> Galeri).
 *
 * Kanvas 12 kolom dengan sel persegi; tiap foto = kotak (x, y, w, h) dalam satuan sel, sama persis dengan
 * model yang dipakai undangan (includes/galeri.php + style.css #galeri-list), jadi tata letak di sini = tata letak
 * di undangan pada layar mana pun.
 *
 * Cara mengatur ukuran/posisi:
 *   - seret foto (pindah) / seret sudut kanan-bawah (ubah ukuran), snap ke sel
 *   - ketik Lebar (kolom), Tinggi (baris), Mulai dari kolom/baris di panel foto terpilih
 *   - keyboard: panah = pindah, Shift+panah = ubah ukuran, Delete = hapus
 * Foto yang bertabrakan otomatis didorong ke bawah (tidak pernah saling menimpa).
 * Disimpan bersama tombol "Simpan perubahan" (lihat editor-undangan.js -> modul).
 */
(function () {
    'use strict';

    var wadah = document.getElementById('gl');
    if (!wadah || !window.EditorUndangan) return;

    var KOLOM = +wadah.getAttribute('data-kolom');
    var BARIS_MAKS = +wadah.getAttribute('data-baris-maks');
    var MAKS_FOTO = +wadah.getAttribute('data-maks-foto');
    var MAKS_BARU = +wadah.getAttribute('data-maks-baru');
    var MAKS_BYTE = 12 * 1024 * 1024;
    var DEF_W = 4, DEF_H = 3;     // ukuran bawaan: 3 foto per baris, rasio 4:3

    var kanvas = document.getElementById('gl-kanvas');
    var panel = document.getElementById('gl-panel');
    var pesan = document.getElementById('gl-pesan');
    var hitung = document.getElementById('gl-hitung');
    var inW = document.getElementById('gl-w');
    var inH = document.getElementById('gl-h');
    var inX = document.getElementById('gl-x');
    var inY = document.getElementById('gl-y');
    var info = document.getElementById('gl-info');
    var nama = document.getElementById('gl-nama');
    var berkas = document.getElementById('gl-berkas');

    var items = [];
    try {
        items = JSON.parse(wadah.getAttribute('data-awal') || '[]').map(function (it) {
            return { k: it.k, src: it.src, x: it.x, y: it.y, w: it.w, h: it.h, file: null };
        });
    } catch (e) { items = []; }

    var elemen = {};          // kunci -> elemen DOM
    var terpilih = null;      // kunci foto terpilih
    var noBaru = 0;
    var sel = 0;              // ukuran 1 sel dalam px
    var tarik = null;         // sedang menyeret
    var timerPesan = 0;

    /* ---------- Hitungan tata letak ---------- */

    function klem(v, a, b) { return Math.max(a, Math.min(b, v)); }
    function cari(k) { for (var i = 0; i < items.length; i++) if (items[i].k === k) return items[i]; return null; }
    function salin(it) { return { k: it.k, x: it.x, y: it.y, w: it.w, h: it.h }; }
    function gcd(a, b) { return b ? gcd(b, a % b) : a; }

    function tabrak(a, b) {
        return a.x < b.x + b.w && b.x < a.x + a.w && a.y < b.y + b.h && b.y < a.y + a.h;
    }

    function urut(daftar) {
        return daftar.slice().sort(function (a, b) { return a.y - b.y || a.x - b.x; });
    }

    /**
     * Letakkan foto k di $kotak pada salinan tata letak $awal; foto yang tertimpa didorong ke bawah
     * (berantai). null kalau ada yang melewati tinggi maksimal.
     */
    function selesaikan(awal, k, kotak) {
        var l = awal.map(salin);
        var m = l.filter(function (o) { return o.k === k; })[0];
        m.x = kotak.x; m.y = kotak.y; m.w = kotak.w; m.h = kotak.h;

        var antre = [m], aman = 0;
        while (antre.length) {
            var a = antre.shift();
            for (var i = 0; i < l.length; i++) {
                var b = l[i];
                if (b === a || !tabrak(a, b)) continue;
                b.y = a.y + a.h;
                if (b.y + b.h > BARIS_MAKS) return null;
                antre.push(b);
            }
            if (++aman > 20000) return null;
        }
        return l;
    }

    function terapkan(hasil) {
        for (var i = 0; i < items.length; i++) {
            items[i].x = hasil[i].x; items[i].y = hasil[i].y; items[i].w = hasil[i].w; items[i].h = hasil[i].h;
        }
    }

    function tampilkanPesan(teks, galat) {
        clearTimeout(timerPesan);
        pesan.textContent = teks;
        pesan.className = 'gl-pesan' + (galat ? ' galat' : '');
        pesan.hidden = !teks;
        if (teks) timerPesan = setTimeout(function () { pesan.hidden = true; }, 6000);
    }

    /* ---------- Gambar ke layar ---------- */

    function srcTampil(it) { return it.file ? it.src : '../' + it.src; }

    function buatElemen(it) {
        var el = document.createElement('div');
        el.className = 'gl-item';
        el.tabIndex = 0;
        el.setAttribute('role', 'button');

        var img = new Image();
        img.alt = '';
        img.draggable = false;
        el.appendChild(img);

        ['gl-no', 'gl-ukuran'].forEach(function (c) {
            var s = document.createElement('span'); s.className = c; el.appendChild(s);
        });

        var x = document.createElement('button');
        x.type = 'button'; x.className = 'gl-x'; x.textContent = '×';
        el.appendChild(x);

        var sudut = document.createElement('span');
        sudut.className = 'gl-sudut'; sudut.setAttribute('aria-hidden', 'true');
        el.appendChild(sudut);

        x.addEventListener('click', function (e) { e.stopPropagation(); hapus(el._k); });
        el.addEventListener('pointerdown', mulaiTarik);
        el.addEventListener('pointermove', geserTarik);
        el.addEventListener('pointerup', akhiriTarik);
        el.addEventListener('pointercancel', akhiriTarik);
        el.addEventListener('keydown', tombolKeyboard);
        el.addEventListener('focus', function () { pilih(el._k, true); });

        kanvas.appendChild(el);
        return el;
    }

    function render() {
        // kanvas
        sel = kanvas.clientWidth / KOLOM;
        var pakai = 0;
        items.forEach(function (it) { pakai = Math.max(pakai, it.y + it.h); });
        var baris = klem(pakai + 3, 6, BARIS_MAKS);
        kanvas.style.setProperty('--sel', sel + 'px');
        kanvas.style.height = (baris * sel) + 'px';

        // elemen: buat / perbarui / hapus
        var ada = {};
        items.forEach(function (it, i) {
            ada[it.k] = true;
            var el = elemen[it.k] || (elemen[it.k] = buatElemen(it));
            el._k = it.k;
            el.style.setProperty('--x', it.x);
            el.style.setProperty('--y', it.y);
            el.style.setProperty('--w', it.w);
            el.style.setProperty('--h', it.h);
            el.classList.toggle('terpilih', it.k === terpilih);

            var img = el.querySelector('img');
            var src = srcTampil(it);
            if (img.getAttribute('src') !== src) img.src = src;

            el.querySelector('.gl-no').textContent = (i + 1);
            el.querySelector('.gl-ukuran').textContent = it.w + '×' + it.h;
            el.setAttribute('aria-label', 'Foto ' + (i + 1) + ', ' + it.w + ' kolom × ' + it.h + ' baris');
            el.querySelector('.gl-x').setAttribute('aria-label', 'Hapus foto ' + (i + 1));
        });
        Object.keys(elemen).forEach(function (k) {
            if (!ada[k]) { elemen[k].remove(); delete elemen[k]; }
        });

        hitung.textContent = items.length + ' foto';
        isiPanel();
    }

    /** Isi panel ukuran untuk foto terpilih. $kecuali = input yang sedang diketik (jangan ditimpa). */
    function isiPanel(kecuali) {
        var it = terpilih && cari(terpilih);
        panel.hidden = !it;
        if (!it) return;

        var no = items.indexOf(it) + 1;
        nama.textContent = 'Foto ' + no + (it.file ? ' (baru, belum disimpan)' : '');
        [[inW, it.w], [inH, it.h], [inX, it.x + 1], [inY, it.y + 1]].forEach(function (p) {
            if (p[0] !== kecuali) p[0].value = p[1];
        });
        var g = gcd(it.w, it.h);
        info.textContent = '= ' + Math.round(it.w / KOLOM * 100) + '% dari lebar galeri · rasio ' + (it.w / g) + ':' + (it.h / g) +
            ' · menempati kolom ' + (it.x + 1) + '–' + (it.x + it.w) + ', baris ' + (it.y + 1) + '–' + (it.y + it.h);
    }

    function pilih(k, tanpaFokus) {
        if (terpilih === k) return;
        terpilih = k;
        Object.keys(elemen).forEach(function (kk) { elemen[kk].classList.toggle('terpilih', kk === k); });
        isiPanel();
    }

    function berubah() { window.EditorUndangan.berubah(); }

    /* ---------- Seret ---------- */

    function mulaiTarik(e) {
        var el = e.currentTarget;
        if (e.pointerType === 'mouse' && e.button !== 0) return;
        if (e.target.closest('.gl-x')) return;
        var it = cari(el._k);
        if (!it || sel <= 0) return;

        pilih(it.k);
        e.preventDefault();
        el.focus({ preventScroll: true });
        el.setPointerCapture(e.pointerId);
        tarik = {
            mode: e.target.closest('.gl-sudut') ? 'ukur' : 'geser',
            k: it.k, pid: e.pointerId, sx: e.clientX, sy: e.clientY,
            awal: items.map(salin), asal: salin(it), berubah: false
        };
        el.classList.add('sedang');
    }

    function geserTarik(e) {
        if (!tarik || e.pointerId !== tarik.pid) return;
        var dx = Math.round((e.clientX - tarik.sx) / sel);
        var dy = Math.round((e.clientY - tarik.sy) / sel);
        var a = tarik.asal, kotak;

        if (tarik.mode === 'geser') {
            kotak = { x: klem(a.x + dx, 0, KOLOM - a.w), y: klem(a.y + dy, 0, BARIS_MAKS - a.h), w: a.w, h: a.h };
        } else {
            kotak = { x: a.x, y: a.y, w: klem(a.w + dx, 1, KOLOM - a.x), h: klem(a.h + dy, 1, BARIS_MAKS - a.y) };
        }
        var hasil = selesaikan(tarik.awal, tarik.k, kotak);
        if (!hasil) return;
        terapkan(hasil);
        tarik.berubah = true;
        render();
    }

    function akhiriTarik(e) {
        if (!tarik || e.pointerId !== tarik.pid) return;
        var el = elemen[tarik.k];
        if (el) el.classList.remove('sedang');
        if (tarik.berubah) berubah();
        tarik = null;
    }

    /* ---------- Keyboard ---------- */

    function tombolKeyboard(e) {
        var it = cari(e.currentTarget._k);
        if (!it) return;

        if (e.key === 'Delete' || e.key === 'Backspace') { e.preventDefault(); hapus(it.k); return; }

        var d = { ArrowLeft: [-1, 0], ArrowRight: [1, 0], ArrowUp: [0, -1], ArrowDown: [0, 1] }[e.key];
        if (!d) return;
        e.preventDefault();

        var kotak = e.shiftKey
            ? { x: it.x, y: it.y, w: klem(it.w + d[0], 1, KOLOM - it.x), h: klem(it.h + d[1], 1, BARIS_MAKS - it.y) }
            : { x: klem(it.x + d[0], 0, KOLOM - it.w), y: klem(it.y + d[1], 0, BARIS_MAKS - it.h), w: it.w, h: it.h };
        var hasil = selesaikan(items.map(salin), it.k, kotak);
        if (!hasil) return;
        terapkan(hasil);
        render();
        berubah();
        elemen[it.k].focus({ preventScroll: true });
    }

    /* ---------- Ketik ukuran / posisi ---------- */

    function dariInput(e) {
        var it = terpilih && cari(terpilih);
        if (!it) return;
        var v = parseInt(e.target.value, 10);
        var batas = { 'gl-w': [1, KOLOM], 'gl-h': [1, BARIS_MAKS], 'gl-x': [1, KOLOM], 'gl-y': [1, BARIS_MAKS] }[e.target.id];
        if (isNaN(v) || v < batas[0] || v > batas[1]) return;     // belum selesai mengetik / di luar batas

        var kotak = { x: it.x, y: it.y, w: it.w, h: it.h };
        if (e.target === inW) { kotak.w = v; kotak.x = Math.min(kotak.x, KOLOM - v); }
        if (e.target === inH) { kotak.h = v; kotak.y = Math.min(kotak.y, BARIS_MAKS - v); }
        if (e.target === inX) kotak.x = Math.min(v - 1, KOLOM - it.w);
        if (e.target === inY) kotak.y = Math.min(v - 1, BARIS_MAKS - it.h);

        var hasil = selesaikan(items.map(salin), it.k, kotak);
        if (!hasil) { tampilkanPesan('Tidak muat: tata letak galeri melebihi ' + BARIS_MAKS + ' baris.', true); return; }
        terapkan(hasil);
        render();
        isiPanel(e.target);
        berubah();
    }

    [inW, inH, inX, inY].forEach(function (i) {
        i.addEventListener('input', dariInput);
        i.addEventListener('change', function () { isiPanel(); });   // rapikan nilai kalau kosong/di luar batas
    });

    /* ---------- Tambah / hapus / susun ---------- */

    function cariSlot(w, h) {
        for (var y = 0; y + h <= BARIS_MAKS; y++) {
            for (var x = 0; x + w <= KOLOM; x++) {
                var k = { x: x, y: y, w: w, h: h }, bebas = true;
                for (var i = 0; i < items.length; i++) if (tabrak(k, items[i])) { bebas = false; break; }
                if (bebas) return k;
            }
        }
        return null;
    }

    berkas.addEventListener('change', function () {
        var daftar = Array.prototype.slice.call(berkas.files);
        berkas.value = '';
        var galat = [], terakhir = null;
        var baru = items.filter(function (i) { return i.file; }).length;

        daftar.forEach(function (f) {
            if (!/^image\/(jpeg|png|webp)$/.test(f.type)) { galat.push(f.name + ': format tidak didukung (gunakan JPG, PNG, atau WebP).'); return; }
            if (f.size > MAKS_BYTE) { galat.push(f.name + ': lebih dari 12 MB.'); return; }
            if (items.length >= MAKS_FOTO) { galat.push('Galeri maksimal ' + MAKS_FOTO + ' foto.'); return; }
            if (baru >= MAKS_BARU) { galat.push('Maksimal ' + MAKS_BARU + ' foto baru sekali simpan. Simpan dulu, lalu tambah lagi.'); return; }
            var slot = cariSlot(DEF_W, DEF_H);
            if (!slot) { galat.push('Tidak ada ruang kosong lagi di galeri.'); return; }

            var it = { k: 'n' + (++noBaru), src: URL.createObjectURL(f), x: slot.x, y: slot.y, w: DEF_W, h: DEF_H, file: f };
            items.push(it);
            terakhir = it.k;
            baru++;
        });

        if (terakhir) { pilih(terakhir); render(); berubah(); }
        tampilkanPesan(galat.length ? galat.join(' ') : (terakhir ? 'Foto ditambahkan. Atur ukuran dan posisinya, lalu simpan.' : ''), galat.length > 0);
    });

    function hapus(k) {
        var it = cari(k);
        if (!it) return;
        if (it.file) URL.revokeObjectURL(it.src);
        items.splice(items.indexOf(it), 1);
        if (terpilih === k) terpilih = null;
        render();
        berubah();
    }
    document.getElementById('gl-hapus').addEventListener('click', function () { if (terpilih) hapus(terpilih); });

    document.getElementById('gl-susun').addEventListener('click', function () {
        if (!items.length) return;
        if (!window.confirm('Semua foto akan diberi ukuran sama (3 per baris) menurut urutannya sekarang. Lanjutkan?')) return;
        var per = Math.floor(KOLOM / DEF_W);
        urut(items).forEach(function (it, i) {
            it.x = (i % per) * DEF_W; it.y = Math.floor(i / per) * DEF_H; it.w = DEF_W; it.h = DEF_H;
        });
        render();
        berubah();
    });

    document.getElementById('gl-rapat').addEventListener('click', function () {
        var sudah = [];
        urut(items).forEach(function (it) {
            for (var y = 0; y <= it.y; y++) {
                var k = { x: it.x, y: y, w: it.w, h: it.h };
                if (!sudah.some(function (o) { return tabrak(k, o); })) { it.y = y; break; }
            }
            sudah.push(it);
        });
        render();
        berubah();
    });

    /* ---------- Ukuran kanvas mengikuti lebar kolom form ---------- */

    if (window.ResizeObserver) new ResizeObserver(function () { if (!tarik) render(); }).observe(kanvas);
    window.addEventListener('resize', function () { if (!tarik) render(); });

    /* ---------- Hubungan dengan editor utama ---------- */

    window.EditorUndangan.daftar({
        // Dikirim ke pratinjau (urutan = urutan di undangan: atas ke bawah, kiri ke kanan)
        kumpulkan: function () {
            return {
                galeri: urut(items).map(function (it) {
                    return { k: it.k, src: it.src, x: it.x, y: it.y, w: it.w, h: it.h };
                })
            };
        },

        // Dikirim ke server saat menyimpan
        isiForm: function (fd) {
            fd.set('galeri_tata', JSON.stringify(items.map(function (it) {
                return { k: it.k, x: it.x, y: it.y, w: it.w, h: it.h };
            })));
            items.forEach(function (it) {
                if (it.file) fd.append('galeri_baru[' + it.k + ']', it.file, it.file.name);
            });
        },

        // Foto baru yang tersimpan mendapat kunci tetap (g<id>) dan path di server
        setelahSimpan: function (d) {
            var peta = d.galeri_baru || {};
            Object.keys(peta).forEach(function (kLama) {
                var it = cari(kLama);
                if (!it) return;
                URL.revokeObjectURL(it.src);
                it.k = peta[kLama].k;
                it.src = peta[kLama].file;
                it.file = null;
                if (terpilih === kLama) terpilih = it.k;
                if (elemen[kLama]) { elemen[it.k] = elemen[kLama]; delete elemen[kLama]; }
            });
            render();
        }
    });

    render();
})();
