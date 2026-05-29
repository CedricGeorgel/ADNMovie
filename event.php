<?php
require_once 'functions/utils.php';

// ── Interception crawlers OG (Discord, Facebook, Twitter…) ───────────
// Les bots ne sont pas authentifiés → on leur répond avant check_auth()
$ua        = $_SERVER['HTTP_USER_AGENT'] ?? '';
$isCrawler = (bool) preg_match(
    '/discordbot|facebookexternalhit|twitterbot|linkedinbot|slackbot|whatsapp|telegrambot|applebot|googlebot/i',
    $ua
);

if ($isCrawler) {
    $eventId = $_GET['id'] ?? null;
    if ($eventId) {
        $event = db_fetch_one(
            'SELECT e.*, m.title AS movie_title, m.poster AS movie_poster
             FROM room_events e
             LEFT JOIN movies m ON m.tmdb_id = e.movie_id
             WHERE e.id = ?',
            [$eventId]
        );
        if ($event) {
            $base  = 'https://adnmovie.fr';
            $title = $event['movie_title'] ?? 'Événement';
            $desc  = 'Projection de ' . $title . ' le '
                   . date('d/m/Y', strtotime($event['event_date']))
                   . ' à ' . ($event['event_time'] ?? '');
            if (!empty($event['location'])) {
                $desc .= '. Lieu : ' . $event['location'];
            }
            $imgUrl  = $base . '/api/og_image_generator.php?id=' . urlencode($eventId);
            $pageUrl = $base . '/event.php?id=' . urlencode($eventId);

            header('Content-Type: text/html; charset=UTF-8');
            echo '<!DOCTYPE html><html lang="fr"><head>';
            echo '<meta charset="UTF-8">';
            echo '<title>' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . ' | ADN Movie</title>';
            echo '<meta property="og:site_name" content="ADN Movie">';
            echo '<meta property="og:type"        content="website">';
            echo '<meta property="og:url"         content="' . htmlspecialchars($pageUrl, ENT_QUOTES, 'UTF-8') . '">';
            echo '<meta property="og:title"       content="' . htmlspecialchars($title,   ENT_QUOTES, 'UTF-8') . '">';
            echo '<meta property="og:description" content="' . htmlspecialchars($desc,    ENT_QUOTES, 'UTF-8') . '">';
            echo '<meta property="og:image"       content="' . htmlspecialchars($imgUrl,  ENT_QUOTES, 'UTF-8') . '">';
            echo '<meta property="og:image:width"  content="1200">';
            echo '<meta property="og:image:height" content="630">';
            echo '<meta name="twitter:card"        content="summary_large_image">';
            echo '<meta name="twitter:title"       content="' . htmlspecialchars($title,  ENT_QUOTES, 'UTF-8') . '">';
            echo '<meta name="twitter:description" content="' . htmlspecialchars($desc,   ENT_QUOTES, 'UTF-8') . '">';
            echo '<meta name="twitter:image"       content="' . htmlspecialchars($imgUrl, ENT_QUOTES, 'UTF-8') . '">';
            echo '</head><body></body></html>';
            exit;
        }
    }
    // Pas d'événement trouvé → on laisse passer la suite (affichera une redirect)
}

// ── Chargement normal (utilisateurs authentifiés) ────────────────────
require_once 'functions/auth.php';
require_once 'functions/rooms_logic.php';
require_once 'components/header.php';
require_once 'components/nav.php';
require_once 'components/footer.php';

check_auth();
$userId  = $_SESSION['user_id'];
$eventId = $_GET['id'] ?? null;
if (!$eventId) { header('Location: rooms.php'); exit; }

$event = db_fetch_one(
    'SELECT e.*, m.title AS movie_title, m.poster AS movie_poster
     FROM room_events e
     LEFT JOIN movies m ON m.tmdb_id = e.movie_id
     WHERE e.id = ?',
    [$eventId]
);
if (!$event) { header('Location: rooms.php?error=event_notfound'); exit; }

$room   = get_room($event['room_id']);
$isHost = ($room && $room['host_id'] === $userId);

$isAttending = (bool)db_fetch_one(
    'SELECT 1 FROM event_attendees WHERE event_id = ? AND user_id = ?',
    [$eventId, $userId]
);

// Participants
$attendees = db_fetch_all(
    'SELECT u.id, u.username, u.avatar
     FROM event_attendees ea
     JOIN users u ON u.id = ea.user_id
     WHERE ea.event_id = ?',
    [$eventId]
);

$currentUser  = get_user_by_id($userId);
$locationRaw  = $event['location'] ?? '';
$locType = 'text';
if (filter_var($locationRaw, FILTER_VALIDATE_URL)) $locType = 'url';
elseif (preg_match('/^\d+\s+(rue|boulevard|avenue|chemin|allée|place|route|impasse|quai)/i', $locationRaw)) $locType = 'address';

// Purge des événements passés (plus de 2 jours)
db_execute(
    'DELETE FROM room_events WHERE event_date < DATE_SUB(CURDATE(), INTERVAL 2 DAY)',
    []
);

// Métadonnées OG pour le <head> utilisateur normal
$baseUrl       = 'https://adnmovie.fr';
$ogTitle       = $event['movie_title'] ?? 'Événement';
$ogDescription = 'Projection de ' . ($event['movie_title'] ?? '') . ' le '
    . date('d/m/Y', strtotime($event['event_date']))
    . ' à ' . ($event['event_time'] ?? '') . '.';
if (!empty($event['location'])) {
    $ogDescription .= ' Lieu : ' . $event['location'];
}
$ogImageUrl = $baseUrl . '/api/og_image_generator.php?id=' . urlencode($eventId);
$ogPageUrl  = $baseUrl . '/event.php?id=' . urlencode($eventId);
$pageTitle  = htmlspecialchars($ogTitle, ENT_QUOTES, 'UTF-8') . ' | ADN Movie';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title><?= $pageTitle ?></title>
    <link rel="icon" href="/assets/Icons/Logo2.ico" type="image/x-icon">
    <link rel="apple-touch-icon" href="/assets/Icons/Logo3.png">

    <!-- Balises Open Graph -->
    <meta property="og:site_name" content="ADN Movie">
    <meta property="og:type"        content="website">
    <meta property="og:url"         content="<?= htmlspecialchars($ogPageUrl,      ENT_QUOTES, 'UTF-8') ?>">
    <meta property="og:title"       content="<?= htmlspecialchars($ogTitle,        ENT_QUOTES, 'UTF-8') ?>">
    <meta property="og:description" content="<?= htmlspecialchars($ogDescription,  ENT_QUOTES, 'UTF-8') ?>">
    <meta property="og:image"       content="<?= htmlspecialchars($ogImageUrl,     ENT_QUOTES, 'UTF-8') ?>">
    <meta property="og:image:width"  content="1200">
    <meta property="og:image:height" content="630">

    <!-- Twitter / Discord fallback -->
    <meta name="twitter:card"        content="summary_large_image">
    <meta name="twitter:title"       content="<?= htmlspecialchars($ogTitle,       ENT_QUOTES, 'UTF-8') ?>">
    <meta name="twitter:description" content="<?= htmlspecialchars($ogDescription, ENT_QUOTES, 'UTF-8') ?>">
    <meta name="twitter:image"       content="<?= htmlspecialchars($ogImageUrl,    ENT_QUOTES, 'UTF-8') ?>">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="assets/style.css?v=<?= filemtime('assets/style.css') ?>">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <meta name="theme-color" content="#050505">

    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
</head>
<body class="page-event" data-room-id="<?= h($event['room_id']) ?>" data-event-id="<?= h($eventId) ?>">
    <div class="container">
        <?php renderHeader($currentUser); ?>
        <?php renderNav('rooms'); ?>
        <main style="margin-top:30px;">
            <a href="session.php?id=<?= h($event['room_id']) ?>" class="back-link">← Retour à la session</a>
            <div class="user-dashboard-split">
                <div class="dash-column" style="flex:2;">
                    <div class="event-hero">
                        <div class="event-hero-bg" style="background-image:url('<?= h($event['movie_poster'] ?? '') ?>');"></div>
                        <div class="event-hero-content">
                            <img src="<?= h($event['movie_poster'] ?? '') ?>" class="event-hero-poster">
                            <div class="event-hero-text">
                                <span class="session-badge">Projection</span>
                                <h1><?= h($event['movie_title'] ?? 'Séance') ?></h1>
                                <p style="color:var(--pastel-blue);font-weight:800;"><?= $event['event_date'] ? date('d/m/Y', strtotime($event['event_date'])) : '' ?> à <?= h($event['event_time'] ?? '') ?></p>
                            </div>
                        </div>
                    </div>
                    <div class="dash-card" style="margin-top:20px;display:flex;justify-content:space-between;align-items:center;">
                        <span style="font-weight:800;font-size:0.8rem;"><?= $isAttending ? 'VOUS PARTICIPEZ ✓' : 'PARTICIPER ?' ?></span>
                        <button onclick="toggleEventAttendance('<?= h($eventId) ?>')" class="btn-base <?= $isAttending ? '' : 'active' ?>">
                            <?= $isAttending ? 'Se désister' : 'Rejoindre' ?>
                        </button>
                    </div>

                    <!-- Ajouter au calendrier -->
                    <div class="dash-card" style="margin-top:20px;">
                        <h4 style="margin-bottom:12px;">Ajouter au calendrier</h4>
                        <div style="display:flex;gap:10px;flex-wrap:wrap;">
                            <a href="api/api_export_cal.php?id=<?= h($eventId) ?>&type=google" target="_blank" class="btn-base active" style="flex:1;text-align:center;">Google Calendar</a>
                            <a href="api/api_export_cal.php?id=<?= h($eventId) ?>&type=ics" class="btn-base" style="flex:1;text-align:center;">Télécharger .ics</a>
                        </div>
                    </div>
                </div>

                <div class="dash-column" style="flex:1;">
                    <div class="dash-card">
                        <h4>Lieu</h4>
                        <?php if ($locType === 'url'): ?>
                            <a href="<?= h($locationRaw) ?>" target="_blank" class="btn-base active" style="width:100%;margin-top:10px;">Lien du salon</a>
                        <?php elseif ($locType === 'address'): ?>
                            <p style="font-size:0.9rem;font-weight:700;"><?= h($locationRaw) ?></p>
                            <div id="osm-map" class="map-container"></div>
                        <?php else: ?>
                            <p style="font-weight:800;color:var(--pastel-blue);"><?= h($locationRaw) ?></p>
                        <?php endif; ?>
                    </div>
                    <div class="dash-card" style="margin-top:20px;">
                        <h4>Participants (<?= count($attendees) ?>)</h4>
                        <div class="attendee-grid">
                            <?php foreach ($attendees as $att): ?>
                                <img src="<?= h($att['avatar']) ?>" class="attendee-avatar" title="<?= h($att['username']) ?>" onerror="this.src='assets/default-avatar.png';">
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>

            <?php if ($isHost): ?>
            <div class="dash-card" style="margin-top:30px;border-color:rgba(255,77,77,0.3);">
                <h4 style="color:#ff4d4d;">Gestion Hôte</h4>
                <div style="display:flex;gap:10px;margin-top:15px;">
                    <button class="btn-base" onclick="openEditEventModal()" style="flex:1;">Modifier</button>
                    <button class="btn-base btn-manage" onclick="deleteEvent('<?= h($eventId) ?>', '<?= h($event['room_id']) ?>')" style="flex:1;">Supprimer l'évènement</button>
                </div>
            </div>
            <?php endif; ?>
        </main>
    </div>

    <div id="editEventModal" class="modal">
        <div class="modal-content">
            <button class="modal-close" onclick="closeModal('editEventModal')">&times;</button>
            <h3>Modifier l'évènement</h3>
            <form id="editEventForm" class="event-form-grid" style="margin-top:20px;">
                <input type="hidden" name="event_id" value="<?= h($eventId) ?>">
                <input type="hidden" name="room_id" value="<?= h($event['room_id']) ?>">
                <div class="slider-group"><label class="slider-labels">Date</label><input type="date" name="date" class="input-room-create" value="<?= h($event['event_date'] ?? '') ?>" required></div>
                <div class="slider-group"><label class="slider-labels">Heure</label><input type="time" name="time" class="input-room-create" value="<?= h($event['event_time'] ?? '') ?>" required></div>
                <div class="slider-group"><label class="slider-labels">Lieu / Lien</label><input type="text" name="location" class="input-room-create" value="<?= h($event['location'] ?? '') ?>"></div>
                <div class="modal-actions" style="margin-top:20px;"><button type="button" class="btn-base active" onclick="submitEditEvent()">Enregistrer</button></div>
            </form>
        </div>
    </div>

    <?php renderFooter(); ?>
    <script src="assets/js/ui.js"></script>
    <script src="assets/js/sessions.js"></script>
    <?php if ($locType === 'address'): ?>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>
        fetch(`https://nominatim.openstreetmap.org/search?format=json&q=<?= urlencode($locationRaw) ?>`)
            .then(r => r.json()).then(d => {
                if (d.length > 0) {
                    const map = L.map('osm-map').setView([d[0].lat, d[0].lon], 15);
                    L.tileLayer('https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png', { attribution: '&copy; OpenStreetMap &copy; CARTO' }).addTo(map);
                    L.marker([d[0].lat, d[0].lon]).addTo(map);
                }
            });
    </script>
    <?php endif; ?>
    <script src="/assets/js/event.js?v=1" defer></script>
</body>
</html>