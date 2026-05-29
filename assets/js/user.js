let _allProviders = null;

function switchProfileTab(tab) {
    const tabs = ['profil', 'plateformes', 'confidentialite', 'import'];
    tabs.forEach(t => {
        document.getElementById('tab-' + t).style.display = (t === tab) ? 'block' : 'none';
        document.getElementById('tab-btn-' + t)?.classList.toggle('active', t === tab);
    });
    if (tab === 'plateformes') loadPlatforms();
}

function openProfileEditor(tab) {
    switchProfileTab(tab || 'profil');
    openModal('editProfileModal');
}

let finalAvatarBase64 = '';

function resizeToCanvas(imgEl, targetW, targetH) {
    const canvas = document.createElement('canvas');
    canvas.width = targetW; canvas.height = targetH;
    const ctx = canvas.getContext('2d');
    const srcRatio = imgEl.width / imgEl.height;
    const tgtRatio = targetW / targetH;
    let sx, sy, sw, sh;
    if (srcRatio > tgtRatio) {
        sh = imgEl.height; sw = sh * tgtRatio;
        sx = (imgEl.width - sw) / 2; sy = 0;
    } else {
        sw = imgEl.width; sh = sw / tgtRatio;
        sx = 0; sy = (imgEl.height - sh) / 2;
    }
    ctx.drawImage(imgEl, sx, sy, sw, sh, 0, 0, targetW, targetH);
    return canvas;
}


document.addEventListener('DOMContentLoaded', function() {
    // ── Avatar ──
    const avatarInput = document.getElementById('avatarInput');
    if (avatarInput) {
        avatarInput.addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (!file) return;
            const reader = new FileReader();
            reader.onload = function(event) {
                const img = new Image();
                img.onload = function() {
                    const canvas = resizeToCanvas(img, 256, 256);
                    finalAvatarBase64 = canvas.toDataURL('image/jpeg', 0.85);
                    document.getElementById('avatarPreview').src = finalAvatarBase64;
                };
                img.src = event.target.result;
            };
            reader.readAsDataURL(file);
        });
    }

});

function saveProfile() {
    const newUsername    = document.getElementById('editUsernameInput').value.trim();
    const newDescription = document.getElementById('editDescriptionInput').value.trim();
    if (!newUsername) { alert("Pseudo requis."); return; }

    const formData = new URLSearchParams();
    formData.append('username', newUsername);
    formData.append('description', newDescription);
    if (finalAvatarBase64) formData.append('avatar_base64', finalAvatarBase64);

    fetch('api/api_update_profile.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: formData
    })
    .then(r => r.json())
    .then(res => {
        if (res.success) {
            document.getElementById('mainUsername').innerText = res.username;
            if (res.avatar) document.getElementById('mainAvatar').src = res.avatar;
            const formattedDesc = newDescription
                ? newDescription.replace(/\n/g, "<br>")
                : "Aucune description fournie.";
            document.getElementById('mainDescription').innerHTML = formattedDesc;
            closeModal('editProfileModal');
        } else {
            alert("Erreur : " + res.message);
        }
    })
    .catch(err => console.error("Erreur requête :", err));
}

function savePrivacy() {
    const adnPublic        = document.getElementById('toggle-adn-public').checked;
    const collectionPublic = document.getElementById('toggle-collection-public').checked;
    const wishlistPublic   = document.getElementById('toggle-wishlist-public').checked;
    const contentPublic    = document.getElementById('toggle-content-public').checked;
    fetch('api/api_update_privacy.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({
            adn_public:        adnPublic        ? '1' : '0',
            collection_public: collectionPublic ? '1' : '0',
            wishlist_public:   wishlistPublic   ? '1' : '0',
            content_public:    contentPublic    ? '1' : '0',
        })
    })
    .then(r => r.json())
    .then(res => {
        if (res.success) {
            closeModal('editProfileModal');
        } else {
            alert("Erreur : " + (res.message || "Impossible de sauvegarder."));
        }
    })
    .catch(err => console.error("Erreur confidentialité :", err));
}

async function loadPlatforms() {
    if (_allProviders !== null) { renderPlatformGrid(); return; }
    const grid = document.getElementById('platformsGrid');
    if (grid) grid.innerHTML = '<p class="platforms-loading">Chargement...</p>';
    try {
        const res  = await fetch('api/api_get_providers.php');
        const data = await res.json();
        if (!data.success) { if (grid) grid.innerHTML = '<p class="platforms-loading">Erreur de chargement.</p>'; return; }
        _allProviders = data.providers;
        renderPlatformGrid();
    } catch(e) { console.error('loadPlatforms:', e); }
}

function renderPlatformGrid(filter = '') {
    const grid = document.getElementById('platformsGrid');
    if (!grid || !_allProviders) return;
    const term = filter.toLowerCase().trim();

    const sorted = [..._allProviders].sort((a, b) => {
        const aOwned = _userPlatforms.includes(a.provider_id) ? 0 : 1;
        const bOwned = _userPlatforms.includes(b.provider_id) ? 0 : 1;
        return aOwned - bOwned;
    });

    const visible = term
        ? sorted.filter(p => p.provider_name.toLowerCase().includes(term))
        : sorted;

    grid.innerHTML = visible.map(p => {
        const active      = _userPlatforms.includes(p.provider_id);
        const name        = p.provider_name.replace(/"/g, '&quot;');
        const priceVal    = p.price != null ? parseFloat(p.price).toFixed(2) : (p.default_price != null ? parseFloat(p.default_price).toFixed(2) : '');
        const priceDisplay = priceVal ? priceVal.replace('.', ',') + '&nbsp;€/mois' : 'Prix inconnu';

        return `<div class="platform-card ${active ? 'active' : ''}" data-id="${p.provider_id}">
            <img src="https://image.tmdb.org/t/p/original${p.logo_path}"
                 alt="${name}"
                 class="platform-card-logo"
                 onerror="this.closest('.platform-card').style.display='none'">
            <div class="platform-card-info">
                <span class="platform-card-name">${name}</span>
                <div class="platform-card-price-row">
                    <input  type="number"
                            class="platform-card-price-input"
                            step="0.01" min="0" max="999"
                            value="${priceVal}"
                            placeholder="Prix/mois"
                            data-id="${p.provider_id}"
                            onchange="saveTilePrice(${p.provider_id}, this.value)">
                    <span class="platform-card-currency">€/mois</span>
                </div>
            </div>
            <button class="platform-card-toggle ${active ? 'active' : ''}"
                    onclick="togglePlatformCard(this, ${p.provider_id})"
                    type="button"
                    title="${active ? 'Retirer' : 'Ajouter'}">
                ${active ? '✓' : '+'}
            </button>
        </div>`;
    }).join('') || '<p style="font-size:0.7rem;color:var(--text-dim);">Aucune plateforme trouvée.</p>';
}

window.filterPlatforms = function(value) {
    renderPlatformGrid(value);
};

function togglePlatform(btn, id) {
    btn.classList.toggle('active');
    if (btn.classList.contains('active')) {
        if (!_userPlatforms.includes(id)) _userPlatforms.push(id);
    } else {
        _userPlatforms = _userPlatforms.filter(x => x !== id);
    }
}

function togglePlatformCard(btn, id) {
    const card   = btn.closest('.platform-card');
    const active = !card.classList.contains('active');
    card.classList.toggle('active', active);
    btn.classList.toggle('active', active);
    btn.textContent = active ? '✓' : '+';
    btn.title       = active ? 'Retirer' : 'Ajouter';
    if (active) {
        if (!_userPlatforms.includes(id)) _userPlatforms.push(id);
    } else {
        _userPlatforms = _userPlatforms.filter(x => x !== id);
    }
}

async function saveTilePrice(providerId, value) {
    const price = value !== '' ? parseFloat(value) : null;
    const p     = _allProviders.find(x => x.provider_id === providerId);
    if (p) p.price = price;
    await fetch('api/api_update_platform_price.php', {
        method:  'POST',
        headers: { 'Content-Type': 'application/json' },
        body:    JSON.stringify({ provider_id: providerId, price }),
    });
}


async function savePlatforms() {
    try {
        const res  = await fetch('api/api_update_platforms.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ platform_ids: _userPlatforms }),
        });
        const data = await res.json();
        if (data.success) closeModal('editProfileModal');
        else alert('Erreur : ' + (data.message || 'Impossible de sauvegarder.'));
    } catch(e) { console.error('savePlatforms:', e); }
}
