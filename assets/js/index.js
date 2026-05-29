document.addEventListener('DOMContentLoaded', () => {
    setTimeout(() => {
        const canvas = document.getElementById('homeRadarDemo');
        if (!canvas || typeof Chart === 'undefined') return;
        const ctx = canvas.getContext('2d');
        new Chart(ctx, {
            type: 'radar',
            data: {
                labels: ['Complexité', 'Vraisemblance', 'Effroi', 'Aventure', 'Rythme', 'Suspense', 'Vibe', 'Esthétique', 'Sentiment'],
                datasets: [{
                    label: 'SÉQUENCE TYPE',
                    data: [8, 6, 4, 7, 8, 9, 8, 9, 7],
                    fill: true,
                    backgroundColor: 'rgba(167, 199, 231, 0.2)',
                    borderColor: '#a7c7e7',
                    borderWidth: 2,
                    pointBackgroundColor: '#a7c7e7',
                    pointBorderColor: '#050505',
                }]
            },
            options: {
                responsive: true, aspectRatio: 1,
                events: [],
                scales: { r: {
                    angleLines: { color: 'rgba(255,255,255,0.1)' },
                    grid: { color: 'rgba(255,255,255,0.1)' },
                    pointLabels: { color: 'rgba(255,255,255,0.5)', font: { size: 10, weight: '700', family: 'monospace' } },
                    ticks: { display: false, stepSize: 2 },
                    suggestedMin: 0, suggestedMax: 10
                }},
                plugins: { legend: { display: false } }
            }
        });
    }, 100);
});
