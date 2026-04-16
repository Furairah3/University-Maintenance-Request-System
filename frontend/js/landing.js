/**
 * Landing page interactivity:
 *  - Scroll-reveal via IntersectionObserver
 *  - Count-up animation for stat numbers
 *  - Nav background darkens after scroll
 *  - Cursor-follow glow on feature cards
 *  - Respects prefers-reduced-motion
 */
(function () {
    const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    // --- Nav scroll state ---
    const nav = document.querySelector('.landing-nav');
    if (nav) {
        const updateNav = () => nav.classList.toggle('scrolled', window.scrollY > 40);
        updateNav();
        window.addEventListener('scroll', updateNav, { passive: true });
    }

    // --- Scroll reveal ---
    const revealEls = document.querySelectorAll('.reveal');
    if (!reduceMotion && 'IntersectionObserver' in window) {
        const io = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('in');
                    io.unobserve(entry.target);
                }
            });
        }, { threshold: 0.15, rootMargin: '0px 0px -60px 0px' });
        revealEls.forEach(el => io.observe(el));
    } else {
        revealEls.forEach(el => el.classList.add('in'));
    }

    // --- Count-up on stat numbers ---
    function countUp(el, target, duration) {
        if (reduceMotion) { el.textContent = formatNum(target); return; }
        const start = performance.now();
        const from = 0;
        function tick(now) {
            const t = Math.min((now - start) / duration, 1);
            // easeOutCubic
            const eased = 1 - Math.pow(1 - t, 3);
            const value = from + (target - from) * eased;
            el.textContent = formatNum(value, target);
            if (t < 1) requestAnimationFrame(tick);
            else el.textContent = formatNum(target);
        }
        requestAnimationFrame(tick);
    }

    function formatNum(n, target) {
        // If the final target has a decimal, keep one; otherwise integer
        const finalTarget = target !== undefined ? target : n;
        if (finalTarget % 1 !== 0) return n.toFixed(1);
        return Math.round(n).toLocaleString();
    }

    const statNums = document.querySelectorAll('.stat-item .num[data-count]');
    if ('IntersectionObserver' in window) {
        const io2 = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const el = entry.target;
                    const target = parseFloat(el.getAttribute('data-count')) || 0;
                    countUp(el, target, 1400);
                    io2.unobserve(el);
                }
            });
        }, { threshold: 0.5 });
        statNums.forEach(el => io2.observe(el));
    } else {
        statNums.forEach(el => {
            el.textContent = formatNum(parseFloat(el.getAttribute('data-count')) || 0);
        });
    }

    // --- Cursor-follow glow on feature cards ---
    document.querySelectorAll('.feature-card').forEach(card => {
        card.addEventListener('mousemove', (e) => {
            const rect = card.getBoundingClientRect();
            const mx = ((e.clientX - rect.left) / rect.width) * 100;
            const my = ((e.clientY - rect.top) / rect.height) * 100;
            card.style.setProperty('--mx', mx + '%');
            card.style.setProperty('--my', my + '%');
        });
    });

    // --- Smooth anchor scroll with nav offset (small polish) ---
    document.querySelectorAll('a[href^="#"]').forEach(a => {
        a.addEventListener('click', (e) => {
            const id = a.getAttribute('href');
            if (id.length > 1) {
                const target = document.querySelector(id);
                if (target) {
                    e.preventDefault();
                    const top = target.getBoundingClientRect().top + window.scrollY - 72;
                    window.scrollTo({ top, behavior: reduceMotion ? 'auto' : 'smooth' });
                }
            }
        });
    });
})();
