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
    /* Modale (dialog)                                                  */
    /* ---------------------------------------------------------------- */
    window.openModal = function (modalId) {
        const modal = document.getElementById(modalId);
        if (modal && typeof modal.showModal === 'function') {
            console.log('[Modal] openModal() otwieram:', modalId);
            modal.showModal();
        } else {
            console.warn('[Modal] openModal() nie znaleziono:', modalId, modal);
        }
    };

    function initModals() {
        console.log('[Modal] Inicjalizacja modali...');
        document.querySelectorAll('[data-modal-open]').forEach(function (trigger) {
            trigger.addEventListener('click', function () {
                const selector = trigger.getAttribute('data-modal-open');
                console.log('[Modal] Kliknięto trigger:', selector);
                const modal = document.querySelector(selector);
                if (modal && typeof modal.showModal === 'function') {
                    console.log('[Modal] Otwieram modal:', modal.id);
                    modal.showModal();
                } else {
                    console.warn('[Modal] Modal nie znaleziony lub showModal nieobsługiwany:', selector, modal);
                }
            });
        });

        document.querySelectorAll('[data-modal-close]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                const modal = btn.closest('dialog');
                if (modal) {
                    console.log('[Modal] Zamykam modal:', modal.id);
                    modal.close();
                }
            });
        });

        document.querySelectorAll('dialog').forEach(function (modal) {
            modal.addEventListener('click', function (ev) {
                const rect = modal.getBoundingClientRect();
                const isInDialog = ev.clientX >= rect.left && ev.clientX <= rect.right &&
                                   ev.clientY >= rect.top && ev.clientY <= rect.bottom;
                if (!isInDialog) {
                    console.log('[Modal] Kliknięcie w backdrop, zamykam:', modal.id);
                    modal.close();
                }
            });
        });

        document.addEventListener('keydown', function (ev) {
            if (ev.key === 'Escape') {
                document.querySelectorAll('dialog[open]').forEach(function (m) {
                    console.log('[Modal] Escape, zamykam:', m.id);
                    m.close();
                });
            }
        });
        console.log('[Modal] Inicjalizacja zakończona');
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initModals);
    } else {
        initModals();
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
        const soundBtn = document.getElementById('previewSound');
        const backLink = document.getElementById('previewBack');
        let index = 0;

        /* Krótki dźwięk przy zmianie slajdu (Web Audio, bez plików). */
        let audioCtx = null;
        let soundOn = true;
        try {
            soundOn = window.localStorage.getItem('tn-slide-sound') !== '0';
        } catch (err) {
            soundOn = true;
        }

        /**
         * Przyjemny dźwięk przejścia: delikatny „swish” (szum z filtrem)
         * plus krótki, dzwoniący akcent. W zależności od kierunku dźwięk
         * unosi się (następny slajd) albo opada (poprzedni).
         */
        function playSlideSound(direction) {
            if (!soundOn) return;
            const AudioCtx = window.AudioContext || window.webkitAudioContext;
            if (!AudioCtx) return;

            try {
                if (!audioCtx) audioCtx = new AudioCtx();
                if (audioCtx.state === 'suspended' && audioCtx.resume) {
                    audioCtx.resume();
                }

                const now = audioCtx.currentTime;
                const dir = direction < 0 ? -1 : 1;

                /* 1. Swish – krótki szum przestrajany filtrem pasmowym. */
                const sweep = 0.28;
                const buffer = audioCtx.createBuffer(1, Math.ceil(audioCtx.sampleRate * sweep), audioCtx.sampleRate);
                const data = buffer.getChannelData(0);
                for (let i = 0; i < data.length; i++) {
                    data[i] = Math.random() * 2 - 1;
                }

                const noise = audioCtx.createBufferSource();
                noise.buffer = buffer;

                const filter = audioCtx.createBiquadFilter();
                filter.type = 'bandpass';
                filter.Q.value = 1.4;
                filter.frequency.setValueAtTime(dir > 0 ? 650 : 2400, now);
                filter.frequency.exponentialRampToValueAtTime(dir > 0 ? 2600 : 600, now + 0.24);

                const noiseGain = audioCtx.createGain();
                noiseGain.gain.setValueAtTime(0.0001, now);
                noiseGain.gain.exponentialRampToValueAtTime(0.05, now + 0.035);
                noiseGain.gain.exponentialRampToValueAtTime(0.0001, now + 0.27);

                let noiseOut = noiseGain;
                let panner = null;
                if (audioCtx.createStereoPanner) {
                    panner = audioCtx.createStereoPanner();
                    panner.pan.setValueAtTime(-0.35 * dir, now);
                    panner.pan.linearRampToValueAtTime(0.35 * dir, now + 0.24);
                    noiseGain.connect(panner);
                    noiseOut = panner;
                }

                noise.connect(filter);
                filter.connect(noiseGain);
                noiseOut.connect(audioCtx.destination);
                noise.start(now);
                noise.stop(now + sweep);
                noise.addEventListener('ended', function () {
                    try {
                        noise.disconnect();
                        filter.disconnect();
                        noiseGain.disconnect();
                        if (panner) panner.disconnect();
                    } catch (err) { /* ignore */ }
                });

                /* 2. Dzwoniący akcent – dwie harmoniczne, cichy i szybko gasnący. */
                const base = dir > 0 ? 880 : 698.46;
                [1, 1.5].forEach(function (mult, n) {
                    const osc = audioCtx.createOscillator();
                    const gain = audioCtx.createGain();
                    const start = now + 0.025;

                    osc.type = 'sine';
                    osc.frequency.setValueAtTime(base * mult, start);
                    gain.gain.setValueAtTime(0.0001, start);
                    gain.gain.exponentialRampToValueAtTime(n === 0 ? 0.045 : 0.02, start + 0.015);
                    gain.gain.exponentialRampToValueAtTime(0.0001, start + (n === 0 ? 0.3 : 0.2));

                    osc.connect(gain);
                    gain.connect(audioCtx.destination);
                    osc.start(start);
                    osc.stop(start + 0.34);
                    osc.addEventListener('ended', function () {
                        try { osc.disconnect(); gain.disconnect(); } catch (err) { /* ignore */ }
                    });
                });
            } catch (err) {
                /* dźwięk jest opcjonalny – brak wsparcia nie może przerwać prezentacji */
            }
        }

        function updateSoundButton() {
            if (!soundBtn) return;
            soundBtn.textContent = soundOn ? '🔊' : '🔇';
            soundBtn.setAttribute('aria-pressed', soundOn ? 'true' : 'false');
            soundBtn.setAttribute('aria-label', soundOn ? 'Wyłącz dźwięk slajdów' : 'Włącz dźwięk slajdów');
            soundBtn.title = soundOn ? 'Dźwięk slajdów: włączony' : 'Dźwięk slajdów: wyłączony';
        }

        if (soundBtn) {
            soundBtn.addEventListener('click', function () {
                soundOn = !soundOn;
                try {
                    window.localStorage.setItem('tn-slide-sound', soundOn ? '1' : '0');
                } catch (err) { /* ignore */ }
                updateSoundButton();
                playSlideSound(1);
            });
        }
        updateSoundButton();

        function show(i) {
            if (!slides.length) return;
            if (i < 0 || i >= slides.length) return;
            const previous = index;
            index = i;
            slides.forEach(function (s, n) { s.classList.toggle('is-current', n === index); });
            if (counter) counter.textContent = (index + 1) + ' / ' + slides.length;
            if (prevBtn) {
                prevBtn.style.display = 'inline-flex';
                prevBtn.disabled = index <= 0;
            }
            if (nextBtn) {
                nextBtn.style.display = 'inline-flex';
                nextBtn.disabled = index >= slides.length - 1;
            }
            if (previous !== index) playSlideSound(index > previous ? 1 : -1);
        }

        if (prevBtn) {
            prevBtn.style.display = 'none';
            prevBtn.addEventListener('click', function () { 
                if (index <= 0) return;
                show(index - 1); 
            });
        }
        if (nextBtn) {
            nextBtn.style.display = slides.length > 1 ? 'inline-flex' : 'none';
            nextBtn.addEventListener('click', function () { 
                if (index >= slides.length - 1) return;
                show(index + 1); 
            });
        }

        /* Pełny ekran */
        function updateFullscreenButton() {
            if (!fsBtn) return;
            const fsElement = document.fullscreenElement || document.webkitFullscreenElement || document.msFullscreenElement;
            const isFs = !!fsElement;
            console.log('[Fullscreen] updateFullscreenButton, isFs=', isFs, 'element=', fsElement);
            fsBtn.textContent = isFs ? '⛶ Wyjdź' : '⛶ Pełny ekran';
            fsBtn.setAttribute('aria-label', isFs ? 'Wyjdź z pełnego ekranu' : 'Pełny ekran');
            fsBtn.title = isFs ? 'Wyjdź z pełnego ekranu' : 'Pełny ekran (F)';
        }

        function toggleFullscreen() {
            const elem = document.querySelector('.page-preview');
            if (!elem) {
                console.warn('[Fullscreen] .page-preview element not found');
                return;
            }
            const fsElement = document.fullscreenElement || document.webkitFullscreenElement || document.msFullscreenElement;
            if (!fsElement) {
                console.log('[Fullscreen] Requesting fullscreen on', elem);
                if (elem.requestFullscreen) {
                    elem.requestFullscreen().then(function() {
                        console.log('[Fullscreen] Fullscreen entered');
                    }).catch(function(err) {
                        console.error('[Fullscreen] requestFullscreen failed:', err);
                    });
                } else if (elem.webkitRequestFullscreen) {
                    elem.webkitRequestFullscreen();
                } else if (elem.msRequestFullscreen) {
                    elem.msRequestFullscreen();
                }
            } else {
                console.log('[Fullscreen] Exiting fullscreen');
                if (document.exitFullscreen) {
                    document.exitFullscreen().catch(function(err) {
                        console.error('[Fullscreen] exitFullscreen failed:', err);
                    });
                } else if (document.webkitExitFullscreen) {
                    document.webkitExitFullscreen();
                } else if (document.msExitFullscreen) {
                    document.msExitFullscreen();
                }
            }
        }

        if (fsBtn) {
            fsBtn.addEventListener('click', function(e) {
                e.preventDefault();
                console.log('[Fullscreen] Button clicked');
                toggleFullscreen();
            });
            document.addEventListener('fullscreenchange', function() {
                console.log('[Fullscreen] fullscreenchange event');
                updateFullscreenButton();
            });
            document.addEventListener('webkitfullscreenchange', function() {
                console.log('[Fullscreen] webkitfullscreenchange event');
                updateFullscreenButton();
            });
            document.addEventListener('msfullscreenchange', function() {
                console.log('[Fullscreen] msfullscreenchange event');
                updateFullscreenButton();
            });
        }

        document.addEventListener('keydown', function (ev) {
            if (ev.key === 'ArrowRight' || ev.key === ' ' || ev.key === 'PageDown') {
                ev.preventDefault();
                if (index >= slides.length - 1) return;
                show(index + 1);
            } else if (ev.key === 'ArrowLeft' || ev.key === 'PageUp') {
                ev.preventDefault(); 
                if (index <= 0) return;
                show(index - 1);
            } else if (ev.key === 'Escape') {
                const fsElement = document.fullscreenElement || document.webkitFullscreenElement || document.msFullscreenElement;
                if (fsElement) {
                    ev.preventDefault();
                    console.log('[Fullscreen] Escape in fullscreen, exiting');
                    if (document.exitFullscreen) document.exitFullscreen().catch(function () {});
                    else if (document.webkitExitFullscreen) document.webkitExitFullscreen();
                    else if (document.msExitFullscreen) document.msExitFullscreen();
                } else if (backLink) {
                    window.location.href = backLink.getAttribute('href');
                }
            } else if (ev.key === 'f' || ev.key === 'F') {
                if (!ev.ctrlKey && !ev.metaKey && !ev.altKey) {
                    ev.preventDefault();
                    console.log('[Fullscreen] F key pressed');
                    toggleFullscreen();
                }
            }
        });

        index = 0;
        slides.forEach(function (s, n) { s.classList.toggle('is-current', n === 0); });
        if (counter) counter.textContent = '1 / ' + slides.length;
        if (prevBtn) prevBtn.style.display = 'inline-flex';
        if (nextBtn) nextBtn.style.display = 'inline-flex';
        show(0);
    }
})();
