const isi = document.querySelector("#isi");
const pancasila = document.querySelector("#pancasila");
const tampilkanNama = document.querySelector("#tampilkan-nama");

const nama = prompt("Tuliskan nama kamu");

tampilkanNama.innerHTML = `
<p><b>Nama saya ${nama}, saya akan mengamalkan Pancasila dan UUD 1945 sebagai Dasar Negara</b> </p>
`;


isi.innerHTML = `
<p><span class='latar-ungu'><b>Bahwa sesungguhnya kemerdekaan itu ialah hak segala bangsa dan oleh sebab itu,</b></span> maka penjajahan di atas dunia harus dihapuskan, karena tidak sesuai dengan peri-kemanusiaan dan peri-keadilan.</p>
<p><span class='latar-merah'><b>Dan perjuangan pergerakan kemerdekaan Indonesia telah sampailah kepada saat</b></span> yang membahagiakan dengan selamat sentausa mengantarkan rakyat Indonesia ke depan pintu gerbang kemerdekaan Negara Indonesia yang merdeka, bersatu, berdaulat, adil dan makmur.</p>
<p><span class='latar-hijau'>Atas berkat rakhmat Allah yang maha kuasa dan dengan didorongkan oleh keinginan</span> luhur supaya berkehidupan kebangsaan yang bebas, maka rakyat Indonesia menyatakan dengan ini kemerdekaannya.</p>
<p><span class='latar-biru tulisan-putih'><b>Kemudian dari pada itu untuk membentuk suatu Pemerintah Negara Indonesia yang</b></span> melindungi segenap bangsa Indonesia dan seluruh tumpah darah Indonesia dan untuk memajukan kesejahteraan umum, mencerdaskan kehidupan bangsa dan ikut melaksanakan ketertiban dunia yang berdasarkan kemerdekaan, perdamaian abadi dan keadilan sosial, maka disusunlah Kemerdekaan Kebangsaan Indonesia itu dalam suatu susunan Negara Republik Indonesia, yang berkedaulatan rakyat dengan berdasar kepada: Ketuhanan Yang Maha Esa, Kemanusiaan yang adil dan beradab, persatuan Indonesia dan kerakyatan yang dipimpin oleh hikmat kebijaksanaan dalam permusyawaratan/perwakilan, serta dengan mewujudkan suatu keadilan sosial bagi seluruh rakyat Indonesia.</p>
`;

pancasila.innerHTML = `
<ol>
     <li>Ketuhanan yang Maha Esa</li>
     <li>Kemanusiaan yang adil dan beradab</li>
     <li>Persatuan Indonesia</li>
     <li>Kerakyatan yang dipimpin oleh hikmat kebijaksanaan dalam permusyawaratan/perwakilan, serta</li>
     <li>Keadilan sosial bagi seluruh rakyat Indonesia</li>
</ol>
`;


