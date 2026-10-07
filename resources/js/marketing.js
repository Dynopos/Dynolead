// Sales page only: Bob's voice note, and sections that fade in on scroll.
import '@fontsource/caveat/latin-600.css';

const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

function initReveal() {
    const items = document.querySelectorAll('[data-reveal]');

    if (reduceMotion || !('IntersectionObserver' in window)) {
        items.forEach((el) => el.classList.add('is-visible'));
        return;
    }

    const observer = new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
            if (entry.isIntersecting) {
                entry.target.classList.add('is-visible');
                observer.unobserve(entry.target);
            }
        });
    }, { rootMargin: '0px 0px -10% 0px' });

    items.forEach((el) => observer.observe(el));
}

function initVoiceNote() {
    const root = document.querySelector('[data-voice]');
    if (!root) {
        return;
    }

    const audio = root.querySelector('audio');
    const button = root.querySelector('[data-voice-toggle]');
    const time = root.querySelector('[data-voice-time]');
    const progress = root.querySelector('[data-voice-progress]');
    const storageKey = 'dyno-lead-voice-played';
    // Browsers allow sound only after a completed tap/click/key (touchend, click, keydown),
    // not on touchstart. Keep listening until one of them actually starts the audio.
    const events = ['pointerdown', 'pointerup', 'touchend', 'click', 'keydown'];
    let pausedByVisitor = false;

    const format = (seconds) => {
        const s = Math.max(0, Math.ceil(seconds));
        return `${Math.floor(s / 60)}:${String(s % 60).padStart(2, '0')}`;
    };

    const render = () => {
        const playing = !audio.paused && !audio.ended;
        root.classList.toggle('is-playing', playing);
        button.setAttribute('aria-pressed', playing ? 'true' : 'false');
        button.setAttribute('aria-label', playing ? 'Jeda pesanan Bob' : 'Dengar pesanan Bob');

        if (Number.isFinite(audio.duration) && audio.duration > 0) {
            const left = audio.ended ? audio.duration : audio.duration - audio.currentTime;
            time.textContent = format(left);
            progress.style.width = `${audio.ended ? 0 : (audio.currentTime / audio.duration) * 100}%`;
        }
    };

    ['play', 'pause', 'ended', 'timeupdate', 'loadedmetadata'].forEach((name) => audio.addEventListener(name, render));

    audio.addEventListener('play', () => {
        root.classList.remove('is-waiting');
        try {
            sessionStorage.setItem(storageKey, '1');
        } catch (e) {
            // Private mode: the voice note may play again on the next page view.
        }
    });

    const stopWaiting = () => events.forEach((name) => document.removeEventListener(name, onFirstInteraction, true));

    function onFirstInteraction(event) {
        // A tap on the player itself is handled by its own button.
        if (pausedByVisitor || !audio.paused || root.contains(event.target)) {
            stopWaiting();
            return;
        }
        audio.play().then(stopWaiting).catch(() => {});
    }

    button.addEventListener('click', () => {
        if (audio.paused) {
            pausedByVisitor = false;
            audio.play().catch(() => {});
        } else {
            pausedByVisitor = true;
            audio.pause();
        }
    });

    let alreadyPlayed = false;
    try {
        alreadyPlayed = sessionStorage.getItem(storageKey) === '1';
    } catch (e) {
        alreadyPlayed = false;
    }

    if (alreadyPlayed) {
        return;
    }

    // Play as soon as the page opens. Most browsers block sound until the visitor
    // touches the page; then it starts on their first tap or key press instead.
    audio.play().then(stopWaiting).catch(() => {
        root.classList.add('is-waiting');
        events.forEach((name) => document.addEventListener(name, onFirstInteraction, { capture: true, passive: true }));
    });
}

// The demo screen recording is decoration: keep it still for reduced-motion users.
if (reduceMotion) {
    document.querySelectorAll('video[data-autoplay]').forEach((video) => {
        video.removeAttribute('autoplay');
        video.pause();
    });
}

initReveal();
initVoiceNote();
