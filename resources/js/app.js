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

function boot() {
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
