// =========================================================
// FITUR 1: TOMBOL PENGHITUNG (COUNTER)
// =========================================================

// Ambil elemen yang dibutuhkan dari HTML
const counterValue = document.getElementById('counterValue');
const counterButton = document.getElementById('counterButton');

// Simpan nilai counter di dalam variabel (state)
let count = 0;

// Tambahkan event listener pada tombol (bukan inline onclick)
counterButton.addEventListener('click', function () {
  count = count + 1; // tambah 1 setiap kali diklik
  counterValue.textContent = count; // perbarui tampilan angka di halaman
});


// =========================================================
// FITUR 2: VALIDASI FORM DATA MAHASISWA
// =========================================================

// Ambil elemen form dan input-inputnya
const contactForm = document.getElementById('contactForm');
const namaInput = document.getElementById('nama');
const nimInput = document.getElementById('nim');
const jurusanInput = document.getElementById('jurusan');
const fotoProfilInput = document.getElementById('fotoProfil');
const formError = document.getElementById('formError');

// Ambil elemen kartu mahasiswa yang akan diperbarui setelah submit berhasil
const profileName = document.getElementById('profileName');
const profileRole = document.getElementById('profileRole');
const profilePhoto = document.getElementById('profilePhoto');

// Fungsi bantuan: hapus tanda "invalid" dan pesan sebelumnya
function resetValidasi() {
  namaInput.classList.remove('invalid');
  nimInput.classList.remove('invalid');
  jurusanInput.classList.remove('invalid');
  formError.classList.remove('success');
  formError.textContent = '';
}

// Fungsi bantuan: tampilkan pesan error dan tandai input yang salah
function tampilkanError(pesan, inputBermasalah) {
  formError.textContent = pesan;
  if (inputBermasalah) {
    inputBermasalah.classList.add('invalid');
  }
}

// Tambahkan event listener pada saat form disubmit
contactForm.addEventListener('submit', function (event) {
  // Mencegah form mengirim data ke server (karena tidak ada backend)
  event.preventDefault();

  // Reset pesan dan tanda invalid setiap kali submit dicoba
  resetValidasi();

  // Ambil nilai input dan hilangkan spasi di awal/akhir
  const namaValue = namaInput.value.trim();
  const nimValue = nimInput.value.trim();
  const jurusanValue = jurusanInput.value.trim();

  // --- Validasi Nama ---
  if (namaValue === '') {
    tampilkanError('Nama tidak boleh kosong.', namaInput);
    return;
  }

  if (namaValue.length < 3) {
    tampilkanError('Nama minimal harus 3 karakter.', namaInput);
    return;
  }

  // --- Validasi NIM ---
  if (nimValue === '') {
    tampilkanError('NIM tidak boleh kosong.', nimInput);
    return;
  }

  // NIM harus tepat 11 digit dan hanya berisi angka
  const polaNim = /^[0-9]{11}$/;
  if (!polaNim.test(nimValue)) {
    tampilkanError('NIM harus terdiri dari 11 digit angka.', nimInput);
    return;
  }

  // 2 digit pertama = tahun masuk (00 - 26, karena tahun berjalan adalah 2026)
  const tahunMasuk = parseInt(nimValue.substring(0, 2), 10);
  if (tahunMasuk < 0 || tahunMasuk > 26) {
    tampilkanError('2 digit pertama NIM (tahun masuk) tidak valid. Contoh: 26 untuk angkatan 2026.', nimInput);
    return;
  }

  // Digit ke-3 dan ke-4 = kode fakultas (00 - 10)
  const kodeFakultas = parseInt(nimValue.substring(2, 4), 10);
  if (kodeFakultas < 0 || kodeFakultas > 10) {
    tampilkanError('Digit ke-3 dan ke-4 NIM (kode fakultas) harus di antara 00-10.', nimInput);
    return;
  }

  // --- Validasi Jurusan ---
  if (jurusanValue === '') {
    tampilkanError('Jurusan tidak boleh kosong.', jurusanInput);
    return;
  }

  if (jurusanValue.length < 3) {
    tampilkanError('Jurusan minimal harus 3 karakter.', jurusanInput);
    return;
  }

  // --- Jika semua validasi lolos ---
  formError.classList.add('success');
  formError.textContent = 'Form berhasil dikirim. Kartu mahasiswa telah diperbarui!';

  // Perbarui kartu mahasiswa dengan data yang baru disubmit.
  // Bullet (•) ditulis langsung sebagai teks, digabung memakai textContent
  // agar lebih aman daripada innerHTML.
  profileName.textContent = namaValue;
  profileRole.textContent = nimValue + ' \u2022 ' + jurusanValue;

  // Foto profil bersifat opsional. Jika pengguna memilih file gambar,
  // baca isinya dengan FileReader lalu tampilkan sebagai foto profil.
  const fileFoto = fotoProfilInput.files[0];

  if (fileFoto) {
    const pembaca = new FileReader();

    // Fungsi ini dijalankan setelah gambar selesai dibaca
    pembaca.onload = function (e) {
      profilePhoto.src = e.target.result;
      // Tandai bahwa ini foto asli pengguna, supaya tampil bulat + berborder
      profilePhoto.classList.add('has-photo');
    };

    pembaca.readAsDataURL(fileFoto);
  }
  // Jika tidak ada file yang dipilih, foto profil dibiarkan seperti sebelumnya
  // (tidak diubah, dan gaya border-nya juga tidak diubah)

  // Kosongkan kembali form setelah berhasil "dikirim"
  contactForm.reset();
});


// =========================================================
// FITUR 3: UCAPAN DINAMIS BERDASARKAN WAKTU
// =========================================================

const greetingElement = document.getElementById('greeting');

function tampilkanUcapan() {
  const sekarang = new Date();
  const jam = sekarang.getHours(); // angka 0 - 23

  let ucapan;

  if (jam >= 0 && jam <= 10) {
    ucapan = 'Selamat Pagi';
  } else if (jam >= 11 && jam <= 14) {
    ucapan = 'Selamat Siang';
  } else if (jam >= 15 && jam <= 17) {
    ucapan = 'Selamat Sore';
  } else {
    ucapan = 'Selamat Malam';
  }

  greetingElement.textContent = ucapan;
}


// =========================================================
// FITUR TAMBAHAN: TOMBOL SETTING TEMA GELAP/TERANG
// =========================================================

const themeToggle = document.getElementById('themeToggle');

// Fungsi untuk menerapkan tema ke halaman dan memperbarui teks tombol
function terapkanTema(tema) {
  if (tema === 'dark') {
    document.body.setAttribute('data-theme', 'dark');
    themeToggle.textContent = '☀️ Mode Terang';
  } else {
    document.body.removeAttribute('data-theme');
    themeToggle.textContent = '🌙 Mode Gelap';
  }
}

// Saat tombol tema diklik: tukar antara gelap <-> terang
themeToggle.addEventListener('click', function () {
  const temaSaatIni = document.body.getAttribute('data-theme');
  const temaBaru = temaSaatIni === 'dark' ? 'light' : 'dark';

  terapkanTema(temaBaru);

  // Simpan pilihan tema di localStorage agar tetap tersimpan saat halaman dibuka lagi
  localStorage.setItem('tema', temaBaru);
});


// =========================================================
// JALANKAN SAAT HALAMAN SELESAI DIMUAT
// =========================================================

document.addEventListener('DOMContentLoaded', function () {
  tampilkanUcapan();

  // Ambil tema yang tersimpan sebelumnya (jika ada), default ke terang
  const temaTersimpan = localStorage.getItem('tema') || 'light';
  terapkanTema(temaTersimpan);
});
