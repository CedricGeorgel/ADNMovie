document.addEventListener('DOMContentLoaded', function() {
    loadGroupAdn();
});

async function loadGroupAdn() {
    const roomId  = document.body.dataset.roomId;
    const targets = document.querySelectorAll('.group-adn-target');
    if (!targets.length || !roomId) return;

    try {
        const res  = await fetch('api/api_group_adn.php?room_id=' + encodeURIComponent(roomId));
        const data = await res.json();

        if (!data.success || data.no_data) {
            const msg = '<p style="color:var(--text-dim);font-size:0.8rem;text-align:center;padding:10px 0;">'
                      + (data.message || 'Données ADN insuffisantes.') + '</p>';
            targets.forEach(t => t.innerHTML = msg);
            return;
        }

        const criteriaKeys   = ['complexite','previsibilite','intensite','malaise','stylisation','dynamique','depaysement','coherence'];
        const criteriaLabels = ['Complexité','Prévisibilité','Intensité','Malaise','Stylisation','Dynamique','Dépaysement','Cohérence'];
        const scores = criteriaKeys.map(k => data.group_adn_assoc[k] ?? 0);

        function filmCard(film, label, labelColor) {
            if (!film) return `<p style="font-size:0.7rem;color:var(--text-dim);font-style:italic;margin-top:6px;">Aucun film disponible.</p>`;
            return `<div style="display:flex;align-items:center;gap:10px;padding:8px;background:rgba(255,255,255,0.04);border-radius:8px;margin-top:6px;">
                <img src="${film.poster || 'assets/no-poster.svg'}" onerror="this.src='assets/no-poster.svg'"
                     style="width:30px;height:44px;object-fit:cover;border-radius:4px;flex-shrink:0;">
                <div>
                    <div style="font-size:0.75rem;font-weight:800;color:white;line-height:1.2;">${film.title}</div>
                    <div style="font-size:0.6rem;color:${labelColor};margin-top:2px;">${label}</div>
                </div>
            </div>`;
        }

        const recoHtml = `
            <div style="font-size:0.6rem;text-transform:uppercase;font-weight:900;color:var(--text-dim);letter-spacing:1.5px;margin-top:10px;">Scrutin</div>
            ${filmCard(data.closest_proposal, 'Meilleure correspondance au vote', 'var(--pastel-blue)')}
            <div style="font-size:0.6rem;text-transform:uppercase;font-weight:900;color:var(--text-dim);letter-spacing:1.5px;margin-top:10px;">Catalogue</div>
            ${filmCard(data.closest_catalogue, 'Meilleure correspondance catalogue', 'var(--text-dim)')}
        `;

        targets.forEach((target, idx) => {
            const canvasId = 'groupAdnRadar-' + idx;
            target.innerHTML = `
                <p style="font-size:0.6rem;color:var(--text-dim);margin-bottom:8px;">
                    ${data.members_with_data}/${data.total_members} membres avec données ADN
                </p>
                <div style="position:relative;height:200px;">
                    <canvas id="${canvasId}"
                            class="radar-canvas"
                            data-labels='${JSON.stringify(criteriaLabels)}'
                            data-commu='${JSON.stringify(scores)}'
                            data-user='null'>
                    </canvas>
                </div>
                ${recoHtml}
            `;
        });

        if (typeof initRadars === 'function') {
            initRadars();
        }

    } catch(e) {
        console.error('loadGroupAdn:', e);
    }
}
