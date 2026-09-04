const keterangan = document.querySelector("#keterangan");
const kotak = prompt("Tuliskan nama kamu.")

keterangan.innerHTML = `
<p><span class='perkenalan'>Nama saya ${kotak}, saya akan mengamalkan Pancasila dan UUD 1945 sebagai Dasar Negara</span></p>
`;

const isi = document.querySelector("#isi");

isi.innerHTML = `
<p><span class='p1'>Bahwa sesungguhnya kemerdekaan itu ialah hak segala bangsa dan oleh sebab itu,</span> maka penjajahan di atas dunia harus dihapuskan, karena tidak sesuai dengan peri-kemanusiaan dan peri-keadilan.</p>
<p><span class='p2'>Dan perjuangan pergerakan kemerdekaan Indonesia telah sampailah kepada saat</span> yang membahagiakan dengan selamat sentausa mengantarkan rakyat Indonesia ke depan pintu gerbang kemerdekaan Negara Indonesia yang merdeka, bersatu, berdaulat, adil dan makmur.</p>
<p><span class='p3'>Atas berkat rakhmat Allah yang maha kuasa dan dengan didorongkan oleh keinginan</span> luhur supaya berkehidupan kebangsaan yang bebas, maka rakyat Indonesia menyatakan dengan ini kemerdekaannya.</p>
<p><span class='p4'>Kemudian dari pada itu untuk membentuk suatu Pemerintah Negara Indonesia yang</span> melindungi segenap bangsa Indonesia dan seluruh tumpah darah Indonesia dan untuk memajukan kesejahteraan umum, mencerdaskan kehidupan bangsa dan ikut melaksanakan ketertiban dunia yang berdasarkan kemerdekaan, perdamaian abadi dan keadilan sosial, maka disusunlah Kemerdekaan Kebangsaan Indonesia itu dalam suatu susunan Negara Republik Indonesia, yang berkedaulatan rakyat dengan berdasar kepada: Ketuhanan Yang Maha Esa, Kemanusiaan yang adil dan beradab, persatuan Indonesia dan kerakyatan yang dipimpin oleh hikmat kebijaksanaan dalam permusyawaratan/perwakilan, serta dengan mewujudkan suatu keadilan sosial bagi seluruh rakyat Indonesia.</p>
`;


const sila = document.querySelector("#sila");

sila.innerHTML = `
<ol>
    <li>Ketuhanan yang Maha Esa</li>
    <li>Kemanusiaan yang adil dan beradab</li>
    <li>Persatuan Indonesia</li>
    <li>Kerakyatan yang dipimpin oleh hikmat kebijaksanaan dalam permusyawaratan/perwakilan, serta</li>
    <li>Keadilan sosial bagi seluruh rakyat Indonesia</li>
</ol>
`;