/*
 * Editor "Cerita Kami" (dashboard -> Undangan -> Cerita Kami): 2 sampai 8 cerita,
 * tiap cerita punya judul, deskripsi, dan foto; urutan diatur dengan tombol naik/turun.
 * Posisi foto kiri/kanan di undangan bergantian otomatis mengikuti urutan.
 *
 * Kolom form memakai nama cerita[<kunci>][judul|deskripsi] dan cerita_foto[<kunci>] (urutan DOM = urutan simpan),
 * jadi ikut terkirim bersama form utama (lihat editor-undangan.js -> modul, dashboard/undangan_aksi.php).
 * Kunci: c<id> = cerita yang sudah tersimpan, n<angka> = cerita baru.
 */
(function () {
    'use strict';

    var wadah = document.getElementById('cr');
    if (!wadah || !window.EditorUndangan) return;

    var MIN = +wadah.getAttribute('data-min');
    var MAKS = +wadah.getAttribute('data-maks');
    var MAKS_BYTE = 12 * 1024 * 1024;
    var CADANGAN = [];
    var awal = [];
    try {
        CADANGAN = JSON.parse(wadah.getAttribute('data-cadangan') || '[]');
        awal = JSON.parse(wadah.getAttribute('data-awal') || '[]');
    } catch (e) { /* biarkan kosong */ }

    var daftar = document.getElementById('cr-daftar');
    var tombolTambah = document.getElementById('cr-tambah');
    var hitung = document.getElementById('cr-hitung');
    var noBaru = 0;

    function buat(tag, kelas, teks) {
        var el = document.createElement(tag);
        if (kelas) el.className = kelas;
        if (teks) el.textContent = teks;
        return el;
    }

    function kartuSemua() { return Array.prototype.slice.call(daftar.children); }

    /** Pasang nama kolom sesuai kunci kartu (dipanggil juga saat kunci berubah setelah simpan). */
    function setKunci(kartu, k) {
        kartu.setAttribute('data-k', k);
        kartu.querySelectorAll('[data-f]').forEach(function (el) {
            var f = el.getAttribute('data-f');
            el.name = f === 'foto' ? 'cerita_foto[' + k + ']' : 'cerita[' + k + '][' + f + ']';
        });
    }

    /** Foto yang dipakai pratinjau/thumbnail: pilihan baru > foto tersimpan > foto contoh (cerita baru). */
    function srcFoto(kartu, indeks) {
        if (kartu._blob) return kartu._blob;
        if (kartu._foto) return kartu._foto;
        return CADANGAN[indeks % CADANGAN.length] || '';
    }

    function buatKartu(c) {
        var kartu = buat('div', 'cr-kartu');
        kartu._foto = c.foto || '';      // path di server (kosong untuk cerita baru)
        kartu._blob = '';                // blob URL foto yang baru dipilih
        kartu._file = null;

        var kepala = buat('div', 'cr-kepala');
        kepala.appendChild(buat('strong', 'cr-no'));
        var aksi = buat('span', 'cr-aksi');
        [['cr-naik', '↑', 'Naikkan cerita'], ['cr-turun', '↓', 'Turunkan cerita']].forEach(function (b) {
            var t = buat('button', 'cr-tombol ' + b[0], b[1]);
            t.type = 'button'; t.setAttribute('aria-label', b[2]); t.title = b[2];
            aksi.appendChild(t);
        });
        var hapus = buat('button', 'cr-tombol cr-hapus', 'Hapus');
        hapus.type = 'button';
        aksi.appendChild(hapus);
        kepala.appendChild(aksi);
        kartu.appendChild(kepala);

        var lJudul = buat('label', 'ed-field');
        lJudul.appendChild(buat('span', 'ed-label', 'Judul cerita'));
        var judul = buat('input'); judul.type = 'text'; judul.maxLength = 80; judul.required = true;
        judul.setAttribute('data-f', 'judul'); judul.setAttribute('data-modul', ''); judul.autocomplete = 'off';
        judul.value = c.judul || '';
        lJudul.appendChild(judul);
        lJudul.appendChild(buat('small', 'ed-ket', 'Boleh memuat tahun, mis. Awal Bertemu (2019)'));
        kartu.appendChild(lJudul);

        var lDesk = buat('label', 'ed-field');
        lDesk.appendChild(buat('span', 'ed-label', 'Deskripsi cerita'));
        var desk = buat('textarea'); desk.rows = 4; desk.maxLength = 500;
        desk.setAttribute('data-f', 'deskripsi'); desk.setAttribute('data-modul', '');
        desk.value = c.deskripsi || '';
        lDesk.appendChild(desk);
        kartu.appendChild(lDesk);

        var foto = buat('div', 'ed-foto');
        var thumb = buat('img', 'ed-thumb'); thumb.alt = '';
        foto.appendChild(thumb);
        var lFoto = buat('label', 'ed-field');
        lFoto.appendChild(buat('span', 'ed-label', 'Foto cerita'));
        var berkas = buat('input'); berkas.type = 'file'; berkas.accept = 'image/jpeg,image/png,image/webp';
        berkas.setAttribute('data-f', 'foto'); berkas.setAttribute('data-modul', '');
        lFoto.appendChild(berkas);
        lFoto.appendChild(buat('small', 'ed-ket', 'JPG/PNG/WebP, maks. 12 MB. Kosongkan kalau tidak ingin mengganti.'));
        foto.appendChild(lFoto);
        kartu.appendChild(foto);

        setKunci(kartu, c.k);
        return kartu;
    }

    /** Perbarui nomor, thumbnail, dan status tombol semua kartu. */
    function segarkan() {
        var semua = kartuSemua();
        semua.forEach(function (kartu, i) {
            kartu.querySelector('.cr-no').textContent = 'Cerita ' + (i + 1);
            kartu.querySelector('.cr-naik').disabled = i === 0;
            kartu.querySelector('.cr-turun').disabled = i === semua.length - 1;
            kartu.querySelector('.cr-hapus').disabled = semua.length <= MIN;
            kartu.querySelector('.cr-hapus').title = semua.length <= MIN ? 'Minimal ' + MIN + ' cerita' : 'Hapus cerita ini';

            var src = srcFoto(kartu, i);
            var img = kartu.querySelector('.ed-thumb');
            var tampil = kartu._blob ? src : '../' + src;
            if (img.getAttribute('src') !== tampil) img.src = tampil;
        });
        hitung.textContent = semua.length + ' / ' + MAKS + ' cerita';
        tombolTambah.disabled = semua.length >= MAKS;
        tombolTambah.title = semua.length >= MAKS ? 'Maksimal ' + MAKS + ' cerita' : '';
    }

    function berubah() { segarkan(); window.EditorUndangan.berubah(); }

    /* ---------- Aksi ---------- */

    daftar.addEventListener('click', function (e) {
        var tombol = e.target.closest('button');
        if (!tombol) return;
        var kartu = tombol.closest('.cr-kartu');

        if (tombol.classList.contains('cr-naik') && kartu.previousElementSibling) {
            daftar.insertBefore(kartu, kartu.previousElementSibling);
            berubah();
        } else if (tombol.classList.contains('cr-turun') && kartu.nextElementSibling) {
            daftar.insertBefore(kartu.nextElementSibling, kartu);
            berubah();
        } else if (tombol.classList.contains('cr-hapus') && kartuSemua().length > MIN) {
            if (kartu._blob) URL.revokeObjectURL(kartu._blob);
            kartu.remove();
            berubah();
        }
    });

    daftar.addEventListener('change', function (e) {
        var el = e.target;
        if (el.type !== 'file') return;
        var kartu = el.closest('.cr-kartu');
        var ket = el.parentNode.querySelector('.ed-ket');
        var wadahFoto = el.closest('.ed-foto');
        var asli = 'JPG/PNG/WebP, maks. 12 MB. Kosongkan kalau tidak ingin mengganti.';
        wadahFoto.classList.remove('ada-galat');
        ket.textContent = asli;

        if (kartu._blob) { URL.revokeObjectURL(kartu._blob); kartu._blob = ''; }
        kartu._file = null;

        var f = el.files && el.files[0];
        if (f) {
            var galat = !/^image\/(jpeg|png|webp)$/.test(f.type) ? 'Format tidak didukung. Gunakan JPG, PNG, atau WebP.'
                : f.size > MAKS_BYTE ? 'Ukuran file terlalu besar (maksimal 12 MB).' : '';
            if (galat) {
                el.value = '';
                wadahFoto.classList.add('ada-galat');
                ket.textContent = galat;
            } else {
                kartu._file = f;
                kartu._blob = URL.createObjectURL(f);
            }
        }
        berubah();
    });

    tombolTambah.addEventListener('click', function () {
        if (kartuSemua().length >= MAKS) return;
        var kartu = buatKartu({ k: 'n' + (++noBaru), judul: 'Cerita baru', deskripsi: '', foto: '' });
        daftar.appendChild(kartu);
        berubah();
        var judul = kartu.querySelector('[data-f="judul"]');
        judul.focus();
        judul.select();
    });

    /* ---------- Hubungan dengan editor utama ---------- */

    window.EditorUndangan.daftar({
        kumpulkan: function () {
            return {
                cerita: kartuSemua().map(function (kartu, i) {
                    return {
                        k: kartu.getAttribute('data-k'),
                        judul: kartu.querySelector('[data-f="judul"]').value,
                        deskripsi: kartu.querySelector('[data-f="deskripsi"]').value,
                        foto: srcFoto(kartu, i)
                    };
                })
            };
        },

        isiForm: function () { /* semua kolom (termasuk file) sudah ada di form */ },

        setelahSimpan: function (d) {
            var baru = d.cerita_baru || {};
            var ganti = d.cerita_foto || {};

            kartuSemua().forEach(function (kartu) {
                var kLama = kartu.getAttribute('data-k');
                var foto = null;

                if (baru[kLama]) { foto = baru[kLama].foto; setKunci(kartu, baru[kLama].k); }
                else if (ganti[kLama]) foto = ganti[kLama];

                if (foto !== null) {
                    if (kartu._blob) URL.revokeObjectURL(kartu._blob);
                    kartu._blob = '';
                    kartu._file = null;
                    kartu._foto = foto;
                    kartu.querySelector('input[type="file"]').value = '';
                }
            });
            segarkan();
        }
    });

    awal.forEach(function (c) { daftar.appendChild(buatKartu(c)); });
    segarkan();
})();
