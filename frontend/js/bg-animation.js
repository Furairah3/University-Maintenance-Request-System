/**
 * Animated constellation background for auth pages.
 * Particles drift slowly, connect with faint lines when close,
 * and gently repel from the cursor. Respects prefers-reduced-motion.
 */
(function () {
    const canvas = document.getElementById('bgCanvas');
    if (!canvas) return;

    const ctx = canvas.getContext('2d');
    const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    let width = 0, height = 0, dpr = 1;
    let particles = [];
    let mouse = { x: -9999, y: -9999, active: false };

    const COLOR = 'rgba(147, 197, 253, '; // light blue, alpha appended
    const LINK_DISTANCE = 130;
    const MOUSE_RADIUS = 140;

    function resize() {
        dpr = Math.min(window.devicePixelRatio || 1, 2);
        width = window.innerWidth;
        height = window.innerHeight;
        canvas.width = width * dpr;
        canvas.height = height * dpr;
        canvas.style.width = width + 'px';
        canvas.style.height = height + 'px';
        ctx.setTransform(dpr, 0, 0, dpr, 0, 0);

        const target = Math.min(90, Math.floor((width * height) / 16000));
        particles = [];
        for (let i = 0; i < target; i++) {
            particles.push({
                x: Math.random() * width,
                y: Math.random() * height,
                vx: (Math.random() - 0.5) * 0.35,
                vy: (Math.random() - 0.5) * 0.35,
                r: Math.random() * 1.6 + 0.6,
            });
        }
    }

    function step() {
        ctx.clearRect(0, 0, width, height);

        for (let i = 0; i < particles.length; i++) {
            const p = particles[i];

            // Cursor repulsion
            if (mouse.active) {
                const dx = p.x - mouse.x;
                const dy = p.y - mouse.y;
                const dist = Math.hypot(dx, dy);
                if (dist < MOUSE_RADIUS && dist > 0) {
                    const force = (MOUSE_RADIUS - dist) / MOUSE_RADIUS * 0.6;
                    p.vx += (dx / dist) * force;
                    p.vy += (dy / dist) * force;
                }
            }

            p.x += p.vx;
            p.y += p.vy;

            // Soft friction so repulsion doesn't accumulate
            p.vx *= 0.985;
            p.vy *= 0.985;

            // Keep a slow baseline drift
            if (Math.abs(p.vx) < 0.05) p.vx += (Math.random() - 0.5) * 0.05;
            if (Math.abs(p.vy) < 0.05) p.vy += (Math.random() - 0.5) * 0.05;

            // Wrap around edges
            if (p.x < -10) p.x = width + 10;
            if (p.x > width + 10) p.x = -10;
            if (p.y < -10) p.y = height + 10;
            if (p.y > height + 10) p.y = -10;

            // Draw particle
            ctx.beginPath();
            ctx.arc(p.x, p.y, p.r, 0, Math.PI * 2);
            ctx.fillStyle = COLOR + '0.65)';
            ctx.fill();
        }

        // Draw links between close particles
        for (let i = 0; i < particles.length; i++) {
            for (let j = i + 1; j < particles.length; j++) {
                const a = particles[i], b = particles[j];
                const dx = a.x - b.x, dy = a.y - b.y;
                const dist = Math.hypot(dx, dy);
                if (dist < LINK_DISTANCE) {
                    const alpha = (1 - dist / LINK_DISTANCE) * 0.35;
                    ctx.strokeStyle = COLOR + alpha.toFixed(3) + ')';
                    ctx.lineWidth = 1;
                    ctx.beginPath();
                    ctx.moveTo(a.x, a.y);
                    ctx.lineTo(b.x, b.y);
                    ctx.stroke();
                }
            }
        }

        if (!reduceMotion) requestAnimationFrame(step);
    }

    resize();
    window.addEventListener('resize', resize);
    window.addEventListener('mousemove', function (e) {
        mouse.x = e.clientX;
        mouse.y = e.clientY;
        mouse.active = true;
    });
    window.addEventListener('mouseleave', function () { mouse.active = false; });

    if (reduceMotion) {
        // Static snapshot only
        step();
    } else {
        requestAnimationFrame(step);
    }
})();
