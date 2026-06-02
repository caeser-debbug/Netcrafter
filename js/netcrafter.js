/* netcrafter.js — consolidated site scripts (loaded with defer) */
(function () {
    const body = document.body;
    const lang = body.dataset.lang || 'fr';
    const base = body.dataset.base || '';

    /* ── Page loader ───────────────────────────────────────────── */
    const loader = document.getElementById('page-loader');
    if (loader) {
        if (sessionStorage.getItem('nc_loaded')) {
            loader.style.display = 'none';
        } else {
            function hideLoader() {
                loader.classList.add('hidden-loader');
                sessionStorage.setItem('nc_loaded', '1');
            }
            // defer scripts run after HTML parsing; on fast networks (localhost)
            // the load event may already have fired — check readyState first.
            if (document.readyState === 'complete') {
                setTimeout(hideLoader, 400);
            } else {
                window.addEventListener('load', hideLoader, { once: true });
                setTimeout(hideLoader, 4000); // absolute fallback
            }
        }
    }

    /* ── Scroll progress ────────────────────────────────────────── */
    window.addEventListener('scroll', function () {
        const el = document.getElementById('scroll-progress');
        if (!el) return;
        const pct = window.scrollY / (document.body.scrollHeight - window.innerHeight) * 100;
        el.style.width = Math.min(pct, 100) + '%';
    }, { passive: true });

    /* ── Global cursor glow ─────────────────────────────────────── */
    const g = document.getElementById('global-cursor');
    if (g) {
        if (window.matchMedia('(pointer:coarse)').matches) {
            g.style.display = 'none';
        } else {
            let tx = 0, ty = 0, cx = 0, cy = 0;
            document.addEventListener('mousemove', e => { tx = e.clientX; ty = e.clientY; }, { passive: true });
            (function tick() {
                cx += (tx - cx) * 0.07; cy += (ty - cy) * 0.07;
                g.style.transform = `translate(${cx - 210}px,${cy - 210}px)`;
                requestAnimationFrame(tick);
            })();
        }
    }

    /* ── Sidebar ────────────────────────────────────────────────── */
    window.openSidebar = function () {
        document.getElementById('sidebar').classList.add('open');
        document.getElementById('sidebar-overlay').classList.add('open');
        document.body.style.overflow = 'hidden';
    };
    window.closeSidebar = function () {
        document.getElementById('sidebar').classList.remove('open');
        document.getElementById('sidebar-overlay').classList.remove('open');
        document.body.style.overflow = '';
    };
    document.addEventListener('keydown', e => { if (e.key === 'Escape') window.closeSidebar(); });

    /* ── Navbar scroll ──────────────────────────────────────────── */
    const navbar = document.getElementById('navbar');
    if (navbar) {
        window.addEventListener('scroll', () => {
            navbar.classList.toggle('scrolled', window.scrollY > 30);
        }, { passive: true });
    }

    /* ── Lang in localStorage ───────────────────────────────────── */
    localStorage.setItem('nc_lang', lang);

    /* ── Cookie consent ─────────────────────────────────────────── */
    const cookieBanner = document.getElementById('cookie-banner');
    if (cookieBanner && !localStorage.getItem('nc_cookie_choice')) {
        cookieBanner.style.display = 'flex';
    }
    window.cookieChoice = function (accepted) {
        if (!cookieBanner) return;
        localStorage.setItem('nc_cookie_choice', accepted ? 'accepted' : 'declined');
        cookieBanner.style.transition = 'opacity .3s';
        cookieBanner.style.opacity = '0';
        setTimeout(() => { cookieBanner.style.display = 'none'; }, 300);
    };

    /* ── Back to top ────────────────────────────────────────────── */
    const bttBtn = document.getElementById('back-to-top');
    if (bttBtn) {
        window.addEventListener('scroll', () => {
            bttBtn.style.display = window.scrollY > 300 ? 'flex' : 'none';
        }, { passive: true });
    }

    /* ── PWA Service Worker ─────────────────────────────────────── */
    if ('serviceWorker' in navigator) {
        navigator.serviceWorker.register(base + '/sw.js').catch(() => {});
    }

    /* ── AOS ────────────────────────────────────────────────────── */
    if (typeof AOS !== 'undefined') {
        AOS.init({
            once: true, duration: 750, easing: 'ease-out-quart',
            offset: 60, anchorPlacement: 'top-bottom', mirror: false
        });
    }

    /* ── Animated counters ──────────────────────────────────────── */
    function animateCounters() {
        document.querySelectorAll('[data-count]').forEach(el => {
            const target   = parseFloat(el.dataset.count);
            const suffix   = el.dataset.suffix || '';
            const prefix   = el.dataset.prefix || '';
            const decimal  = Number.isInteger(target) ? 0 : 1;
            const duration = 1800;
            const start    = performance.now();
            function step(now) {
                const p    = Math.min((now - start) / duration, 1);
                const ease = 1 - Math.pow(1 - p, 4);
                const val  = target * ease;
                el.textContent = prefix + (decimal ? val.toFixed(1) : Math.round(val)) + suffix;
                if (p < 1) requestAnimationFrame(step);
            }
            requestAnimationFrame(step);
        });
    }
    const counterSection = document.querySelector('[data-counters]');
    if (counterSection) {
        new IntersectionObserver(entries => {
            entries.forEach(e => { if (e.isIntersecting) animateCounters(); });
        }, { threshold: 0.3 }).observe(counterSection);
    } else {
        let fired = false;
        window.addEventListener('scroll', function once() {
            if (!fired && window.scrollY > 300) {
                fired = true; animateCounters();
                window.removeEventListener('scroll', once);
            }
        }, { passive: true });
    }

    /* ── Grid entry animations ──────────────────────────────────── */
    document.querySelectorAll('[data-grid-anim]').forEach(grid => {
        const cards = Array.from(grid.children);
        const cols  = parseInt(grid.dataset.cols || '3');
        const obs   = new IntersectionObserver(entries => {
            entries.forEach(e => {
                if (!e.isIntersecting) return;
                cards.forEach((card, i) => {
                    const col = i % cols;
                    let cls, delay;
                    if (cols === 1) {
                        cls = i % 2 === 0 ? 'anim-left' : 'anim-right';
                        delay = Math.min(i * 120, 480);
                    } else if (cols === 2) {
                        cls = col === 0 ? 'anim-left' : 'anim-right';
                        delay = Math.floor(i / cols) * 120;
                    } else {
                        cls   = col === 0 ? 'anim-left' : col === cols - 1 ? 'anim-right' : 'anim-up';
                        delay = col * 100;
                    }
                    setTimeout(() => {
                        card.classList.remove('anim-left', 'anim-right', 'anim-up', 'anim-zoom');
                        card.classList.add(cls);
                    }, delay);
                });
                obs.disconnect();
            });
        }, { threshold: 0.1 });
        obs.observe(grid);
    });

    /* ── Reveal underline ───────────────────────────────────────── */
    document.querySelectorAll('.reveal-underline').forEach(el => {
        new IntersectionObserver(entries => {
            entries.forEach(e => { if (e.isIntersecting) el.classList.add('aos-animate'); });
        }, { threshold: 0.5 }).observe(el);
    });

})();
