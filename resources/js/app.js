import Chart from 'chart.js/auto';

// Motion is kept on even when the OS asks for reduced motion: it is short and purely cosmetic, and the OS flag is often off by accident on Windows.

// Shared chart look: quiet grid, rounded tooltips, a short eased entrance.
Chart.defaults.font.family = "'Inter', ui-sans-serif, system-ui, sans-serif";
Chart.defaults.color = '#64748b';
Chart.defaults.borderColor = 'rgba(148, 163, 184, 0.18)';
Chart.defaults.animation = { duration: 500, easing: 'easeOutQuart' };
Chart.defaults.plugins.legend.labels.usePointStyle = true;
Chart.defaults.plugins.legend.labels.boxWidth = 8;
Chart.defaults.plugins.tooltip.backgroundColor = '#0f172a';
Chart.defaults.plugins.tooltip.padding = 10;
Chart.defaults.plugins.tooltip.cornerRadius = 8;
Chart.defaults.plugins.tooltip.titleFont = { weight: '600' };

// Charts are declared in Blade as <canvas data-chart="{...Chart.js config as JSON...}">.
function mountCharts() {
    document.querySelectorAll('canvas[data-chart]').forEach((canvas) => {
        canvas._chart?.destroy();
        try {
            canvas._chart = new Chart(canvas, JSON.parse(canvas.dataset.chart));
        } catch (e) {
            console.error('Chart could not be drawn', e);
        }
    });
}

// <span data-count="1234.5" data-decimals="2"> counts up to its value (the markup already holds the final text).
function countUp() {
    document.querySelectorAll('[data-count]:not([data-counted])').forEach((el) => {
        el.dataset.counted = '1';
        const target = parseFloat(el.dataset.count);
        if (!Number.isFinite(target)) return;
        const decimals = parseInt(el.dataset.decimals ?? '0', 10);
        const fmt = new Intl.NumberFormat('en-US', { minimumFractionDigits: decimals, maximumFractionDigits: decimals });
        const final = el.textContent;
        const start = performance.now();
        const duration = 900;
        const tick = (now) => {
            const t = Math.max(0, Math.min(1, (now - start) / duration));
            const eased = 1 - Math.pow(1 - t, 4);
            el.textContent = t < 1 ? fmt.format(target * eased) : final;
            if (t < 1) requestAnimationFrame(tick);
        };
        requestAnimationFrame(tick);
    });
}

// Flash messages fade out by themselves; errors stay.
function toasts() {
    document.querySelectorAll('.toast[data-autohide]:not([data-armed])').forEach((el) => {
        el.dataset.armed = '1';
        setTimeout(() => {
            el.classList.add('is-leaving');
            setTimeout(() => el.remove(), 320);
        }, 5000);
    });
}

// Thin progress bar while a Livewire request is in flight.
function busyBar() {
    const bar = document.getElementById('busy-bar');
    if (!bar || !window.Livewire?.hook) return;
    let pending = 0;
    const set = () => bar.classList.toggle('is-busy', pending > 0);
    window.Livewire.hook('request', ({ succeed, fail }) => {
        pending++;
        set();
        const done = () => { pending = Math.max(0, pending - 1); set(); };
        succeed(done);
        fail(done);
    });
}

// Click ripple: a soft circle grows from the pointer on buttons and the sidebar pills (delegated, so it also covers Livewire content).
document.addEventListener('pointerdown', (e) => {
    const el = e.target.closest?.('.btn-primary, .btn-secondary, .btn-danger, .nav-link, .ripple');
    if (!el || el.disabled) return;
    const rect = el.getBoundingClientRect();
    const size = Math.max(rect.width, rect.height) * 2;
    const dot = document.createElement('span');
    dot.className = 'ripple-dot';
    dot.style.width = dot.style.height = `${size}px`;
    dot.style.left = `${e.clientX - rect.left - size / 2}px`;
    dot.style.top = `${e.clientY - rect.top - size / 2}px`;
    el.appendChild(dot);
    setTimeout(() => dot.remove(), 650);
});

// Desktop icon rail: the dark pill is drawn as one smooth SVG path with a round notch cut around the active item.
// The notch (and its bubble) glides from the previous page's item to the current one.
const RAIL = { w: 208, r: 35, f: 12, cx: 22, corner: 40 }; // pill width, notch radius, fillet radius, notch centre x (from the edge the notch is on), corner radius; the SVG is mirrored in CSS so the notch sits on the right

function railPath(h, cy) {
    const { w, r, f, cx, corner: k0 } = RAIL;
    const top = `M${k0} 0H${w - k0}A${k0} ${k0} 0 0 1 ${w} ${k0}V${h - k0}A${k0} ${k0} 0 0 1 ${w - k0} ${h}H${k0}A${k0} ${k0} 0 0 1 0 ${h - k0}`;
    const close = `L0 ${k0}A${k0} ${k0} 0 0 1 ${k0} 0Z`;
    if (cy < k0 + 60 || cy > h - k0 - 60) return `${top}${close}`;
    const dx = cx - f;
    const d = Math.sqrt((r + f) ** 2 - dx ** 2);
    const k = r / (r + f);
    const tx = cx - k * dx;
    const ty = k * d;
    return `${top}L0 ${cy + d}A${f} ${f} 0 0 1 ${tx} ${cy + ty}A${r} ${r} 0 1 0 ${tx} ${cy - ty}A${f} ${f} 0 0 1 0 ${cy - d}${close}`;
}

function railNotch() {
    const rail = document.querySelector('.rail');
    if (!rail) return;
    const path = rail.querySelector('.rail-svg path');
    const links = [...rail.querySelectorAll('.nav-link')];
    const active = rail.querySelector('.nav-link[aria-current="page"]');
    const wide = window.matchMedia('(min-width: 1024px)').matches;
    const draw = (cy) => {
        rail._cy = cy;
        path.setAttribute('d', railPath(rail.offsetHeight, cy));
        rail.style.setProperty('--notch-y', `${cy}px`);
    };
    const centre = (el) => el.getBoundingClientRect().top - rail.getBoundingClientRect().top + el.offsetHeight / 2;
    // Glide the notch to an item's centre; the item whose icon sits on the bubble gets .is-hot.
    const glide = (target, duration = 450) => {
        if (rail._anim) cancelAnimationFrame(rail._anim);
        links.forEach((l) => l.classList.toggle('is-hot', l === target));
        if (!target) { draw(-200); return; }
        const to = centre(target);
        const from = Number.isFinite(rail._cy) && rail._cy > 0 ? rail._cy : to;
        try { sessionStorage.setItem('railY', String(to)); } catch (e) { /* storage unavailable */ }
        const start = performance.now();
        const step = (now) => {
            const t = Math.min(1, (now - start) / duration);
            draw(from + (to - from) * (1 - Math.pow(1 - t, 4)));
            if (t < 1) rail._anim = requestAnimationFrame(step);
        };
        rail._anim = requestAnimationFrame(step);
    };
    rail._glide = glide;
    rail._active = active;
    if (!wide) { glide(null); return; }
    let from = NaN;
    try { from = parseFloat(sessionStorage.getItem('railY')); } catch (e) { /* storage unavailable */ }
    rail._cy = Number.isFinite(from) ? from : undefined;
    glide(active);
    if (!rail._bound) {
        rail._bound = true;
        // Keep the pill the same height as the page area when the content grows or shrinks.
        new ResizeObserver(() => { if (Number.isFinite(rail._cy)) path.setAttribute('d', railPath(rail.offsetHeight, rail._cy)); }).observe(rail);
        // Hover moves the notch to the hovered item; leaving returns it to the current page. Clicking keeps it there.
        rail.addEventListener('mouseover', (e) => {
            const link = e.target.closest('.nav-link');
            if (link && window.matchMedia('(min-width: 1024px)').matches) rail._glide(link, 320);
        });
        rail.addEventListener('mouseleave', () => {
            if (window.matchMedia('(min-width: 1024px)').matches) rail._glide(rail._active, 380);
        });
    }
}
window.addEventListener('resize', () => railNotch());

function boot() {
    railNotch();
    mountCharts();
    countUp();
    toasts();
}

document.addEventListener('DOMContentLoaded', boot);
document.addEventListener('livewire:navigated', boot);
document.addEventListener('livewire:init', busyBar);
document.addEventListener('livewire:initialized', () => {
    window.Livewire.hook('morphed', () => { countUp(); toasts(); });
});
