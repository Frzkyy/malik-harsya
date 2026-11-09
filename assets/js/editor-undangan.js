/*
 * Editor Undangan (dashboard, tab Undangan).
 *  - Setiap perubahan form dikirim ke iframe pratinjau (assets/js/pratinjau.js) lewat postMessage.
 *  - Pratinjau memakai halaman undangan asli, "layar virtualnya" mengikuti perangkat yang dipakai
 *    (atau HP / Tablet / Desktop pilihan admin) lalu diskalakan agar muat di panel.
 *  - Simpan lewat fetch ke dashboard/undangan_aksi.php (tanpa memuat ulang halaman).
 */
(function () {
    'use strict';

    var form = document.getElementById('form-undangan');
    var frame = document.getElementById('pv-frame');
    if (!form || !frame) return;

    var ASAL = location.origin;
    var MAKS_BYTE = 12 * 1024 * 1024;           // sama dengan UNGGAH_MAKS di includes/upload.php
    var NAMA_FOTO = ['cover_foto', 'pria_foto', 'wanita_foto', 'akad_foto', 'resepsi_foto'];

    var layar = document.getElementById('pv-layar');
    var kanvas = document.getElementById('pv-kanvas');
    var panel = document.getElementById('pratinjau');
    var status = document.getElementById('ed-status');
    var tombolSimpan = document.getElementById('ed-simpan');

    var siap = false;          // iframe sudah mengirim 'siap'
    var kotor = false;         // ada perubahan belum disimpan
    var antre = 0;
    var blobAktif = {};        // nama foto -> blob URL (supaya bisa di-revoke)
    var sumberFoto = {};       // nama foto -> src untuk pratinjau (path di root situs, atau blob URL)

    NAMA_FOTO.forEach(function (n) {
        var w = form.querySelector('.ed-foto[data-foto="' + n + '"]');
        if (w) sumberFoto[n] = w.getAttribute('data-path');
    });

    /* ---------- Kirim data ke pratinjau ---------- */

    function kumpulkan() {
        var d = {};
        Array.prototype.forEach.call(form.elements, function (el) {
            if (!el.name || el.type === 'file' || el.type === 'hidden') return;
            d[el.name] = el.value;
        });
        NAMA_FOTO.forEach(function (n) { d[n] = sumberFoto[n]; });
        return d;
    }

    function kirim() {
        antre = 0;
        if (!siap || !frame.contentWindow) return;
        frame.contentWindow.postMessage({ type: 'undangan:data', data: kumpulkan() }, ASAL);
    }

    function jadwalkanKirim() {
        if (!antre) antre = requestAnimationFrame(kirim);
    }

    function gulirPratinjau(id) {
        if (siap && id) frame.contentWindow.postMessage({ type: 'undangan:gulir', id: id }, ASAL);
    }

    /* ---------- Status simpan ---------- */

    function setStatus(teks, kelas) {
        status.textContent = teks;
        status.className = 'ed-status' + (kelas ? ' ' + kelas : '');
    }

    function tandaiKotor() {
        if (!kotor) { kotor = true; }
        setStatus('Ada perubahan yang belum disimpan.', 'kotor');
    }

    window.addEventListener('beforeunload', function (e) {
        if (!kotor) return;
        e.preventDefault();
        e.returnValue = '';
    });

    /* ---------- Input form ---------- */

    form.addEventListener('input', function (e) {
        var el = e.target;
        if (!el.name || el.type === 'file') return;

        // Tanggal ada di dua bagian (Cover & Tempat dan Waktu): selalu disamakan.
        if (el.name === 'tanggal') {
            form.querySelectorAll('input[name="tanggal"]').forEach(function (o) { if (o !== el) o.value = el.value; });
        }
        tandaiKotor();
        jadwalkanKirim();
    });

    form.addEventListener('change', function (e) {
        var el = e.target;
        if (el.type !== 'file') { if (el.tagName === 'SELECT') { tandaiKotor(); jadwalkanKirim(); } return; }

        var wadah = el.closest('.ed-foto');
        var ket = wadah.querySelector('.ed-ket');
        var nama = el.name;
        wadah.classList.remove('ada-galat');
        ket.textContent = 'JPG/PNG/WebP, maks. 12 MB. Kosongkan kalau tidak ingin mengganti.';

        var f = el.files && el.files[0];
        if (!f) { pulihkanFoto(nama); return; }

        if (!/^image\/(jpeg|png|webp)$/.test(f.type)) {
            return tolakFoto(el, wadah, ket, 'Format tidak didukung. Gunakan JPG, PNG, atau WebP.');
        }
        if (f.size > MAKS_BYTE) {
            return tolakFoto(el, wadah, ket, 'Ukuran file terlalu besar (maksimal 12 MB).');
        }

        if (blobAktif[nama]) URL.revokeObjectURL(blobAktif[nama]);
        blobAktif[nama] = URL.createObjectURL(f);
        sumberFoto[nama] = blobAktif[nama];
        wadah.querySelector('.ed-thumb').src = blobAktif[nama];
        tandaiKotor();
        jadwalkanKirim();
    });

    function tolakFoto(input, wadah, ket, pesan) {
        input.value = '';
        wadah.classList.add('ada-galat');
        ket.textContent = pesan;
        pulihkanFoto(input.name);
    }

    /** Batalkan pilihan foto: kembali ke gambar yang tersimpan. */
    function pulihkanFoto(nama) {
        var wadah = form.querySelector('.ed-foto[data-foto="' + nama + '"]');
        if (blobAktif[nama]) { URL.revokeObjectURL(blobAktif[nama]); delete blobAktif[nama]; }
        sumberFoto[nama] = wadah.getAttribute('data-path');
        wadah.querySelector('.ed-thumb').src = '../' + sumberFoto[nama];
        jadwalkanKirim();
    }

    /* ---------- Bagian (accordion) <-> pratinjau ---------- */

    var bagianList = Array.prototype.slice.call(form.querySelectorAll('details.bagian'));
    var bagianAktif = null;

    function bukaBagian(det) {
        // Cadangan untuk browser yang belum mendukung <details name> (satu terbuka sekaligus).
        bagianList.forEach(function (d) { if (d !== det && d.open) d.open = false; });
        det.open = true;
    }

    bagianList.forEach(function (det) {
        det.addEventListener('toggle', function () {
            if (!det.open) return;
            bagianList.forEach(function (d) { if (d !== det && d.open) d.open = false; });
            bagianAktif = det;
            gulirPratinjau(det.getAttribute('data-sasaran'));
        });
    });

    // Fokus ke kolom di sub-bagian (Akad / Resepsi): pratinjau menggulir ke blok itu.
    form.addEventListener('focusin', function (e) {
        var sub = e.target.closest && e.target.closest('[data-sasaran]');
        if (sub) gulirPratinjau(sub.getAttribute('data-sasaran'));
    });

    /* ---------- Pesan dari iframe pratinjau ---------- */

    window.addEventListener('message', function (e) {
        if (e.origin !== ASAL || e.source !== frame.contentWindow || !e.data) return;

        if (e.data.type === 'undangan:siap') {
            siap = true;
            kirim();
            if (bagianAktif) gulirPratinjau(bagianAktif.getAttribute('data-sasaran'));
        }

        // Klik bagian di pratinjau -> buka bagian editor yang sesuai.
        if (e.data.type === 'undangan:fokus') {
            var det = form.querySelector('details.bagian[data-bagian="' + String(e.data.bagian).replace(/[^a-z]/g, '') + '"]');
            if (det) {
                bukaBagian(det);
                det.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            }
        }
    });

    /* ---------- Ukuran & skala pratinjau ---------- */

    // Lebar "layar virtual" per mode; Otomatis = lebar jendela perangkat yang dipakai mengedit.
    var LEBAR = { hp: 390, tablet: 820, desktop: 1440 };
    var mode = 'auto';

    function ukur() {
        var W = layar.clientWidth;
        var H = layar.clientHeight;
        if (!W || !H) return;

        var V = mode === 'auto' ? Math.max(320, window.innerWidth) : LEBAR[mode];
        var skala = Math.min(1, W / V);

        kanvas.style.width = Math.round(V * skala) + 'px';
        frame.style.width = V + 'px';
        frame.style.height = Math.ceil(H / skala) + 'px';
        frame.style.transform = 'scale(' + skala + ')';
    }

    if (window.ResizeObserver) new ResizeObserver(ukur).observe(layar);
    window.addEventListener('resize', ukur);
    ukur();

    panel.querySelectorAll('.pv-mode button').forEach(function (b) {
        b.addEventListener('click', function () {
            mode = b.getAttribute('data-mode');
            panel.querySelectorAll('.pv-mode button').forEach(function (o) {
                o.setAttribute('aria-pressed', o === b ? 'true' : 'false');
            });
            ukur();
        });
    });

    // Di layar sempit pratinjau menempel di atas; bisa dilipat supaya form lebih lega.
    var lipat = document.getElementById('pv-lipat');
    lipat.addEventListener('click', function () {
        var tutup = panel.classList.toggle('tutup');
        lipat.textContent = tutup ? 'Tampilkan' : 'Sembunyikan';
        lipat.setAttribute('aria-expanded', tutup ? 'false' : 'true');
        if (!tutup) ukur();
    });

    /* ---------- Simpan ---------- */

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        if (!form.reportValidity()) return;

        tombolSimpan.disabled = true;
        setStatus('Menyimpan…', '');

        fetch(form.action, {
            method: 'POST',
            body: new FormData(form),
            headers: { 'Accept': 'application/json' },
            credentials: 'same-origin'
        })
            .then(function (r) {
                return r.json().catch(function () { return { ok: false, pesan: 'Balasan server tidak dikenali. Muat ulang halaman.' }; });
            })
            .then(function (d) {
                if (!d.ok) { setStatus(d.pesan || 'Gagal menyimpan.', 'galat'); return; }

                // Gambar yang baru tersimpan jadi gambar "resmi"; kosongkan kolom file-nya.
                Object.keys(d.gambar || {}).forEach(function (nama) {
                    var wadah = form.querySelector('.ed-foto[data-foto="' + nama + '"]');
                    if (!wadah) return;
                    wadah.setAttribute('data-path', d.gambar[nama]);
                    wadah.querySelector('input[type="file"]').value = '';
                    wadah.querySelector('.ed-thumb').src = '../' + d.gambar[nama];
                    if (blobAktif[nama]) { URL.revokeObjectURL(blobAktif[nama]); delete blobAktif[nama]; }
                    sumberFoto[nama] = d.gambar[nama];
                });
                kotor = false;
                var jam = new Date().toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' });
                setStatus('Tersimpan pukul ' + jam + '.', 'ok');
                jadwalkanKirim();
            })
            .catch(function () { setStatus('Tidak bisa terhubung ke server. Coba lagi.', 'galat'); })
            .then(function () { tombolSimpan.disabled = false; });
    });
})();
