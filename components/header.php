<?php
/**
 * HEADER.PHP - Logo, Profil et Gestion des Scripts
 */
require_once __DIR__ . '/../functions/push_logic.php';

function get_site_banner_config(): array {
    db_execute("CREATE TABLE IF NOT EXISTS site_banner (
        id        TINYINT UNSIGNED NOT NULL PRIMARY KEY,
        message   TEXT             NOT NULL DEFAULT '',
        color     VARCHAR(20)      NOT NULL DEFAULT 'blue',
        is_active TINYINT(1)       NOT NULL DEFAULT 0
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $row = db_fetch_one('SELECT * FROM site_banner WHERE id = 1');
    return $row ?: ['id' => 1, 'message' => '', 'color' => 'blue', 'is_active' => 0];
}

function get_active_banner(?string $userId): ?array {
    $config = get_site_banner_config();

    // Quarter-end: last 14 days of March / June / September / December
    $month = (int)date('n');
    $day   = (int)date('j');
    $daysInMonth = (int)date('t');
    $isQuarterEnd = in_array($month, [3, 6, 9, 12]) && ($daysInMonth - $day) < 14;

    if ($isQuarterEnd && $userId) {
        return [
            'message'  => 'Fin de trimestre — générez et partagez votre bilan cinématographique !',
            'color'    => 'purple',
            'link'     => 'share-card.php?id=' . rawurlencode($userId),
            'link_label' => 'Voir ma carte →',
        ];
    }

    if ($config['is_active'] && trim($config['message']) !== '') {
        return ['message' => $config['message'], 'color' => $config['color']];
    }

    return null;
}

function renderHeader($user = null) {
    // ui.js et script.js chargés une seule fois quel que soit la page
    static $uiJsLoaded = false;
    if (!$uiJsLoaded) {
        $uiJsLoaded = true;

        // iOS PWA : sync du remember_token dans localStorage (uniquement si absent ou changé)
        if ($user) {
            $pwaToken = $_COOKIE['remember_token'] ?? '';
            if ($pwaToken !== '') {
                echo '<script>(function(){try{var t=' . json_encode($pwaToken) . ';if(localStorage.getItem("_pwa_token")!==t)localStorage.setItem("_pwa_token",t);}catch(e){}})();</script>';
            }
        }

        $v  = file_exists(__DIR__ . '/../assets/js/ui.js')  ? filemtime(__DIR__ . '/../assets/js/ui.js')  : 1;
        $vs = file_exists(__DIR__ . '/../assets/script.js') ? filemtime(__DIR__ . '/../assets/script.js') : 1;
        echo '<script src="assets/js/ui.js?v='  . $v  . '" defer></script>';
        echo '<script src="assets/script.js?v=' . $vs . '" defer></script>';

        // VAPID public key + SW registration (logged-in users only)
        $vapidPublic = '';
        if ($user) {
            $vapidPublic = function_exists('get_vapid_public_key') ? get_vapid_public_key() : '';
            if ($vapidPublic) {
                $vpush = file_exists(__DIR__ . '/../assets/js/push.js') ? filemtime(__DIR__ . '/../assets/js/push.js') : 1;
                echo '<script>window.VAPID_PUBLIC_KEY=' . json_encode($vapidPublic) . ';</script>';
                echo '<script src="assets/js/push.js?v=' . $vpush . '" defer></script>';
            }
        }
    }
    ?>
    <header class="main-header">
        <div class="header-content">
            <a href="index.php" class="logo">
                <img src="assets/Icons/Named_logo2.svg" alt="ADN Movie" style="height: 32px; width: auto; display: block;">
            </a>
            
            <div class="header-actions">
    <?php if ($user): ?>
        <!-- Cloche de notifications -->
        <div class="notif-bell-wrapper" id="notif-bell-wrapper">
            <button class="notif-bell-btn" id="notif-bell-btn" aria-label="Notifications" onclick="toggleNotifPanel()">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/>
                    <path d="M13.73 21a2 2 0 0 1-3.46 0"/>
                </svg>
                <span class="notif-count" id="notif-count" style="display:none;"></span>
            </button>

            <!-- Panneau overlay -->
            <div class="notif-panel" id="notif-panel" style="display:none;">
                <div class="notif-panel-header">
                    <span>Notifications</span>
                    <div style="display:flex;align-items:center;gap:8px;">
                        <?php if ($vapidPublic): ?>
                        <button id="push-optin-btn" class="notif-mark-all" onclick="pushToggle()" title="Notifications push">🔕 Push</button>
                        <?php endif; ?>
                        <button class="notif-mark-all" onclick="notifMarkAllRead()">Tout lire</button>
                    </div>
                </div>
                <div class="notif-list" id="notif-list">
                    <div class="notif-empty">Chargement…</div>
                </div>
            </div>
        </div>

        <a href="user.php" class="user-pill">
            <img src="<?= h($user['avatar']) ?>" onerror="this.src='assets/default-avatar.png';" class="header-avatar">
            <span class="header-username"><?= h($user['username']) ?></span>
        </a>
    <?php else: ?>
        <a href="login.php" class="btn-login">Connexion</a>
    <?php endif; ?>
</div>
        </div>
    </header>
    <?php
    // Bulle de chat flottante
    if ($user) {
        require_once __DIR__ . '/../functions/friends_logic.php';
        $unreadDm = get_unread_dm_count((string)$user['id']);
        ?>
        <!-- Bulle : lien sur desktop, ouvre le panel sur mobile -->
        <a href="chat.php" class="chat-bubble" id="chatBubbleBtn" aria-label="Messages" onclick="event.preventDefault();chatBubbleOpen();">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
            </svg>
            <?php if ($unreadDm > 0): ?>
            <span class="chat-bubble-badge" id="chatBubbleBadge"><?= $unreadDm ?></span>
            <?php else: ?>
            <span class="chat-bubble-badge" id="chatBubbleBadge" style="display:none;">0</span>
            <?php endif; ?>
        </a>

        <!-- Panel mobile -->
        <div id="chatBubblePanel">

            <!-- Écran 1 : liste des conversations -->
            <div class="chat-panel-screen" id="chatPanelList">
                <div class="chat-panel-header">
                    <span style="flex:1;">Messages</span>
                    <a href="chat.php" style="font-size:0.7rem;color:var(--text-dim);text-decoration:none;margin-right:10px;" title="Ouvrir en pleine page">↗</a>
                    <button class="chat-panel-back" onclick="chatBubbleClose()" style="font-size:1.4rem;">✕</button>
                </div>
                <div class="chat-panel-list" id="chatPanelConvList">
                    <div style="padding:24px;text-align:center;color:var(--text-dim);font-size:0.8rem;">Chargement…</div>
                </div>
            </div>

            <!-- Écran 2 : thread -->
            <div class="chat-panel-screen hidden" id="chatPanelThread">
                <div class="chat-panel-header">
                    <button class="chat-panel-back" onclick="chatPanelBack()">←</button>
                    <div id="chatPanelThreadAvatarWrap" style="display:none;flex-shrink:0;cursor:pointer;" onclick="chatPanelAvatarClick()">
                        <img id="chatPanelThreadAvatar" src="" onerror="this.src='assets/default-avatar.png'"
                             class="member-avatar-img" style="width:32px;height:32px;">
                    </div>
                    <span id="chatPanelThreadName" style="flex:1;"></span>
                </div>
                <div id="chatPanelMessages"></div>
                <div class="chat-panel-input-area">
                    <input type="text" id="chatPanelInput" placeholder="Écrire…" maxlength="2000" autocomplete="off">
                    <button onclick="chatPanelSend()">→</button>
                </div>
            </div>

        </div>

        <script>
        (function() {
            let _panelWith    = null;
            let _panelLastId  = 0;
            let _panelTimer   = null;
            let _panelTargetUrl = null;

            function buildBubble(msg) {
                const wrap   = document.createElement('div');
                wrap.style.cssText = 'display:flex;flex-direction:column;align-items:' + (msg.is_mine ? 'flex-end' : 'flex-start') + ';';
                const bubble = document.createElement('div');
                bubble.className   = 'dm-bubble ' + (msg.is_mine ? 'mine' : 'theirs');
                bubble.textContent = msg.text;
                const time   = document.createElement('div');
                time.className   = 'dm-time';
                time.textContent = msg.time;
                wrap.appendChild(bubble);
                wrap.appendChild(time);
                return wrap;
            }

            async function loadConversations() {
                const listEl = document.getElementById('chatPanelConvList');
                const res    = await fetch('api/api_dm_conversations.php', { cache: 'no-store' });
                const data   = await res.json();
                if (!data.ok || !data.conversations.length) {
                    listEl.innerHTML = '<div style="padding:24px;text-align:center;color:var(--text-dim);font-size:0.8rem;">Aucune conversation.</div>';
                    return;
                }
                listEl.innerHTML = '';
                data.conversations.forEach(c => {
                    const el = document.createElement('div');
                    el.className = 'chat-panel-conv';
                    el.innerHTML = `
                        <img src="${c.avatar||'assets/default-avatar.png'}" onerror="this.src='assets/default-avatar.png'"
                             style="width:38px;height:38px;border-radius:50%;object-fit:cover;flex-shrink:0;">
                        <div style="flex:1;min-width:0;">
                            <div class="chat-panel-conv-name">${c.username.replace(/</g,'&lt;')}</div>
                            <div class="chat-panel-conv-preview">${(c.last_message||'').replace(/</g,'&lt;')}</div>
                        </div>
                        ${c.unread_count > 0 ? `<span class="chat-unread-badge">${c.unread_count}</span>` : ''}`;
                    el.addEventListener('click', () => openThread(c.id, c.username, c.avatar));
                    listEl.appendChild(el);
                });
            }

            function openThread(userId, username, avatar) {
                _panelWith      = userId;
                _panelLastId    = 0;
                _panelTargetUrl = (Math.floor(Math.random() * 24) + 1 === 1)
                    ? 'anomaly.php?id=' + encodeURIComponent(userId)
                    : 'adn.php?id='     + encodeURIComponent(userId);
                document.getElementById('chatPanelThreadName').textContent = username;
                const wrap = document.getElementById('chatPanelThreadAvatarWrap');
                const av   = document.getElementById('chatPanelThreadAvatar');
                if (avatar) { av.src = avatar; wrap.style.display = 'block'; }
                else        { wrap.style.display = 'none'; }
                document.getElementById('chatPanelMessages').innerHTML = '';
                document.getElementById('chatPanelList').classList.add('hidden');
                document.getElementById('chatPanelThread').classList.remove('hidden');
                pollThread();
            }

            window.chatPanelAvatarClick = function() {
                if (_panelTargetUrl) window.location.href = _panelTargetUrl;
            };

            async function pollThread() {
                if (!_panelWith) return;
                try {
                    const res  = await fetch(`api/api_dm_get.php?with=${encodeURIComponent(_panelWith)}&last_id=${_panelLastId}`, { cache: 'no-store' });
                    const data = await res.json();
                    if (data.success && data.messages.length) {
                        const el  = document.getElementById('chatPanelMessages');
                        const atB = el.scrollHeight - el.scrollTop - el.clientHeight < 60;
                        data.messages.forEach(m => {
                            el.appendChild(buildBubble(m));
                            if (m.id > _panelLastId) _panelLastId = m.id;
                        });
                        if (atB) el.scrollTo({ top: el.scrollHeight, behavior: 'smooth' });
                        // Met à jour le badge
                        const badge = document.getElementById('chatBubbleBadge');
                        if (badge) badge.style.display = 'none';
                    }
                } catch(_) {}
                _panelTimer = setTimeout(pollThread, 3000);
            }

            window.chatPanelBack = function() {
                clearTimeout(_panelTimer);
                _panelWith = null;
                document.getElementById('chatPanelThread').classList.add('hidden');
                document.getElementById('chatPanelList').classList.remove('hidden');
                loadConversations();
            };

            window.chatPanelSend = async function() {
                const input = document.getElementById('chatPanelInput');
                const text  = input.value.trim();
                if (!text || !_panelWith) return;
                input.value    = '';
                input.disabled = true;
                try {
                    await fetch('api/api_dm_post.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: new URLSearchParams({ to: _panelWith, message: text }),
                    });
                    clearTimeout(_panelTimer);
                    pollThread();
                } catch(_) {}
                input.disabled = false;
                input.focus();
            };

            // Envoi sur Entrée
            document.addEventListener('DOMContentLoaded', function() {
                const inp = document.getElementById('chatPanelInput');
                if (inp) inp.addEventListener('keydown', e => {
                    if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); chatPanelSend(); }
                });
            });

            window.chatBubbleOpen = function() {
                document.getElementById('chatBubblePanel').classList.add('open');
                if (window.innerWidth <= 700) document.body.classList.add('panel-open');
                loadConversations();
            };

            window.chatBubbleClose = function() {
                clearTimeout(_panelTimer);
                _panelWith = null;
                document.getElementById('chatBubblePanel').classList.remove('open');
                document.getElementById('chatPanelThread').classList.add('hidden');
                document.getElementById('chatPanelList').classList.remove('hidden');
                document.body.classList.remove('panel-open');
            };
        })();
        </script>
        <?php
    }

    $userId = $user['id'] ?? null;
    $banner = get_active_banner($userId ? (string)$userId : null);
    if ($banner):
        $bColor = htmlspecialchars($banner['color'], ENT_QUOTES);
        $bMsg   = htmlspecialchars($banner['message'], ENT_QUOTES);
    ?>
    <div class="site-banner site-banner--<?= $bColor ?>">
        <span><?= $bMsg ?></span>
        <?php if (!empty($banner['link'])): ?>
        <a href="<?= htmlspecialchars($banner['link'], ENT_QUOTES) ?>" class="site-banner__link">
            <?= htmlspecialchars($banner['link_label'] ?? 'Voir →', ENT_QUOTES) ?>
        </a>
        <?php endif; ?>
    </div>
    <?php endif; ?>
    <?php
}

/**
 * Charge tous les scripts Moovie.
 */
function renderScripts() {
    ?>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="assets/js/radars.js" defer></script>
    <script src="assets/js/search.js" defer></script>
    <script src="assets/js/sessions.js" defer></script>
    <?php
}