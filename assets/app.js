/* TestNauka – skrypt panelu administracyjnego */
(function () {
    'use strict';

    /* ---------------------------------------------------------------- */
    /* Kopiowanie kodu do schowka                                        */
    /* ---------------------------------------------------------------- */
    document.querySelectorAll('[data-copy]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const target = document.querySelector(btn.getAttribute('data-copy'));
            if (!target) return;

            const text = target.textContent.trim();
            const done = function () {
                const old = btn.textContent;
                btn.textContent = 'Skopiowano ✓';
                setTimeout(function () { btn.textContent = old; }, 1800);
            };

            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(text).then(done).catch(function () { fallbackCopy(text, done); });
            } else {
                fallbackCopy(text, done);
            }
        });
    });

    function fallbackCopy(text, done) {
        const ta = document.createElement('textarea');
        ta.value = text;
        ta.style.position = 'fixed';
        ta.style.opacity = '0';
        document.body.appendChild(ta);
        ta.select();
        try { document.execCommand('copy'); done(); } catch (e) { /* ignoruj */ }
        document.body.removeChild(ta);
    }

    /* ---------------------------------------------------------------- */
    /* Potwierdzenie akcji destrukcyjnych                               */
    /* ---------------------------------------------------------------- */
    document.querySelectorAll('form[data-confirm]').forEach(function (form) {
        form.addEventListener('submit', function (ev) {
            if (!window.confirm(form.getAttribute('data-confirm') || 'Na pewno?')) {
                ev.preventDefault();
            }
        });
    });

    /* ---------------------------------------------------------------- */
    /* Zaznacz wszystkie (np. lista slajdów do dodania)                  */
    /* ---------------------------------------------------------------- */
    document.querySelectorAll('[data-check-all]').forEach(function (master) {
        master.addEventListener('change', function () {
            const scope = master.closest('form') || document;
            scope.querySelectorAll('[data-check-item]').forEach(function (item) {
                item.checked = master.checked;
            });
        });
    });

    /* ---------------------------------------------------------------- */
    /* Mobilne menu (sidebar)                                           */
    /* ---------------------------------------------------------------- */
    const navToggle = document.getElementById('navToggle');
    const sidebar = document.getElementById('sidebar');
    if (navToggle && sidebar) {
        navToggle.addEventListener('click', function () {
            sidebar.classList.toggle('is-open');
        });
    }

    /* ---------------------------------------------------------------- */
    /* Generowanie kodu przez AJAX (api/kod.php)                        */
    /* ---------------------------------------------------------------- */
    document.querySelectorAll('[data-api-kod]').forEach(function (form) {
        form.addEventListener('submit', function (ev) {
            ev.preventDefault();

            const btn = form.querySelector('button[type="submit"]');
            const label = btn ? btn.textContent : '';
            if (btn) { btn.disabled = true; btn.textContent = 'Wysyłanie…'; }

            fetch(form.getAttribute('action'), {
                method: 'POST',
                body: new FormData(form),
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin'
            })
                .then(function (res) { return res.json().catch(function () { return {}; }); })
                .then(function (data) {
                    if (data && data.ok) {
                        showToast(data.message || 'Kod wysłany.', 'success');
                    } else {
                        showToast((data && data.message) || 'Nie udało się wysłać kodu.', 'error');
                    }
                })
                .catch(function () {
                    showToast('Błąd połączenia z serwerem.', 'error');
                })
                .finally(function () {
                    if (btn) { btn.disabled = false; btn.textContent = label; }
                });
        });
    });

    /* ---------------------------------------------------------------- */
    /* Powiadomienia (toast)                                            */
    /* ---------------------------------------------------------------- */
    function showToast(message, type) {
        const holder = document.createElement('div');
        holder.className = 'toast toast-' + (type === 'error' ? 'error' : 'success');
        holder.textContent = message;
        document.body.appendChild(holder);

        setTimeout(function () {
            holder.classList.add('is-hide');
            setTimeout(function () { holder.remove(); }, 300);
        }, 4500);
    }

    /* ---------------------------------------------------------------- */
    /* Rozwiązywanie testu – postęp i ostrzeżenie o brakujących          */
    /* ---------------------------------------------------------------- */
    const testForm = document.querySelector('form[data-test-progress]');
    if (testForm) {
        const total = parseInt(testForm.getAttribute('data-test-progress'), 10) || 0;
        const bar = document.getElementById('testProgressBar');
        const label = document.getElementById('testProgressLabel');

        function answeredCount() {
            const answered = new Set();
            testForm.querySelectorAll('input[data-answer]:checked').forEach(function (input) {
                const card = input.closest('.question-solve');
                if (card) answered.add(card);
            });
            return answered.size;
        }

        function updateTestProgress() {
            const answered = answeredCount();
            if (bar) bar.style.width = (total > 0 ? (answered / total) * 100 : 0) + '%';
            if (label) label.textContent = answered + ' / ' + total + ' odpowiedzi';
        }

        testForm.querySelectorAll('input[data-answer]').forEach(function (input) {
            input.addEventListener('change', function () {
                const card = input.closest('.question-solve');
                if (card) card.classList.toggle('is-answered', !!card.querySelector('input[data-answer]:checked'));
                updateTestProgress();
            });
        });

        testForm.addEventListener('submit', function (ev) {
            const missing = total - answeredCount();
            if (missing > 0 && !window.confirm('Nie odpowiedziano na ' + missing + ' z ' + total + ' pytań. Zakończyć test mimo to?')) {
                ev.preventDefault();
            }
        });

        updateTestProgress();
    }

    /* ---------------------------------------------------------------- */
    /* Podgląd slajdów – nawigacja                                      */
    /* ---------------------------------------------------------------- */
    const stage = document.getElementById('previewStage');
    if (stage) {
        const slides = Array.prototype.slice.call(stage.querySelectorAll('.slide'));
        const counter = document.getElementById('previewCounter');
        const prevBtn = document.getElementById('previewPrev');
        const nextBtn = document.getElementById('previewNext');
        const fsBtn = document.getElementById('previewFullscreen');
        const backLink = document.getElementById('previewBack');
        let index = 0;

        function show(i) {
            if (!slides.length) return;
            index = (i + slides.length) % slides.length;
            slides.forEach(function (s, n) { s.classList.toggle('is-current', n === index); });
            if (counter) counter.textContent = (index + 1) + ' / ' + slides.length;
        }

        if (prevBtn) prevBtn.addEventListener('click', function () { show(index - 1); });
        if (nextBtn) nextBtn.addEventListener('click', function () { show(index + 1); });

        /* Pełny ekran */
        function updateFullscreenButton() {
            if (!fsBtn) return;
            const fsElement = document.fullscreenElement || document.webkitFullscreenElement || document.msFullscreenElement;
            const isFs = !!fsElement;
            fsBtn.textContent = isFs ? '⛶' : '⛶';
            fsBtn.setAttribute('aria-label', isFs ? 'Wyjdź z pełnego ekranu' : 'Pełny ekran');
            fsBtn.title = isFs ? 'Wyjdź z pełnego ekranu' : 'Pełny ekran (F)';
        }

        function toggleFullscreen() {
            const elem = document.querySelector('.page-preview');
            if (!elem) return;
            if (!document.fullscreenElement && !document.webkitFullscreenElement && !document.msFullscreenElement) {
                if (elem.requestFullscreen) {
                    elem.requestFullscreen().catch(function () { /* ignoruj */ });
                } else if (elem.webkitRequestFullscreen) {
                    elem.webkitRequestFullscreen();
                } else if (elem.msRequestFullscreen) {
                    elem.msRequestFullscreen();
                }
            } else {
                if (document.exitFullscreen) {
                    document.exitFullscreen().catch(function () { /* ignoruj */ });
                } else if (document.webkitExitFullscreen) {
                    document.webkitExitFullscreen();
                } else if (document.msExitFullscreen) {
                    document.msExitFullscreen();
                }
            }
        }

        if (fsBtn) {
            fsBtn.addEventListener('click', toggleFullscreen);
            document.addEventListener('fullscreenchange', updateFullscreenButton);
            document.addEventListener('webkitfullscreenchange', updateFullscreenButton);
            document.addEventListener('msfullscreenchange', updateFullscreenButton);
        }

        document.addEventListener('keydown', function (ev) {
            if (ev.key === 'ArrowRight' || ev.key === ' ' || ev.key === 'PageDown') {
                ev.preventDefault(); show(index + 1);
            } else if (ev.key === 'ArrowLeft' || ev.key === 'PageUp') {
                ev.preventDefault(); show(index - 1);
            } else if (ev.key === 'Escape') {
                if (document.fullscreenElement || document.webkitFullscreenElement || document.msFullscreenElement) {
                    ev.preventDefault();
                    if (document.exitFullscreen) document.exitFullscreen().catch(function () {});
                    else if (document.webkitExitFullscreen) document.webkitExitFullscreen();
                    else if (document.msExitFullscreen) document.msExitFullscreen();
                } else if (backLink) {
                    window.location.href = backLink.getAttribute('href');
                }
            } else if (ev.key === 'f' || ev.key === 'F') {
                if (!ev.ctrlKey && !ev.metaKey && !ev.altKey) {
                    ev.preventDefault();
                    toggleFullscreen();
                }
            }
        });

        show(0);
    }
})();
