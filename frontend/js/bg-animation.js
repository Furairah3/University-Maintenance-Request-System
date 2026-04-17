/**
 * Ashesi-branded geometric background animation.
 * Floating hexagons, triangles, and circles in Ashesi red and warm gold,
 * with faint connecting lines between nearby particles (neural-network look).
 * Vanilla JS, no dependencies. Respects prefers-reduced-motion.
 */
(function () {
    const canvas = document.getElementById('bgCanvas');
    if (!canvas) return;

    const ctx = canvas.getContext('2d');
    const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    // Ashesi brand palette
    const COLORS = {
        red:  { r: 155, g: 27,  b: 48 },   // Ashesi primary
        gold: { r: 197, g: 165, b: 90 },   // Ashesi gold
    };

    const LINK_DISTANCE = 150;
    const MOUSE_RADIUS  = 160;

    let width = 0, height = 0, dpr = 1;
    let particles = [];
    let mouse = { x: -9999, y: -9999, active: false };

    /** Create a single particle with a random shape, color, and drift. */
    function makeParticle() {
        const isGold = Math.random() < 0.32;                       // ~32% gold, 68% red
        const type = ['hex', 'triangle', 'circle', 'circle'][
            Math.floor(Math.random() * 4)
        ];                                                          // circles are more common
        const size = type === 'circle'
            ? Math.random() * 2 + 1
            : Math.random() * 10 + 6;
        return {
            x: Math.random() * width,
            y: Math.random() * height,
            vx: (Math.random() - 0.5) * 0.25,
            vy: (Math.random() - 0.5) * 0.25,
            size,
            type,
            color: isGold ? COLORS.gold : COLORS.red,
            rotation: Math.random() * Math.PI * 2,
            rotSpeed: (Math.random() - 0.5) * 0.006,
            baseAlpha: type === 'circle'
                ? (Math.random() * 0.35 + 0.25)
                : (Math.random() * 0.14 + 0.08),
        };
    }

    function resize() {
        dpr = Math.min(window.devicePixelRatio || 1, 2);
        width = window.innerWidth;
        height = window.innerHeight;
        canvas.width = width * dpr;
        canvas.height = height * dpr;
        canvas.style.width = width + 'px';
        canvas.style.height = height + 'px';
        ctx.setTransform(dpr, 0, 0, dpr, 0, 0);

        // Scale particle count with viewport, cap at 65 for perf
        const target = Math.min(65, Math.floor((width * height) / 22000));
        particles = [];
        for (let i = 0; i < target; i++) particles.push(makeParticle());
    }

    function rgba(color, a) {
        return `rgba(${color.r}, ${color.g}, ${color.b}, ${a})`;
    }

    function drawHex(x, y, size, rot) {
        ctx.save();
        ctx.translate(x, y);
        ctx.rotate(rot);
        ctx.beginPath();
        for (let i = 0; i < 6; i++) {
            const a = (Math.PI / 3) * i;
            const px = Math.cos(a) * size;
            const py = Math.sin(a) * size;
            if (i === 0) ctx.moveTo(px, py);
            else ctx.lineTo(px, py);
        }
        ctx.closePath();
        ctx.stroke();
        ctx.restore();
    }

    function drawTriangle(x, y, size, rot) {
        ctx.save();
        ctx.translate(x, y);
        ctx.rotate(rot);
        ctx.beginPath();
        ctx.moveTo(0, -size);
        ctx.lineTo(size * 0.866, size * 0.5);
        ctx.lineTo(-size * 0.866, size * 0.5);
        ctx.closePath();
        ctx.stroke();
        ctx.restore();
    }

    function drawCircle(x, y, r) {
        ctx.beginPath();
        ctx.arc(x, y, r, 0, Math.PI * 2);
        ctx.fill();
    }

    function step() {
        ctx.clearRect(0, 0, width, height);

        // --- update + draw particles ---
        for (let i = 0; i < particles.length; i++) {
            const p = particles[i];

            // gentle mouse repel
            if (mouse.active) {
                const dx = p.x - mouse.x;
                const dy = p.y - mouse.y;
                const dist = Math.hypot(dx, dy);
                if (dist < MOUSE_RADIUS && dist > 0) {
                    const force = ((MOUSE_RADIUS - dist) / MOUSE_RADIUS) * 0.5;
                    p.vx += (dx / dist) * force;
                    p.vy += (dy / dist) * force;
                }
            }

            p.x += p.vx;
            p.y += p.vy;
            p.rotation += p.rotSpeed;

            // friction so repulsion doesn't snowball
            p.vx *= 0.985;
            p.vy *= 0.985;

            // keep a slow baseline drift
            if (Math.abs(p.vx) < 0.05) p.vx += (Math.random() - 0.5) * 0.04;
            if (Math.abs(p.vy) < 0.05) p.vy += (Math.random() - 0.5) * 0.04;

            // wrap around edges
            if (p.x < -20) p.x = width + 20;
            if (p.x > width + 20) p.x = -20;
            if (p.y < -20) p.y = height + 20;
            if (p.y > height + 20) p.y = -20;

            // draw with shape-specific style
            if (p.type === 'circle') {
                ctx.fillStyle = rgba(p.color, p.baseAlpha);
                drawCircle(p.x, p.y, p.size);
            } else {
                ctx.lineWidth = 1.2;
                ctx.strokeStyle = rgba(p.color, p.baseAlpha);
                if (p.type === 'hex') drawHex(p.x, p.y, p.size, p.rotation);
                else                  drawTriangle(p.x, p.y, p.size, p.rotation);
            }
        }

        // --- connecting lines between nearby particles ---
        ctx.lineWidth = 1;
        for (let i = 0; i < particles.length; i++) {
            for (let j = i + 1; j < particles.length; j++) {
                const a = particles[i], b = particles[j];
                const dx = a.x - b.x, dy = a.y - b.y;
                const dist = Math.hypot(dx, dy);
                if (dist < LINK_DISTANCE) {
                    // blend colors: if both gold then gold line; otherwise red (brand-forward)
                    const useGold = a.color === COLORS.gold && b.color === COLORS.gold;
                    const color = useGold ? COLORS.gold : COLORS.red;
                    const alpha = (1 - dist / LINK_DISTANCE) * 0.14;
                    ctx.strokeStyle = rgba(color, alpha);
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
    window.addEventListener('mousemove', (e) => {
        mouse.x = e.clientX;
        mouse.y = e.clientY;
        mouse.active = true;
    });
    window.addEventListener('mouseleave', () => { mouse.active = false; });

    if (reduceMotion) step();        // draw one static frame
    else requestAnimationFrame(step);
})();
