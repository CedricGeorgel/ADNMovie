/**
 * ASSETS/JS/RADARS.JS — Visualisation DNA + Modal de notation
 * Plage : -10 à +10, neutre à 0
 */

// ── Palette ───────────────────────────────────────────────────────────────────
const RADAR_COLORS = {
    commu:     '#A7C7E7',
    commuFill: 'rgba(167, 199, 231, 0.18)',
    user:      'rgba(255,255,255,0.92)',
    userFill:  'rgba(255,255,255,0.08)',
    userNull:  '#FA6B6B', // Rouge vif pour les valeurs nulles
    amber:     '#E8C07A',
    lavender:  '#B8A7E7',
    neutral:   'rgba(255,255,255,0.25)',
};

function dotColor(val) {
    if (val < 0) return RADAR_COLORS.amber;
    if (val > 0) return RADAR_COLORS.lavender;
    return RADAR_COLORS.neutral;
}

// ── Init radars ───────────────────────────────────────────────────────────────
function initRadars() {
    const isUserPage = document.body.classList.contains('page-user');

    document.querySelectorAll('.radar-canvas:not(#dnaMutationChart)').forEach(canvas => {
        const existing = Chart.getChart(canvas);
        if (existing) existing.destroy();

        const ctx       = canvas.getContext('2d');
        const labels    = JSON.parse(canvas.dataset.labels || '[]');
        const dataCommu = JSON.parse(canvas.dataset.commu  || '[]');
        const dataUser  = canvas.dataset.user && canvas.dataset.user !== 'null'
                          ? JSON.parse(canvas.dataset.user) : null;

        const datasets = [];

        if (dataCommu.length > 0) {
            const origDataCommu = [...dataCommu];
            // Si la donnée est nulle, on la map à 0 pour assurer le tracé géométrique au centre
            const mappedDataCommu = dataCommu.map(v => v === null ? 0 : v);

            let bgColors = RADAR_COLORS.commu;
            let borderColors = RADAR_COLORS.commu;
            let pointRadii = 3;

            if (isUserPage) {
                bgColors = dataCommu.map(v => v === null ? RADAR_COLORS.userNull : RADAR_COLORS.commu);
                borderColors = dataCommu.map(v => v === null ? RADAR_COLORS.userNull : RADAR_COLORS.commu);
                pointRadii = dataCommu.map(v => v === null ? 4 : 3);
            }

            datasets.push({
                label:                isUserPage ? 'Mon ADN' : 'Moyenne Moovie',
                data:                 isUserPage ? mappedDataCommu : dataCommu,
                originalData:         origDataCommu,
                fill:                 true,
                backgroundColor:      RADAR_COLORS.commuFill,
                borderColor:          RADAR_COLORS.commu,
                borderWidth:          2,
                pointRadius:          pointRadii,
                pointBackgroundColor: bgColors,
                pointBorderColor:     borderColors,
            });
        }

        if (dataUser) {
            const origDataUser = [...dataUser];
            const mappedDataUser = dataUser.map(v => v === null ? 0 : v);

            datasets.push({
                label:                'Mon Analyse',
                data:                 mappedDataUser,
                originalData:         origDataUser,
                fill:                 true,
                backgroundColor:      RADAR_COLORS.userFill,
                borderColor:          RADAR_COLORS.user,
                borderWidth:          1.5,
                borderDash:           [4, 3],
                pointRadius:          4,
                pointBackgroundColor: RADAR_COLORS.user,
                pointBorderColor:     RADAR_COLORS.user,
            });
        }

        new Chart(ctx, {
            type: 'radar',
            data: { labels, datasets },
            options: {
                responsive:          true,
                maintainAspectRatio: false,
                scales: {
                    r: {
                        angleLines:  { color: 'rgba(255,255,255,0.08)' },
                        grid:        { color: 'rgba(255,255,255,0.08)' },
                        pointLabels: { color: '#aaa', font: { size: 10, family: 'Inter' } },
                        ticks: {
                            display:       true,
                            stepSize:      5,
                            color:         'rgba(255,255,255,0.15)',
                            font:          { size: 8 },
                            backdropColor: 'transparent',
                            callback:      (v) => v === 0 ? '◆' : '',
                        },
                        suggestedMin: -10,
                        suggestedMax:  10,
                    }
                },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: (ctx) => {
                                const origVal = ctx.dataset.originalData ? ctx.dataset.originalData[ctx.dataIndex] : ctx.raw;
                                if (origVal === null) return ` ${ctx.dataset.label} : Non évalué`;
                                const sign = origVal > 0 ? '+' : '';
                                return ` ${ctx.dataset.label} : ${sign}${origVal}`;
                            }
                        }
                    }
                }
            }
        });
    });

}

// ── Switch vue radar / curseurs ───────────────────────────────────────────────
function switchChartView(id, mode, btn) {
    const radarView   = document.getElementById('view-radar-'   + id);
    const slidersView = document.getElementById('view-sliders-' + id);
    const tabs        = btn.closest('.chart-view-toggle').querySelectorAll('.chart-tab');

    tabs.forEach(t => t.classList.remove('active'));
    btn.classList.add('active');

    if (mode === 'radar') {
        radarView.style.display   = '';
        slidersView.style.display = 'none';
    } else {
        radarView.style.display   = 'none';
        slidersView.style.display = '';
        colorBipolarDots(id);
    }
}

// ── Colorisation dots bipolaires ──────────────────────────────────────────────
function colorBipolarDots(id) {
    const wrapper = document.getElementById('view-sliders-' + id);
    if (!wrapper) return;
    const isUserPage = document.body.classList.contains('page-user');

    wrapper.querySelectorAll('.bipolar-dot--commu').forEach(dot => {
        const rawVal = dot.dataset.val;
        let col;
        
        if (isUserPage && rawVal === 'null') {
            col = RADAR_COLORS.userNull;
        } else {
            const val = parseFloat(rawVal !== 'null' && rawVal !== undefined ? rawVal : 0);
            col = dotColor(val);
        }
        
        dot.style.background = col;
        dot.style.boxShadow  = `0 0 7px ${col}88`;
    });
}

// ── Helpers score display ─────────────────────────────────────────────────────
function scoreColor(val) {
    if (val < 0) return '#E8C07A';
    if (val > 0) return '#B8A7E7';
    return '#A7C7E7';
}

function scoreText(val) {
    if (val === null || val === undefined) return '—';
    const n = parseFloat(val);
    return n > 0 ? '+' + n : String(n);
}

// ── Modal : activation au clic/touch ──────────────────────────────────────────
function activateSliderKey(key, event) {
    const sw       = document.getElementById('switch-' + key);
    const slider   = document.getElementById('slider-'  + key);
    const group    = document.getElementById('group-'   + key);
    const checkbox = document.getElementById('check-'   + key);
    if (!slider) return;

    if (sw && !sw.classList.contains('slider-switch--on')) {
        sw.classList.add('slider-switch--on');
    }
    slider.disabled = false;
    slider.classList.remove('slider-untouched');
    if (checkbox) checkbox.checked = true;
    if (group)    { group.classList.add('active'); group.classList.remove('inactive'); }

    if (event) {
        const rect  = slider.getBoundingClientRect();
        const min   = parseFloat(slider.min);
        const max   = parseFloat(slider.max);
        const step  = parseFloat(slider.step) || 1;
        const clientX = event.touches ? event.touches[0].clientX : event.clientX;
        const ratio = Math.max(0, Math.min(1, (clientX - rect.left) / rect.width));
        const raw   = min + ratio * (max - min);
        slider.value = Math.round(raw / step) * step;
    }
    onCriteriaSliderInput(key, slider.value);
}

function onCriteriaSliderInput(key, rawVal) {
    const val      = parseFloat(rawVal);
    const sw       = document.getElementById('switch-' + key);
    const slider   = document.getElementById('slider-' + key);
    const group    = document.getElementById('group-'  + key);
    const checkbox = document.getElementById('check-'  + key);
    const scoreEl  = document.getElementById('score-'  + key);

    if (sw && !sw.classList.contains('slider-switch--on')) {
        sw.classList.add('slider-switch--on');
        if (slider) { slider.disabled = false; slider.classList.remove('slider-untouched'); }
        if (checkbox) checkbox.checked = true;
        if (group)    { group.classList.add('active'); group.classList.remove('inactive'); }
    }

    if (!sw && checkbox && !checkbox.checked) {
        checkbox.checked = true;
        if (slider) { slider.disabled = false; slider.classList.remove('slider-untouched'); }
        if (group)  { group.classList.add('active'); group.classList.remove('inactive'); }
    }

    if (scoreEl) {
        scoreEl.textContent = scoreText(val);
        scoreEl.style.color = scoreColor(val);
    }
}

// ── Export JPG ────────────────────────────────────────────────────────────────
function exportRadarToJPG(canvasId) {
    const canvas = document.getElementById(canvasId);
    if (!canvas) return;
    const tmp = document.createElement('canvas');
    tmp.width = canvas.width; tmp.height = canvas.height;
    const ctx = tmp.getContext('2d');
    ctx.fillStyle = '#050505';
    ctx.fillRect(0, 0, tmp.width, tmp.height);
    ctx.drawImage(canvas, 0, 0);
    const link    = document.createElement('a');
    link.download = `moovie-adn-${canvasId}.jpg`;
    link.href     = tmp.toDataURL('image/jpeg', 0.9);
    link.click();
}

// ── Mutation & DNA Logic ──────────────────────────────────────────────────────
const MAX_CRITERIA = 3;
let __mutationChart     = null;
let __mutationAnimFrame = null;

window.showMutationView = function(dnaBefore, dnaAfter) {
    // V2 : Nouveaux labels et clés
    const labels = ['Complexité', 'Prévisibilité', 'Intensité', 'Malaise', 'Stylisation', 'Dynamique', 'Dépaysement', 'Cohérence'];
    const keys   = ['complexite', 'previsibilite', 'intensite', 'malaise', 'stylisation', 'dynamique', 'depaysement', 'coherence'];

    const beforeValues = keys.map(k => (dnaBefore && dnaBefore[k] !== undefined) ? parseFloat(dnaBefore[k]) : 0);
// ... suite inchangée
    const afterValues  = keys.map(k => {
        if (dnaAfter && dnaAfter[k] !== undefined) return parseFloat(dnaAfter[k]);
        return (dnaBefore && dnaBefore[k] !== undefined) ? parseFloat(dnaBefore[k]) : 0;
    });

    document.getElementById('rateStep-1').style.display = 'none';
    document.getElementById('rateStep-2').style.display = 'none';
    document.getElementById('rateView-mutation').style.display = 'block';

    window.buildMutationRadar('dnaMutationChart', labels, beforeValues, 'rgba(167,199,231,0.85)', 'rgba(167,199,231,0.12)', 1500);

    const radarContainer = document.getElementById('radarContainer');
    setTimeout(() => {
        radarContainer.classList.add('canvas-glitching');
        setTimeout(() => { radarContainer.classList.remove('canvas-glitching'); }, 400); 
    }, 1200);

    setTimeout(() => {
        window.buildMutationRadar('dnaMutationChart', labels, afterValues, 'rgba(184,167,231,0.9)', 'rgba(184,167,231,0.18)', 1500);
        window.showDeltas(keys, dnaBefore, dnaAfter);
    }, 1800);
};

window.buildMutationRadar = function(canvasId, labels, data, borderColor, bgColor, animDuration = 1200) {
    if (__mutationAnimFrame) { cancelAnimationFrame(__mutationAnimFrame); __mutationAnimFrame = null; }

    const pointColors = data.map(v => parseFloat(v) === 0 ? RADAR_COLORS.userNull : borderColor);

    if (__mutationChart) {
        const fromVals = [...__mutationChart.data.datasets[0].data];
        const toVals   = [...data];
        const start    = performance.now();

        __mutationChart.data.datasets[0].borderColor          = borderColor;
        __mutationChart.data.datasets[0].backgroundColor      = bgColor;
        __mutationChart.data.datasets[0].pointBackgroundColor = pointColors;
        __mutationChart.data.datasets[0].pointBorderColor     = pointColors;

        function animate(now) {
            const elapsed = now - start;
            const t       = Math.min(elapsed / animDuration, 1);
            const ease    = t < 0.5 ? 4*t*t*t : 1 - Math.pow(-2*t+2,3)/2;

            __mutationChart.data.datasets[0].data = fromVals.map((from, i) => from + (toVals[i] - from) * ease);
            __mutationChart.update('none');

            if (t < 1) __mutationAnimFrame = requestAnimationFrame(animate);
            else {
                __mutationChart.data.datasets[0].data = toVals;
                __mutationChart.update('none');
                __mutationAnimFrame = null;
            }
        }
        __mutationAnimFrame = requestAnimationFrame(animate);
        return;
    }

    const ctx = document.getElementById(canvasId)?.getContext('2d');
    if (!ctx) return;
    __mutationChart = new Chart(ctx, {
        type: 'radar',
        data: {
            labels,
            datasets: [{
                data, borderColor, backgroundColor: bgColor,
                borderWidth: 2, pointRadius: 3, 
                pointBackgroundColor: pointColors,
                pointBorderColor: pointColors
            }]
        },
        options: {
            responsive: true, maintainAspectRatio: false, animation: false,
            scales: {
                r: {
                    min: -10, max: 10,
                    angleLines:  { color: 'rgba(255,255,255,0.07)' },
                    grid:        { color: 'rgba(255,255,255,0.07)' },
                    pointLabels: { color: '#888', font: { size: 8, family: 'inherit' } },
                    ticks:       { display: false },
                }
            },
            plugins: { legend: { display: false }, tooltip: { enabled: false } }
        }
    });
};

/**
 * Calcule et affiche les évolutions de l'ADN après un vote
 */
window.showDeltas = function(keys, dnaBefore, dnaAfter) {
    const container = document.getElementById('dnaDeltaList');
    if (!container) return;
    
    container.innerHTML = '';
    
    // V2 : Dictionnaire de correspondance
    const labelsMap = {
        'complexite': 'Complexité', 
        'previsibilite': 'Prévisibilité',
        'intensite': 'Intensité', 
        'malaise': 'Malaise', 
        'stylisation': 'Stylisation', 
        'dynamique': 'Dynamique', 
        'depaysement': 'Dépaysement', 
        'coherence': 'Cohérence'
    };

    let hasMutation = false;

    keys.forEach(key => {
        const before = (dnaBefore && dnaBefore[key] !== undefined) ? parseFloat(dnaBefore[key]) : 0;
        const after  = (dnaAfter && dnaAfter[key] !== undefined) ? parseFloat(dnaAfter[key])
                     : (dnaBefore && dnaBefore[key] !== undefined) ? parseFloat(dnaBefore[key]) : 0;
        const diff   = after - before;

        // On n'affiche que les mutations tangibles (écart de plus de 0.1)
        if (Math.abs(diff) >= 0.1) {
            hasMutation = true;
            const isPositive = diff > 0;
            const sign  = isPositive ? '+' : '';
            const color = isPositive ? RADAR_COLORS.lavender : RADAR_COLORS.amber;
            
            const badge = document.createElement('span');
            badge.style.cssText = `
                font-size: 0.65rem;
                font-weight: 800;
                padding: 4px 8px;
                border-radius: 4px;
                background: rgba(255,255,255,0.05);
                color: ${color};
                border: 1px solid ${color}40;
                font-family: monospace;
            `;
            badge.innerHTML = `${labelsMap[key]} ${sign}${diff.toFixed(1)}`;
            container.appendChild(badge);
        }
    });

    if (!hasMutation) {
        const emptyState = document.createElement('span');
        emptyState.style.cssText = "font-size: 0.65rem; color: var(--text-dim); font-style: italic;";
        emptyState.textContent = "Aucune mutation structurelle détectée.";
        container.appendChild(emptyState);
    }
};

document.addEventListener('DOMContentLoaded', initRadars);