<?php
require_once 'functions/utils.php';
require_once 'functions/auth.php';
require_once 'functions/rooms_logic.php';
require_once 'components/header.php';
require_once 'components/nav.php';
require_once 'components/footer.php';
require_once 'components/movie-card.php';
require_once 'components/search-bar.php';
require_once 'components/button.php';
require_once 'components/event-card.php';
require_once 'components/avatar.php';

check_auth();
$userId = $_SESSION['user_id'];
$roomId = $_GET['id'] ?? null;

if (!$roomId) { header('Location: rooms.php'); exit; }

$room = get_room($roomId);
if (!$room) { header('Location: rooms.php?error=notfound'); exit; }

if (in_array($userId, $room['banned_users'])) {
    header('Location: rooms.php?error=banned'); exit;
}

$isHost      = ($room['host_id'] === $userId);
$currentUser = get_user_by_id($userId);
$allUsers    = get_users_directory();

if (!in_array($userId, $room['members'])) {
    add_user_to_room($roomId, $userId);
    $room['members'][] = $userId;
}

// Marquer les notifications message/mention de cette room comme lues
try {
    require_once 'functions/notifications_logic.php';
    mark_read_for_room($userId, $roomId);
} catch (Exception $e) { /* non critique */ }

$proposed = get_session_proposals($roomId);

$validEvents = db_fetch_all(
    'SELECT e.*, m.title AS movie_title, m.poster AS movie_poster
     FROM room_events e
     LEFT JOIN movies m ON m.tmdb_id = e.movie_id
     WHERE e.room_id = ? AND e.event_date >= CURDATE()
     ORDER BY e.event_date ASC, e.event_time ASC',
    [$roomId]
);

$memberCount = count($room['members']);

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title><?= h($room['name']) ?></title>
    <link rel="icon" href="/assets/Icons/Logo2.ico" type="image/x-icon">
    <link rel="apple-touch-icon" href="/assets/Icons/Logo3.png">
    <meta property="og:site_name" content="ADN Movie">
    <meta property="og:type" content="website">
    <meta property="og:title" content="ADN Movie">
    <meta property="og:description" content="Découvrez vos résonances cinématographiques">
    <meta property="og:image" content="https://adnmovie.fr/assets/Icons/Named_logo1.png">
    <meta name="twitter:card" content="summary">
    <meta name="twitter:image" content="https://adnmovie.fr/assets/Icons/Named_logo1.png">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="assets/style.css?v=<?= filemtime('assets/style.css') ?>">
    <script src="assets/js/ui.js" defer></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="assets/js/radars.js" defer></script>
    <script src="assets/js/sessions.js" defer></script>
    <script src="assets/js/comment-autocomplete.js" defer></script>
</head>
<body class="page-session" data-room-id="<?= h($roomId) ?>" data-my-id="<?= h($userId) ?>">

    <div class="container">
        <?php renderHeader($currentUser); ?>
        <?php renderNav('rooms'); ?>

        <main class="session-layout">

            <header class="session-header">
                <div class="session-title-block" style="width:100%;">
                    <span class="session-badge"><?= !empty($room['is_public']) ? 'Session Publique' : 'Session Privée' ?></span>
                    <h1><?= h($room['name']) ?></h1>
                    <code class="room-id-display" onclick="copyToClipboard('<?= h($roomId) ?>')">Code : <?= h($roomId) ?></code>
                    <?php if ($isHost): ?>
                    <div id="session-desc-wrap" style="margin-top:10px;width:100%;">
                        <div id="session-desc-display" onclick="sessionDescEdit()" style="font-size:0.8rem;color:var(--text-dim);cursor:pointer;min-height:1.4em;">
                            <?= $room['description'] ? h($room['description']) : '<span style="opacity:0.4;font-style:italic;">Ajouter une description…</span>' ?>
                        </div>
                        <div id="session-desc-edit" style="display:none;flex-direction:column;gap:8px;width:100%;">
                            <textarea id="session-desc-input" rows="3" style="width:100%;min-height:70px;background:rgba(255,255,255,0.05);border:1px solid var(--border);border-radius:8px;color:#fff;padding:10px;font-size:0.8rem;resize:vertical;outline:none;font-family:inherit;box-sizing:border-box;"><?= h($room['description'] ?? '') ?></textarea>
                            <div style="display:flex;gap:6px;">
                                <button class="btn-base active" style="font-size:0.7rem;padding:6px 14px;" onclick="sessionDescSave()">Sauvegarder</button>
                                <button class="btn-base" style="font-size:0.7rem;padding:6px 14px;" onclick="sessionDescCancel()">Annuler</button>
                            </div>
                        </div>
                    </div>
                    <?php elseif (!empty($room['description'])): ?>
                    <p style="margin-top:10px;font-size:0.8rem;color:var(--text-dim);"><?= h($room['description']) ?></p>
                    <?php endif; ?>
                </div>
                <div class="session-members" id="memberList">
                    <span class="member-count-badge">
                        <?= $memberCount ?> participant<?= $memberCount > 1 ? 's' : '' ?>
                    </span>
                    <?php foreach ($room['members'] as $mId):
                        $mUser = $allUsers[$mId] ?? null;
                        if ($mUser): renderAvatar($mUser, $mId); endif;
                    endforeach; ?>
                </div>
            </header>

            <?php if ($isHost): ?>
            <div class="session-actions-top" style="margin-bottom:20px; display:flex; align-items:center; gap:10px; width:100%;">
                <button class="btn-base active" onclick="openCreateEventModal()">Créer un évènement</button>
                <button class="btn-base btn-manage" style="margin-left:auto;" onclick="openManageMembersModal()">Manager</button>
            </div>
            <?php endif; ?>

            <section class="session-events-list" style="margin-bottom:30px;">
                <?php foreach ($validEvents as $event): ?>
                    <?php renderEventCard($event, $userId); ?>
                <?php endforeach; ?>
            </section>

            <section class="session-interaction-area">
                <nav class="session-tabs">
                    <button class="session-tab-btn active" data-target="vote">Scrutin</button>
                    <button class="session-tab-btn" data-target="chat">Transmission</button>
                    <?php if (empty($room['is_public'])): ?>
                    <button class="session-tab-btn" data-target="adn">ADN</button>
                    <?php endif; ?>
                </nav>

                <div class="session-grid-container">
                    <section id="section-vote" class="session-pane session-pane--vote active">
                        <div class="pane-header">
                            <div class="vote-header-content">
                                <h2>FILMS AU VOTE</h2>

                                <div class="search-trigger-wrapper vote-search">
                                    <input type="text" id="movieSearchInput" placeholder="Proposer un film..." autocomplete="off">
                                    <div id="searchResultsOverlay" class="search-results-overlay"></div>
                                </div>

                                <div class="sort-controls">
                                    <button class="sort-btn active" data-sort="date">RÉCENTS</button>
                                    <button class="sort-btn" data-sort="votes">VOTES</button>
                                </div>
                            </div>
                        </div>

                        <div id="sessionMoviesGrid" class="movie-grid" style="padding:16px; overflow-y:auto; gap:12px;">
                            <?php if (empty($proposed)): ?>
                                <div class="empty-state"><p>Aucun film proposé pour le moment...</p></div>
                            <?php else: ?>
                                <?php foreach (array_reverse($proposed) as $prop):
                                    $m = get_movie_smart((int)$prop['movie_id']);
                                    if ($m) {
                                        $m['id'] = $prop['movie_id'];
                                        renderMovieCard($m, [
                                            'show_votes' => true,
                                            'vote_count' => count($prop['votes']),
                                            'has_voted'  => in_array($userId, $prop['votes']),
                                            'show_badges'=> true,
                                            'is_host'    => $isHost,
                                        ]);
                                    }
                                endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </section>

                    <div class="session-right-col<?= !empty($room['is_public']) ? ' no-adn' : '' ?>">
                        <section id="section-chat" class="session-pane session-pane--chat">
                            <div class="pane-header"><h2>Flux de communication</h2></div>
                            <div id="chatMessages" class="chat-display"></div>
                            <form id="chatForm" class="chat-input-area" onsubmit="return false;">
                                <input type="text" id="chatInput" placeholder="Message… (@ mention)" autocomplete="off"
                                       data-ac-context="session"
                                       data-ac-room="<?= h($roomId) ?>">
                                <button type="submit" class="btn-base active">OK</button>
                            </form>
                        </section>

                        <?php if (empty($room['is_public'])): ?>
                        <div class="group-adn-widget">
                            <div class="group-adn-header">
                                <span class="group-adn-title">ADN GROUPÉ</span>
                            </div>
                            <div class="group-adn-target group-adn-body">
                                <p class="group-adn-loading">Calcul en cours...</p>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if (empty($room['is_public'])): ?>
                <section id="section-adn" class="session-pane session-pane--adn">
                    <div class="pane-header"><h2>ADN Groupé</h2></div>
                    <div class="group-adn-target group-adn-body">
                        <p class="group-adn-loading">Calcul en cours...</p>
                    </div>
                </section>
                <?php endif; ?>
            </section>
        </main>
    </div>

    <?php renderFooter(); ?>
    <div id="manageMembersModal" class="modal"></div>
    <div id="eventModal" class="modal"></div>

    <script src="/assets/js/session.js?v=1" defer></script>
    <?php if ($isHost): ?>
    <script>
    const SESSION_ROOM_ID = '<?= addslashes($roomId) ?>';
    function sessionDescEdit() {
        document.getElementById('session-desc-display').style.display = 'none';
        const editEl = document.getElementById('session-desc-edit');
        editEl.style.display = 'flex';
        document.getElementById('session-desc-input').focus();
    }
    function sessionDescCancel() {
        document.getElementById('session-desc-display').style.display = '';
        document.getElementById('session-desc-edit').style.display = 'none';
    }
    async function sessionDescSave() {
        const val = document.getElementById('session-desc-input').value.trim();
        const res = await fetch('api/api_room_manage.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({ action: 'update_description', room_id: SESSION_ROOM_ID, description: val }),
        });
        const data = await res.json();
        if (data.success) {
            const disp = document.getElementById('session-desc-display');
            disp.innerHTML = val ? val.replace(/</g,'&lt;') : '<span style="opacity:0.4;font-style:italic;">Ajouter une description…</span>';
            sessionDescCancel();
        }
    }
    </script>
    <?php endif; ?>
</body>
</html>
