import Chart from 'chart.js/auto';

// Charts are declared in Blade as <canvas data-chart="{...Chart.js config as JSON...}">.
// They are (re)drawn on first load and after every wire:navigate visit.
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

document.addEventListener('DOMContentLoaded', mountCharts);
document.addEventListener('livewire:navigated', mountCharts);
