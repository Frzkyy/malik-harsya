/*
 * Editor Undangan: bagian "Musik Latar" (dashboard, tab Undangan).
 *  - Memeriksa file musik (jenis & ukuran) sebelum disimpan, lalu memutarnya di pemutar kecil supaya admin bisa mendengarkan.
 *  - Memberi tahu pratinjau apakah tombol musik harus tampil (musik aktif + ada file).
 *  - Mendaftar sebagai modul lewat window.EditorUndangan.daftar (lihat editor-undangan.js).
 * Bagian Gift memakai mekanisme form biasa (teks + ed_foto), jadi tidak perlu kode di sini.
 */
(function () {
    'use strict';

    var wadah = document.getElementById('musik-editor');
    var input = document.getElementById('musik-berkas');
    if (!wadah || !input || !window.EditorUndangan) return;

    var MAKS = 10 * 1024 * 1024;                       // sama dengan UNGGAH_AUDIO_MAKS di includes/upload.php
    var EKSTENSI = /\.(mp3|ogg|m4a|wav)$/i;
    var JENIS = /^audio\/(mpeg|mp3|ogg|mp4|x-m4a|m4a|wav|x-wav|wave)$/i;

    var pemutar = document.getElementById('musik-dengar');
    var kosong = document.getElementById('musik-kosong');
    var ket = document.getElementById('musik-ket');
    var hapusWadah = document.getElementById('musik-hapus-wadah');
    var hapus = hapusWadah.querySelector('input[type="checkbox"]');
    var aktif = wadah.querySelector('input[type="checkbox"][name="musik_aktif"]');

    var path = wadah.getAttribute('data-path') || '';  // file yang sudah tersimpan
    var blob = null;                                   // blob URL file yang baru dipilih
    var KET_AWAL = ket.textContent;

    function ada() { return !!blob || (path !== '' && !hapus.checked); }

    function tampilPemutar(src) {
        pemutar.pause();
        if (src) { pemutar.src = src; pemutar.hidden = false; kosong.hidden = true; }
        else { pemutar.removeAttribute('src'); pemutar.load(); pemutar.hidden = true; kosong.hidden = false; }
    }

    function tolak(pesan) {
        input.value = '';
        ket.textContent = pesan;
        ket.style.color = '#b3261e';
        ket.style.fontWeight = '600';
    }

    input.addEventListener('change', function () {
        ket.textContent = KET_AWAL;
        ket.style.color = ket.style.fontWeight = '';

        var f = input.files && input.files[0];
        if (blob) { URL.revokeObjectURL(blob); blob = null; }

        if (!f) {                                       // pilihan dibatalkan -> kembali ke file tersimpan
            tampilPemutar(path && !hapus.checked ? '../' + path : '');
            window.EditorUndangan.berubah();
            return;
        }
        if (!EKSTENSI.test(f.name) || (f.type && !JENIS.test(f.type))) {
            tolak('Format tidak didukung. Gunakan MP3, OGG, M4A, atau WAV.');
            tampilPemutar(path && !hapus.checked ? '../' + path : '');
            return;
        }
        if (f.size > MAKS) {
            tolak('Ukuran file terlalu besar (maksimal 10 MB).');
            tampilPemutar(path && !hapus.checked ? '../' + path : '');
            return;
        }

        blob = URL.createObjectURL(f);
        hapus.checked = false;
        tampilPemutar(blob);
        window.EditorUndangan.berubah();
    });

    hapus.addEventListener('change', function () {
        if (hapus.checked) {
            input.value = '';
            if (blob) { URL.revokeObjectURL(blob); blob = null; }
            tampilPemutar('');
        } else {
            tampilPemutar(path ? '../' + path : '');
        }
        window.EditorUndangan.berubah();
    });

    window.EditorUndangan.daftar({
        // Dikirim ke pratinjau: tombol musik tampil kalau musik aktif dan ada file.
        kumpulkan: function () { return { musik_ada: ada() && !!(aktif && aktif.checked) }; },
        isiForm: function () {},
        setelahSimpan: function (d) {
            var baru = d.gambar && d.gambar.musik_file;
            if (baru) {
                path = baru;
                if (blob) { URL.revokeObjectURL(blob); blob = null; }
                input.value = '';
                tampilPemutar('../' + path);
                hapusWadah.hidden = false;
                hapus.checked = false;
            } else if ((d.berkas_hapus || []).indexOf('musik_file') !== -1) {
                path = '';
                input.value = '';
                tampilPemutar('');
                hapusWadah.hidden = true;
                hapus.checked = false;
            }
            wadah.setAttribute('data-path', path);
        }
    });
})();
