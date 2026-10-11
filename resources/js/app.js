import Chart from 'chart.js/auto';

document.addEventListener('alpine:init', () => {
    Alpine.data('watchChart', (series) => {
        let chart;
        return {
            init() {
                chart = new Chart(this.$refs.canvas, {
                    type: 'line',
                    data: {
                        labels: series.labels,
                        datasets: [
                            { label: 'Collectés', data: series.collected, borderColor: '#2563eb', tension: 0.25 },
                            { label: 'Pertinents (IA)', data: series.interesting, borderColor: '#0891b2', tension: 0.25 },
                            { label: 'Validés', data: series.validated, borderColor: '#16a34a', tension: 0.25 },
                        ],
                    },
                    options: { responsive: true, maintainAspectRatio: false, scales: { y: { beginAtZero: true, ticks: { precision: 0 } } } },
                });
            },
            destroy() { chart?.destroy(); },
        };
    });
});
