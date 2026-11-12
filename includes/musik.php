<?php
// Pemutar musik latar (dipakai index.php & login.php). File: media/musik/lagu.mp3
$musik_file = __DIR__ . '/../media/musik/lagu-web.mp3';
if (!is_file($musik_file)) return;
?>

<style>
#musik{position:fixed;right:var(--s3);bottom:var(--s3);z-index:60;display:flex;align-items:center;gap:var(--s2);padding:6px var(--s3) 6px 6px;border:1px solid var(--kuning);border-radius:999px;background:rgba(242,243,240,.92);backdrop-filter:blur(6px);box-shadow:0 6px 20px rgba(27,42,65,.18);font-family:"Cormorant Garamond",serif;color:var(--hijau);cursor:pointer;transition:transform .2s}
#musik:hover{transform:translateY(-2px)}
#musik .m-ikon{width:38px;height:38px;border-radius:50%;background:var(--hijau);color:var(--kapur);display:grid;place-items:center;font-size:14px}
#musik .m-teks{font-size:var(--f0);font-weight:600;letter-spacing:.03em}
#musik.main .m-ikon{animation:m-putar 6s linear infinite}
@keyframes m-putar{to{transform:rotate(360deg)}}
@media(max-width:480px){#musik .m-teks{display:none;}#musik{padding:6px}}
</style>

<audio id="musik-audio" src="media/musik/lagu-web.mp3" loop preload="metadata"></audio>
<button type="button" id="musik" aria-label="Putar atau jeda musik"><span class="m-ikon">&#9835;</span><span class="m-teks">Musik</span></button>

<script>
(function () {
    var a = document.getElementById('musik-audio'), b = document.getElementById('musik');
    var ikon = b.querySelector('.m-ikon'), teks = b.querySelector('.m-teks');
    var jeda = localStorage.getItem('musik_jeda') === '1';
    function ui() {
        var main = !a.paused;
        b.classList.toggle('main', main);
        ikon.innerHTML = main ? '&#10074;&#10074;' : '&#9654;';
        teks.textContent = main ? 'Jeda' : 'Putar';
    }
    function mulai() { a.play().then(ui).catch(function () {}); }
    a.addEventListener('loadedmetadata', function () {
        var t = parseFloat(localStorage.getItem('musik_t'));
        if (t > 0 && t < a.duration) a.currentTime = t;
    }, { once: true });
    a.addEventListener('timeupdate', function () { localStorage.setItem('musik_t', a.currentTime); });
    a.addEventListener('play', ui); a.addEventListener('pause', ui);
    b.addEventListener('click', function () {
        if (a.paused) { localStorage.setItem('musik_jeda', '0'); mulai(); }
        else { localStorage.setItem('musik_jeda', '1'); a.pause(); }
    });
    if (!jeda) {
        mulai();
        // Browser memblokir autoplay: mulai saat interaksi pertama.
        ['click', 'touchstart', 'keydown'].forEach(function (ev) {
            document.addEventListener(ev, function f() {
                if (a.paused && localStorage.getItem('musik_jeda') !== '1') mulai();
                document.removeEventListener(ev, f);
            }, { once: true });
        });
    }
    ui();
})();
</script>