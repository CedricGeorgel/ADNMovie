<?php
/**
 * COMPONENTS/SIGNAL-BTN.PHP
 *
 * renderSignalBtn(type, id, $isLoggedIn)
 *   — affiche un petit bouton ⚑ discret
 *
 * renderSignalModal()
 *   — à appeler une fois par page, injecte le modal + JS global
 */

function renderSignalBtn(string $entityType, int $entityId, bool $isLoggedIn = true): void {
    if (!$isLoggedIn) return;
    ?>
    <button
        class="signal-btn"
        onclick="openSignal('<?= $entityType ?>', <?= $entityId ?>)"
        title="Signaler ce contenu"
        aria-label="Signaler">
        ⚑
    </button>
    <?php
}

function renderSignalModal(): void {
    static $rendered = false;
    if ($rendered) return;
    $rendered = true;
    ?>
    <!-- ── Signal Modal ── -->
    <div id="signalModal" style="
        display:none; position:fixed; inset:0; z-index:9999;
        background:rgba(0,0,0,0.6); backdrop-filter:blur(4px);
        align-items:center; justify-content:center;">
        <div style="
            background:var(--card-bg); border:1px solid var(--border);
            border-radius:16px; padding:28px; width:100%; max-width:340px;
            margin:20px; position:relative;">

            <button onclick="closeSignal()" style="
                position:absolute; top:14px; right:16px;
                background:none; border:none; color:var(--text-dim);
                font-size:1.1rem; cursor:pointer; line-height:1;">&times;</button>

            <h4 style="font-size:0.75rem;font-weight:800;letter-spacing:2px;color:var(--text-dim);text-transform:uppercase;margin:0 0 18px 0;">
                Signaler un contenu
            </h4>

            <div id="signalReasons" style="display:flex;flex-direction:column;gap:8px;">
                <?php
                $reasons = [
                    'inappropriate' => 'Contenu inapproprié / offensant',
                    'spam'          => 'Spam ou hors-sujet',
                    'wrong_info'    => 'Informations incorrectes',
                    'other'         => 'Autre',
                ];
                foreach ($reasons as $key => $label):
                ?>
                <button
                    class="signal-reason-btn"
                    data-reason="<?= $key ?>"
                    onclick="submitSignal('<?= $key ?>')"
                    style="
                        text-align:left; padding:10px 14px;
                        background:rgba(255,255,255,0.02);
                        border:1px solid var(--border);
                        border-radius:8px; color:var(--text-muted);
                        font-size:0.78rem; cursor:pointer;
                        transition:border-color 0.15s, color 0.15s;">
                    <?= $label ?>
                </button>
                <?php endforeach; ?>
            </div>

            <div id="signalConfirm" style="display:none;text-align:center;padding:20px 0 8px;">
                <div style="font-size:1.4rem;margin-bottom:10px;">✓</div>
                <p style="font-size:0.82rem;color:var(--text-muted);margin:0;">
                    Signalement transmis.<br>
                    <span style="font-size:0.72rem;color:var(--text-dim);">Les modérateurs vont examiner ce contenu.</span>
                </p>
            </div>
        </div>
    </div>

    <script>
    // Stockage sur window pour éviter tout conflit de portée entre scripts
    window._signal = { type: null, id: null };

    window.openSignal = function(type, id) {
        window._signal.type = type;
        window._signal.id   = id;
        document.getElementById('signalReasons').style.display  = 'flex';
        document.getElementById('signalConfirm').style.display  = 'none';
        document.getElementById('signalModal').style.display    = 'flex';
    };

    window.closeSignal = function() {
        document.getElementById('signalModal').style.display = 'none';
        window._signal.type = null;
        window._signal.id   = null;
    };

    window.submitSignal = function(reason) {
        var s = window._signal;
        if (!s.type || !s.id) return;

        // Retour visuel immédiat — avant tout appel réseau
        var btn = document.querySelector('.signal-reason-btn[data-reason="' + reason + '"]');
        if (btn) btn.style.opacity = '0.4';
        document.querySelectorAll('.signal-reason-btn').forEach(function(b) { b.disabled = true; });

        var fd = new FormData();
        fd.append('entity_type', s.type);
        fd.append('entity_id',   s.id);
        fd.append('reason',      reason);

        fetch('api/api_signal.php', { method: 'POST', body: fd })
            .then(function(res) { return res.json(); })
            .then(function(data) {
                if (data.success) {
                    document.getElementById('signalReasons').style.display = 'none';
                    document.getElementById('signalConfirm').style.display = 'block';
                    setTimeout(window.closeSignal, 2200);
                } else {
                    console.warn('signal refusé:', data.message);
                    document.querySelectorAll('.signal-reason-btn').forEach(function(b) { b.disabled = false; b.style.opacity = ''; });
                }
            })
            .catch(function(e) {
                console.error('signal erreur réseau:', e);
                document.querySelectorAll('.signal-reason-btn').forEach(function(b) { b.disabled = false; b.style.opacity = ''; });
            });
    };

    // Ferme sur clic extérieur
    (function() {
        var modal = document.getElementById('signalModal');
        if (modal) {
            modal.addEventListener('click', function(e) {
                if (e.target === this) window.closeSignal();
            });
        }
    })();
    </script>
    <?php
}
