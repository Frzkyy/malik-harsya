/*
 * Gift: tombol "Salin Nomor" untuk nomor rekening.
 * Memakai Clipboard API (perlu HTTPS atau localhost); kalau tidak ada, cadangan lewat execCommand('copy').
 */
(function () {
    'use strict';

    var tombol = document.getElementById('gift-bank-salin');
    if (!tombol) return;

    var teksAwal = tombol.textContent;
    var timer = 0;

    function kabari(teks) {
        tombol.textContent = teks;
        clearTimeout(timer);
        timer = setTimeout(function () { tombol.textContent = teksAwal; }, 2200);
    }

    function salinCadangan(teks) {
        var t = document.createElement('textarea');
        t.value = teks;
        t.setAttribute('readonly', '');
        t.style.cssText = 'position:fixed;top:0;left:0;opacity:0;';
        document.body.appendChild(t);
        t.select();
        t.setSelectionRange(0, teks.length);
        var ok = false;
        try { ok = document.execCommand('copy'); } catch (_) {}
        document.body.removeChild(t);
        return ok;
    }

    tombol.addEventListener('click', function () {
        var nomor = tombol.getAttribute('data-nomor') || '';
        if (!nomor) return;

        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(nomor).then(
                function () { kabari('Tersalin ✓'); },
                function () { kabari(salinCadangan(nomor) ? 'Tersalin ✓' : 'Gagal menyalin'); }
            );
        } else {
            kabari(salinCadangan(nomor) ? 'Tersalin ✓' : 'Gagal menyalin');
        }
    });
})();
