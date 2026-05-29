<?php
require_once 'functions/utils.php';
require_once 'functions/auth.php';
require_once 'functions/rooms_logic.php';
require_once 'functions/badges_logic.php';
require_once 'functions/achievements_logic.php';
require_once 'functions/friends_logic.php';
require_once 'components/header.php';
require_once 'components/nav.php';
require_once 'components/footer.php';
require_once 'components/adn-chart.php';
require_once 'components/content-row.php';
require_once 'components/event-card.php';
require_once 'components/room-card.php';
require_once 'config/settings.php';

check_auth();

$userId       = $_GET['id'] ?? $_SESSION['user_id'];
$isOwnProfile = ($userId === $_SESSION['user_id']);
$user         = get_user_by_id($userId);

if (!$user || !empty($user['is_anonymized'])) { header('Location: 404.php'); exit; }

if ($isOwnProfile) {
    check_and_award_achievements($_SESSION['user_id']);
}

$allUsers      = get_users_directory();
$adnData       = get_user_adn($userId) ?: ['count' => 0, 'scores' => []];
$seriesAdnData = get_user_series_adn($userId);
$badgeLibrary  = get_badge_library();
$accountCreationDate = isset($user['created_at']) ? date('d/m/Y', strtotime($user['created_at'])) : 'Date inconnue';

$defaultDesc     = "Spécimen trouvé dans une salle sombre de cinéma, l'identification n'a pas encore été faite, mais le spécimen semble se plaire sur un canapé devant un bon film.";
$userDescription = !empty($user['description']) ? $user['description'] : $defaultDesc;

$userBadgesRaw = db_fetch_all(
    'SELECT badge_id FROM user_badges WHERE user_id = ? ORDER BY earned_at ASC',
    [$userId]
) ?: [];
$userBadges = array_column($userBadgesRaw, 'badge_id');

$myActiveRooms = array_slice(get_user_rooms($userId) ?: [], 0, 4);

$userEvents = db_fetch_all(
    'SELECT e.id, e.event_date, e.event_time, e.location,
            m.title AS movie_title, m.poster AS movie_poster
     FROM event_attendees ea
     JOIN room_events e  ON e.id = ea.event_id
     LEFT JOIN movies m  ON m.tmdb_id = e.movie_id
     WHERE ea.user_id = ?
       AND e.event_date >= CURDATE()
     ORDER BY e.event_date ASC, e.event_time ASC',
    [$userId]
) ?: [];

$privacySettings  = json_decode($user['privacy_settings'] ?? '{}', true) ?: [];
$adnPublic        = $privacySettings['adn_public']        ?? true;
$collectionPublic = $privacySettings['collection_public'] ?? true;
$wishlistPublic   = $privacySettings['wishlist_public']   ?? true;
$contentPublic    = $privacySettings['content_public']    ?? true;

$backdropImage = $user['backdrop_poster'] ?? null;

$profileStats = db_fetch_one(
    "SELECT
        (SELECT COUNT(*) FROM friendships WHERE (requester_id=? OR addressee_id=?) AND status='accepted') AS friend_count,
        (SELECT COUNT(*) FROM ratings        WHERE user_id=?) AS rating_count,
        (SELECT COUNT(*) FROM movie_comments WHERE user_id=?) AS comment_count,
        (SELECT COUNT(*) FROM user_content   WHERE user_id=? AND is_public=1) AS archive_count",
    [$userId, $userId, $userId, $userId, $userId]
) ?: ['friend_count' => 0, 'rating_count' => 0, 'comment_count' => 0, 'archive_count' => 0];

$friendsList = get_friends($userId);

if ($isOwnProfile && !empty($_SESSION['signal_perdu'])) {
    $signalPerdu = true;
    unset($_SESSION['signal_perdu']);
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title><?= h($user['username']) ?> - Profil Opérateur</title>
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
    <?php if (function_exists('renderScripts')) renderScripts(); else echo '<script src="https://cdn.jsdelivr.net/npm/chart.js"></script><script src="assets/js/radars.js" defer></script>'; ?>
    <meta name="theme-color" content="#050505">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
</head>
<body class="page-user">
    <?php if ($backdropImage): ?>
    <div class="user-backdrop-blur" style="background-image:url('<?= h($backdropImage) ?>');"></div>
    <?php endif; ?>
    <div class="container">
        <?php renderHeader($user); ?>
        <?php renderNav('user'); ?>

        <main class="user-profile-layout">

            <!-- ═══════════════ HERO ═══════════════ -->
            <section class="hero-split-container">
                <div class="hero-left-pane">
                    <div class="user-avatar-large">
                        <img id="mainAvatar" src="<?= h($user['avatar']) ?>" onerror="this.src='assets/default-avatar.png';">
                    </div>
                    <div>
                        <h1 id="mainUsername" class="user-name" style="margin:0 0 5px 0; font-size:1.4rem;"><?= h($user['username']) ?></h1>
                        <?= render_role_badge($user['role'] ?? 'user') ?>
                    </div>
                    <span style="color:var(--text-dim); font-size:0.7em;">Membre depuis le <?= $accountCreationDate ?></span>

                    <div class="user-stats-bar">
                        <span class="user-stat-item<?= $isOwnProfile ? ' clickable' : '' ?>"
                              data-label="Liens"
                              <?= $isOwnProfile ? 'onclick="document.getElementById(\'friendsModal\').classList.remove(\'hidden\')"' : '' ?>>
                            <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 442.666 442.666" fill="var(--pastel-blue)"><path d="M230.729,340.323l-144.152.197c-17.95-2.755-28.38-18.263-27.346-36.097,2.218-38.222,37.067-63.929,72.938-69.017,13.023-1.847,25.897-1.753,39.237-.833,17.537,1.21,34.179,6.851,48.808,16.164,23.742,15.115,39.794,41.588,32.385,70.207-2.485,9.596-10.552,19.364-21.871,19.379Z"/><path d="M354.568,340.065l-98.807.414c18.12-16.761,23.5-41.348,14.311-63.848-5.57-13.641-16.308-23.722-29.598-30.832,14.227-9.213,29.571-11.529,45.726-11.845,24.431-.479,47.839,5.535,67.345,20.118,16.658,11.867,27.815,28.852,29.795,49.459,1.672,17.397-9.561,36.454-28.772,36.535Z"/><path d="M164.994,206.594c-16.752,2.38-31.876-2.644-43.452-13.322-11.083-10.223-17.061-25.615-16.286-41.597,1.511-31.175,29.16-53.339,60.275-48.983,19.691,2.756,36.954,16.293,42.974,35.659,9.593,30.864-11.087,63.636-43.511,68.243Z"/><path d="M293.792,206.539c-17.415,2.601-32.769-3.172-44.451-15.113-13.432-13.731-18.711-32.959-12.944-52.477,4.679-15.835,18.063-30.036,36.166-34.918,26.335-7.101,54.837,7.106,63.833,33.329,10.599,30.894-9.995,64.307-42.604,69.178Z"/></svg>
                            <span class="user-stat-value"><?= (int)$profileStats['friend_count'] ?></span>
                        </span>
                        <span class="user-stat-item" data-label="Analyses">
                            <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 442.666 442.666" fill="var(--pastel-blue)"><path d="M317.824,349.076c-.036,8.913-10.045,13.285-17.107,12.355-7.826-1.031-13.332-7.491-13.436-15.933l-.156-12.755-131.125.031c-.041,5.983.038,10.66-.254,15.529-.515,8.601-8.337,13.621-16.075,13.243-7.807-.382-14.84-6.93-14.877-15.693l-.189-43.711c1.092-21.715,11.026-41.877,29.184-54.096l40.732-27.41c-15.605-10.057-30.84-19.049-44.867-30.271-15.651-12.52-24.392-30.487-24.895-50.22-.375-14.716-.327-28.8.039-43.444.218-8.703,7.066-15.11,15.003-15.573,8.201-.479,15.955,5.73,16.083,14.695l.241,16.938,131.017-.003.134-16.956c.067-8.522,7.55-14.545,15.356-14.665,7.703-.118,15.173,6.053,15.267,14.624.17,15.528.564,30.385-.133,46.031-.898,20.163-11.913,37.899-27.978,49.641-13.96,10.203-27.738,18.812-43.036,28.756l41.132,27.357c17.306,11.511,27.928,29.312,30.147,49.95l-.209,51.581ZM263.627,166.574c14.335-.051,24.014-12.728,23.764-25.83l-131.396-.052c-.99,15.051,10.299,26.225,25.15,26.173l82.482-.291ZM232.2,196.984l-23.629-.44,12.594,8.002,11.035-7.562ZM237.343,248.401l-17.489-10.856-16.037,10.769,33.526.087ZM287.611,302.942c.387-12.633-6.807-23.299-18.767-26.324l-91.676.224c-13.691.034-22.296,13.544-21.442,26.108l131.885-.008Z"/></svg>
                            <span class="user-stat-value"><?= (int)$profileStats['rating_count'] ?></span>
                        </span>
                        <span class="user-stat-item" data-label="Commentaires">
                            <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 442.666 442.666" fill="var(--pastel-blue)"><path d="M193.347,352.051c-5.486,3.724-12.376,3.074-17.983.227-3.961-2.012-8.49-8.134-8.507-14.125l-.113-38.926-20.098-.23c-38.418-.439-70.99-30.918-72.294-69.942-.803-24.024-1.142-46.428.1-70.919,1.831-36.121,32.151-70.121,69.285-70.101l154.768.083c39.002.021,70.473,35.443,70.492,73.006l.034,65.842c.021,39.23-32.211,71.374-71.304,71.965l-26.724.404-77.657,52.716Z"/></svg>
                            <span class="user-stat-value"><?= (int)$profileStats['comment_count'] ?></span>
                        </span>
                        <span class="user-stat-item" data-label="Archives">
                            <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 442.666 442.666" fill="var(--pastel-blue)"><path d="M342.805,349.837c-.004,11.387-9.791,22.249-21.007,22.25l-200.557.006c-14.186,0-21.447-13.353-21.445-25.896l.039-250.478c.002-13.39,7.669-23.582,21.443-25.147l149.592.078c9.643.005,18.666,4.222,25.21,10.784l34.876,34.972c7.698,7.719,10.81,17.451,11.913,28.335l-.064,205.096ZM288.93,165.894c-23.463-3.673-40.446-20.855-45.073-43.365l-.503-22.994-116.126.027.002,244.745,188.67.019.012-177.559c-9.033-.188-17.774.569-26.982-.873Z"/><path d="M278.278,225.169l-115.163-.017c-7.462-.001-11.545-8.301-11.622-14.263-.078-6.077,4.288-14.211,11.85-14.219l114.582-.124c7.581-.008,12.706,7.079,13.102,13.465.408,6.572-4.316,15.16-12.749,15.159Z"/><path d="M245.414,282.204l-82.316-.1c-7.041-.009-11.367-7.68-11.588-13.508-.201-5.306,3.862-13.938,10.71-13.942l84.677-.05c7.605-.005,11.792,8.044,11.466,14.23-.332,6.296-4.862,13.38-12.949,13.37Z"/><path d="M222.137,166.956c-20.008.959-39.358.612-59.049.278-7.048-.12-11.264-7.56-11.53-13.534s4.275-14.046,11.552-14.07l55.899-.186c7.325-.024,12.259,5.542,13.343,12.34.848,5.318-2.199,14.789-10.214,15.173Z"/></svg>
                            <span class="user-stat-value"><?= (int)$profileStats['archive_count'] ?></span>
                        </span>
                    </div>

                    <?php if ($isOwnProfile): ?>
                        <button onclick="openProfileEditor('profil')"
                                class="btn-base" style="width:100%; padding:8px 15px; font-size:0.7rem; margin-top:5px;">
                            ✏️ Éditer le profil
                        </button>
                    <?php endif; ?>

                    <button id="btnShareAdn" class="btn-copy-adn" onclick="copyAdnLink('<?= h($userId) ?>')">
                        Partager mon ADN
                    </button>
                </div>

                <div class="hero-right-pane">
                    <h3 style="font-size:0.7rem; color:var(--text-dim); text-transform:uppercase; letter-spacing:2px; margin-bottom:15px;">Dossier d'identification</h3>
                    <p id="mainDescription" style="font-size:0.9rem; line-height:1.6; color:var(--text-muted); margin:0;">
                        <?= nl2br(h($userDescription)) ?>
                    </p>
                </div>
            </section>

            <!-- ═══════════════ ADN & BADGES ═══════════════ -->
            <?php
                $hasSeriesAdn   = ($seriesAdnData['count'] >= 1);
                $seriesScores   = $seriesAdnData['scores'] ?? array_fill(0, 8, 0);
                $seriesStats    = $seriesAdnData['stats']  ?? [];
                $hasPlatforms   = $isOwnProfile && !empty(json_decode($user['user_platforms'] ?? '[]', true));
            ?>
            <div class="dash-card-header"><h4>ANALYSE &amp; DISTINCTIONS</h4></div>
            <?php if ($signalPerdu): ?>
            <div class="signal-perdu-banner" id="signal-perdu-banner">
                Réinitialisation de la séquence… Bon retour parmi nous, <strong><?= h($user['username']) ?></strong>.
            </div>
            <?php endif; ?>
            <section class="user-adn">
                <div class="adn-flex-container">
                    <div class="adn-radar-wrapper">
                        <div class="adn-radar-container">
                            <?php renderAdnChart(
                                $adnData['scores'] ?? [],
                                $seriesScores,
                                0, 'userAdnChart', false, 0,
                                $adnData['stats'] ?? null,
                                $seriesStats
                            ); ?>
                            <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.5/gsap.min.js"></script>
                            <script src="assets/js/adn-zoom.js"></script>
                        </div>
                    </div>
                    <div class="user-badges-section">
                        <?php if (empty($userBadges)): ?>
                            <div class="empty-placeholder">Aucun badge enregistré.</div>
                        <?php else: ?>
                            <?php foreach ($userBadges as $badgeKey):
                                if (!isset($badgeLibrary[$badgeKey])) continue;
                                $b = $badgeLibrary[$badgeKey]; ?>
                                <?php
                                    $descParts = explode("\n", $b['description'] ?? $b['label'], 2);
                                    $descMain  = $descParts[0];
                                    $descCond  = $descParts[1] ?? '';
                                ?>
                                <div class="badge-item">
                                    <div style="color:<?= $b['color'] ?>;">
                                        <?php if (isset($b['svg']) && file_exists($b['svg'])): ?>
                                            <img src="<?= h($b['svg']) ?>" style="width:24px;">
                                        <?php else: ?>
                                            <i class="fa-solid <?= $b['icon'] ?? '' ?>"></i>
                                        <?php endif; ?>
                                    </div>
                                    <div class="badge-info">
                                        <span class="badge-name" style="color:<?= $b['color'] ?>;font-size:0.65em;font-weight:800;"><?= h($b['label']) ?></span>
                                    </div>
                                    <div class="badge-tt">
                                        <span class="badge-tt-main"><?= h($descMain) ?></span>
                                        <?php if ($descCond): ?>
                                        <span class="badge-tt-cond"><?= h($descCond) ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- L'abonnement fait pour moi — toujours visible (profil propre) -->
                <div id="user-best-fit-card" style="display:none;margin-top:24px;padding:12px 14px;border-radius:12px;border:1px solid rgba(167,199,231,0.25);background:rgba(167,199,231,0.04);">
                    <p style="font-size:0.5rem;font-weight:800;letter-spacing:2px;text-transform:uppercase;color:var(--pastel-blue);margin-bottom:10px;opacity:0.8;">L'abonnement fait pour moi</p>
                    <div style="display:flex;align-items:center;gap:12px;">
                        <div style="width:38px;height:38px;border-radius:8px;background:rgba(255,255,255,0.05);flex-shrink:0;overflow:hidden;">
                            <img id="user-fit-logo" src="" alt="" style="width:38px;height:38px;object-fit:cover;display:none;">
                        </div>
                        <div style="flex:1;min-width:0;">
                            <div id="user-fit-name" style="font-size:0.72rem;font-weight:800;color:var(--text-main);"></div>
                            <div id="user-fit-detail" style="font-size:0.57rem;color:var(--text-dim);margin-top:3px;"></div>
                        </div>
                        <div style="flex-shrink:0;text-align:right;">
                            <div id="user-fit-price" style="font-size:0.7rem;font-weight:800;color:var(--pastel-blue);"></div>
                            <div id="user-fit-savings" style="font-size:0.52rem;color:#4ade80;margin-top:2px;"></div>
                        </div>
                    </div>
                </div>

                <?php if ($isOwnProfile): ?>
                <div id="user-renta-wrap" style="display:none;margin-top:16px;padding-top:16px;border-top:1px solid var(--border);">

                    <!-- Ce mois-ci header -->
                    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:6px;">
                        <div id="user-mois-left" style="display:none;align-items:center;gap:6px;">
                            <span style="font-size:0.55rem;font-weight:800;letter-spacing:2px;text-transform:uppercase;color:var(--text-dim);">Ce mois-ci</span>
                            <span id="user-total-cost" style="display:none;font-size:0.55rem;color:var(--text-dim);opacity:0.7;">· <span id="user-total-cost-val"></span> €/mois</span>
                            <button onclick="toggleRentaDesc('desc-mois')" style="background:none;border:none;cursor:pointer;color:var(--text-dim);font-size:0.65rem;padding:0;line-height:1;opacity:0.7;" title="En savoir plus">ⓘ</button>
                        </div>
                        <button id="user-history-btn" onclick="openHistoryModal()" style="display:none;font-size:0.6rem;color:var(--pastel-blue);font-weight:700;background:none;border:none;cursor:pointer;padding:0;text-decoration:underline;text-underline-offset:2px;letter-spacing:0.3px;">Mon historique</button>
                    </div>
                    <div id="desc-mois" style="display:none;font-size:0.6rem;color:var(--text-dim);line-height:1.6;margin-bottom:12px;padding:10px 12px;background:rgba(255,255,255,0.03);border-radius:8px;border:1px solid var(--border);">
                        Ce que vous avez regardé ce mois-ci sur chaque plateforme, comparé à ce que vous payez. Un abonnement est « rentable » si la valeur des films notés disponibles (au prix moyen de la location VOD) dépasse le prix mensuel. Valeur calculée sur la base de 4,99 € par film (prix moyen constaté de location VOD).
                    </div>

                    <div id="user-renta-subs"></div>

                    <div id="user-renta-match-wrap" style="display:none;margin-top:20px;">
                        <div style="display:flex;align-items:center;gap:6px;margin-bottom:6px;">
                            <span style="font-size:0.55rem;font-weight:800;letter-spacing:2px;text-transform:uppercase;color:var(--text-dim);">Pertinence de vos abonnements</span>
                            <button onclick="toggleRentaDesc('desc-pertinence')" style="background:none;border:none;cursor:pointer;color:var(--text-dim);font-size:0.65rem;padding:0;line-height:1;opacity:0.7;" title="En savoir plus">ⓘ</button>
                        </div>
                        <div id="desc-pertinence" style="display:none;font-size:0.6rem;color:var(--text-dim);line-height:1.6;margin-bottom:12px;padding:10px 12px;background:rgba(255,255,255,0.03);border-radius:8px;border:1px solid var(--border);">
                            Quelle part du catalogue disponible sur chaque plateforme correspond à votre ADN cinéma, indépendamment de ce que vous avez regardé ce mois-ci. Un score élevé signifie que la plateforme est faite pour vous, même si vous ne l’utilisez pas encore assez.
                        </div>
                        <div id="user-renta-match"></div>
                    </div>
                </div>

                <!-- Modale historique -->
                <div id="user-history-modal" onclick="if(event.target===this)closeHistoryModal()" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.85);z-index:1000;overflow:auto;align-items:center;justify-content:center;padding:20px;">
                    <div style="max-width:580px;width:100%;background:var(--card-bg);border-radius:20px;border:1px solid var(--border);padding:24px;margin:auto;">
                        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">
                            <h4 style="margin:0;font-size:0.65rem;letter-spacing:2px;text-transform:uppercase;color:var(--text-dim);">Évolution sur 12 mois</h4>
                            <button onclick="closeHistoryModal()" style="background:none;border:none;color:var(--text-dim);font-size:1.1rem;cursor:pointer;padding:4px 8px;line-height:1;">✕</button>
                        </div>
                        <div id="user-history-loading" style="font-size:0.7rem;color:var(--text-dim);">Chargement…</div>
                        <canvas id="user-history-chart" style="display:none;max-height:260px;"></canvas>
                    </div>
                </div>
                <?php endif; ?>

                <style>
                    .user-sub-card{display:flex;align-items:center;gap:12px;padding:12px 14px;border-radius:12px;border:1px solid var(--border);background:rgba(255,255,255,0.02);margin-bottom:8px;}
                    .user-sub-logo{width:38px;height:38px;border-radius:8px;object-fit:cover;flex-shrink:0;}
                    .user-sub-info{flex:1;min-width:0;}
                    .user-sub-name{font-size:0.72rem;font-weight:700;}
                    .user-sub-detail{font-size:0.57rem;color:var(--text-dim);margin-top:2px;}
                    .user-sub-bar-wrap{margin-top:6px;height:3px;background:rgba(255,255,255,0.06);border-radius:2px;overflow:hidden;}
                    .user-sub-bar{height:100%;border-radius:2px;transition:width 0.6s ease;}
                    .user-roi-badge{display:inline-block;padding:3px 8px;border-radius:20px;font-size:0.55rem;font-weight:900;letter-spacing:1px;flex-shrink:0;}
                    .user-roi-good{background:rgba(74,222,128,0.12);color:#4ade80;border:1px solid rgba(74,222,128,0.3);}
                    .user-roi-mid{background:rgba(232,192,122,0.12);color:var(--color-amber);border:1px solid rgba(232,192,122,0.3);}
                    .user-roi-bad{background:rgba(255,77,77,0.1);color:#ff6b6b;border:1px solid rgba(255,77,77,0.25);}
                    .user-roi-none{background:rgba(255,255,255,0.04);color:var(--text-dim);border:1px solid var(--border);}
                    .user-match-row{display:flex;align-items:center;gap:8px;margin-bottom:7px;}
                    .user-match-logo{width:24px;height:24px;border-radius:5px;object-fit:cover;flex-shrink:0;}
                    .user-match-name{font-size:0.6rem;color:var(--text-dim);flex:1;}
                    .user-match-bar-wrap{flex:2;height:3px;background:rgba(255,255,255,0.06);border-radius:2px;overflow:hidden;}
                    .user-match-bar{height:100%;background:var(--pastel-blue);border-radius:2px;transition:width 0.6s ease;}
                    .user-match-score{font-size:0.6rem;font-weight:700;color:var(--pastel-blue);width:32px;text-align:right;}
                </style>
                <script>
                function toggleRentaDesc(id) {
                    const el = document.getElementById(id);
                    el.style.display = el.style.display === 'none' ? 'block' : 'none';
                }

                let _historyChart = null;
                let _historyLoaded = false;
                let _historyData   = null;

                function openHistoryModal() {
                    document.getElementById('user-history-modal').style.display = 'flex';
                    document.body.style.overflow = 'hidden';
                    if (!_historyLoaded) { _historyLoaded = true; _loadHistoryChart(); }
                }

                function closeHistoryModal() {
                    document.getElementById('user-history-modal').style.display = 'none';
                    document.body.style.overflow = '';
                }

                async function _loadHistoryChart() {
                    try {
                        const data = _historyData || await fetch('api/api_renta_history.php').then(r => r.json());
                        document.getElementById('user-history-loading').style.display = 'none';
                        if (!data.success || !data.datasets.length) {
                            document.getElementById('user-history-loading').style.display = 'block';
                            document.getElementById('user-history-loading').textContent = 'Aucune donnée disponible.';
                            return;
                        }
                        data.datasets.forEach(ds => {
                            ds.pointBackgroundColor = ds.data.map(v => v === null ? 'transparent' : v >= 0 ? '#4ade80' : '#f87171');
                            ds.fill = false;
                        });
                        const canvas = document.getElementById('user-history-chart');
                        canvas.style.display = 'block';
                        _historyChart = new Chart(canvas, {
                            type: 'line',
                            data: { labels: data.labels, datasets: data.datasets },
                            options: {
                                responsive: true,
                                maintainAspectRatio: true,
                                interaction: { mode: 'index', intersect: false },
                                plugins: {
                                    legend: { labels: { color: '#9ca3af', font: { size: 10 }, boxWidth: 12 } },
                                    tooltip: {
                                        callbacks: {
                                            label: ctx => {
                                                const v = ctx.parsed.y;
                                                if (v === null) return null;
                                                const sign = v >= 0 ? '+' : '';
                                                return ` ${v >= 0 ? '✓' : '✗'} ${ctx.dataset.label} — ${sign}${v.toFixed(2).replace('.', ',')} €`;
                                            },
                                        },
                                    },
                                },
                                scales: {
                                    x: { ticks: { color: '#9ca3af', font: { size: 10 } }, grid: { color: 'rgba(255,255,255,0.04)' } },
                                    y: {
                                        ticks: { color: '#9ca3af', font: { size: 10 }, callback: v => (v >= 0 ? '+' : '') + v.toFixed(0) + ' €' },
                                        grid: { color: ctx => ctx.tick.value === 0 ? 'rgba(255,255,255,0.2)' : 'rgba(255,255,255,0.04)' },
                                    },
                                },
                            },
                        });
                    } catch(e) {
                        document.getElementById('user-history-loading').textContent = 'Erreur de chargement.';
                    }
                }

                (async () => {
                    const TMDB = 'https://image.tmdb.org/t/p/original';
                    try {
                        const [subRes, recRes, histRes] = await Promise.all([
                            fetch('api/api_renta_subscriptions.php'),
                            fetch('api/api_renta_recommend.php'),
                            fetch('api/api_renta_history.php'),
                        ]);
                        const subData  = await subRes.json();
                        const recData  = await recRes.json();
                        const histData = await histRes.json();

                        // ── Mon historique : visible si historique existe ──
                        if (histData.success && histData.datasets && histData.datasets.length) {
                            _historyData = histData;
                            const histBtn = document.getElementById('user-history-btn');
                            if (histBtn) histBtn.style.display = 'inline';
                            const rentaWrap = document.getElementById('user-renta-wrap');
                            if (rentaWrap) rentaWrap.style.display = 'block';
                        }

                        // ── L'abonnement fait pour moi (toujours, sans condition) ──
                        if (recData.success && recData.best_fit) {
                            const bf = recData.best_fit;
                            document.getElementById('user-fit-name').textContent   = bf.provider_name;
                            document.getElementById('user-fit-detail').textContent = `${bf.match_score}% de correspondance ADN${bf.film_count ? ' · ' + bf.film_count + ' films disponibles' : ''}`;
                            if (bf.monthly_price !== null) {
                                document.getElementById('user-fit-price').textContent = bf.monthly_price.toFixed(2).replace('.', ',') + ' €/mois';
                            }
                            if (bf.logo_path) {
                                const img = document.getElementById('user-fit-logo');
                                img.src = TMDB + bf.logo_path;
                                img.alt = bf.provider_name;
                                img.style.display = 'block';
                            }
                            document.getElementById('user-best-fit-card').style.display = 'block';
                        }

                        // ── Sections dépendantes des abonnements ──
                        if (!subData.success || !subData.subscriptions.length) return;

                        // ── Ce mois-ci : visible si abonnements existent ──
                        const moisLeftEl = document.getElementById('user-mois-left');
                        if (moisLeftEl) moisLeftEl.style.display = 'flex';
                        const rentaWrapSub = document.getElementById('user-renta-wrap');
                        if (rentaWrapSub) rentaWrapSub.style.display = 'block';

                        const totalCost = subData.subscriptions.reduce((sum, s) => sum + (s.monthly_price || 0), 0);

                        // Mise à jour des économies potentielles sur la card best_fit
                        if (recData.best_fit && recData.best_fit.monthly_price !== null && totalCost > 0) {
                            const savings = totalCost - recData.best_fit.monthly_price;
                            if (savings > 0.5) {
                                document.getElementById('user-fit-savings').textContent = '−' + savings.toFixed(2).replace('.', ',') + ' €/mois économisés';
                            }
                        }

                        const costEl = document.getElementById('user-total-cost');
                        const costValEl = document.getElementById('user-total-cost-val');
                        if (costEl && costValEl && totalCost > 0) {
                            costValEl.textContent = totalCost.toFixed(2).replace('.', ',');
                            costEl.style.display = 'inline';
                        }

                        const subsEl = document.getElementById('user-renta-subs');
                        if (subsEl) {
                            subsEl.innerHTML = subData.subscriptions.map(s => {
                                const roiClass = s.roi === null ? 'user-roi-none' : s.roi >= 1.5 ? 'user-roi-good' : s.roi >= 0.8 ? 'user-roi-mid' : 'user-roi-bad';
                                const roiLabel = s.roi === null ? '—' : s.roi >= 1.5 ? '✓ Rentable' : s.roi >= 0.8 ? '≈ Correct' : '✗ Sous-utilisé';
                                const roiPct   = s.roi !== null ? Math.min(s.roi * 67, 100) : 0;
                                const barColor = s.roi === null ? '#444' : s.roi >= 1.5 ? '#4ade80' : s.roi >= 0.8 ? '#E8C07A' : '#ff6b6b';
                                const price    = s.monthly_price !== null ? s.monthly_price.toFixed(2).replace('.', ',') + ' €/mois' : '';
                                const detail   = s.watched_month === 0
                                    ? `${s.unseen_count} films disponibles non vus`
                                    : `${s.watched_month} film${s.watched_month > 1 ? 's' : ''} notés disponibles · ${s.rental_value.toFixed(2).replace('.', ',')} €`;
                                const cancelBtn = (s.roi !== null && s.roi < 0.8 && s.cancel_url)
                                    ? `<a href="${s.cancel_url}" target="_blank" rel="noopener noreferrer" style="font-size:0.5rem;font-weight:800;color:#f87171;text-decoration:none;display:block;margin-top:3px;opacity:0.85;" onmouseover="this.style.textDecoration='underline'" onmouseout="this.style.textDecoration='none'">Résilier →</a>`
                                    : '';
                                return `<div class="user-sub-card">
                                    <img src="${TMDB}${s.logo_path}" alt="${s.provider_name}" class="user-sub-logo" onerror="this.style.display='none'">
                                    <div class="user-sub-info">
                                        <div class="user-sub-name">${s.provider_name}</div>
                                        <div class="user-sub-detail">${detail}${price ? ' · ' + price : ''}</div>
                                        <div class="user-sub-bar-wrap"><div class="user-sub-bar" style="width:${roiPct}%;background:${barColor}"></div></div>
                                    </div>
                                    <div style="text-align:right;flex-shrink:0;">
                                        <div class="user-roi-badge ${roiClass}">${roiLabel}</div>
                                        ${cancelBtn}
                                    </div>
                                </div>`;
                            }).join('');
                        }

                        const matchWrapEl = document.getElementById('user-renta-match-wrap');
                        if (recData.success && recData.owned_scores && recData.owned_scores.length && matchWrapEl) {
                            const maxScore = Math.max(...recData.owned_scores.map(p => p.match_score), 1);
                            document.getElementById('user-renta-match').innerHTML = recData.owned_scores.map(p => `
                                <div class="user-match-row">
                                    <img src="${TMDB}${p.logo_path}" alt="${p.provider_name}" class="user-match-logo" onerror="this.style.display='none'">
                                    <span class="user-match-name">${p.provider_name}</span>
                                    <div class="user-match-bar-wrap"><div class="user-match-bar" style="width:${(p.match_score / maxScore * 100).toFixed(0)}%"></div></div>
                                    <span class="user-match-score">${p.match_score}%</span>
                                </div>`).join('');
                            matchWrapEl.style.display = 'block';
                        }
                    } catch(e) {}
                })();
                </script>
            </section>

            <!-- ═══════════════ AGENDA & SESSIONS ═══════════════ -->
            <div class="user-dashboard-split">
                <div class="dash-column dash-agenda">
                    <div class="dash-card-header"><h4>PLANIFICATION</h4></div>
                    <div class="agenda-list">
                        <?php if (empty($userEvents)): ?>
                            <div class="empty-placeholder"><p>Aucun événement planifié.</p></div>
                        <?php else: ?>
                            <?php foreach ($userEvents as $event):
                                renderEventCard($event, $_SESSION['user_id']);
                            endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="dash-column dash-sessions">
                    <div class="dash-card-header" style="display:flex;justify-content:space-between;align-items:center;">
                        <h4>SESSIONS ACTIVES</h4>
                        <a href="rooms.php" class="view-all" style="font-size:0.7rem;color:var(--pastel-blue);font-weight:bold;">Ouvrir</a>
                    </div>
                    <div class="rooms-grid-mini" style="margin-top:15px;">
                        <?php if (empty($myActiveRooms)): ?>
                            <div class="empty-placeholder"><p>Aucune session active.</p></div>
                        <?php else: ?>
                            <?php foreach ($myActiveRooms as $room):
                                $host = $allUsers[$room['host_id']] ?? null;
                                $room['host_name'] = $host ? $host['username'] : 'Inconnu';
                                renderRoomCard($room);
                            endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- ═══════════════ ARCHIVES ═══════════════ -->
            <section class="user-collection-preview" style="margin-top:40px;">
                <div class="dash-card-header"><h4>ARCHIVES OPÉRATEUR</h4></div>
                <div class="dash-links" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:15px;margin-top:15px;">
                    <a href="collection.php?filter=liked"    class="dash-link-item" style="background:rgba(255,77,77,0.05);border-color:rgba(255,77,77,0.2);">❤️ Coup de coeur</a>
                    <a href="collection.php?filter=seen"     class="dash-link-item" style="background:rgba(167,199,231,0.05);border-color:rgba(167,199,231,0.2);">✓ Historique</a>
                    <a href="collection.php?filter=wishlist" class="dash-link-item" style="background:rgba(232,192,122,0.05);border-color:rgba(232,192,122,0.2);">🔖 Watchlist</a>
                </div>
            </section>

            <!-- ═══════════════ CRÉATIONS ═══════════════ -->
            <section style="margin-top:30px;">
                <div class="dash-card-header" style="display:flex;justify-content:space-between;align-items:center;">
                    <h4>CRÉATIONS</h4>
                    <a href="content-edit.php" class="btn-base" style="font-size:0.65rem;padding:5px 12px;">+ Nouveau</a>
                </div>
                <?php
                $myContents = db_fetch_all(
                    "SELECT uc.slug, uc.type, uc.title,
                            GREATEST(uc.updated_at, COALESCE((SELECT MAX(cc.created_at) FROM content_comments cc WHERE cc.content_id = uc.id), uc.updated_at)) AS last_activity,
                            (SELECT COUNT(*) FROM content_comments cc WHERE cc.content_id = uc.id) AS comment_count
                     FROM user_content uc
                     WHERE uc.user_id = ?
                     ORDER BY last_activity DESC LIMIT 5",
                    [$userId]
                ) ?: [];
                ?>
                <?php if (empty($myContents)): ?>
                    <p style="font-size:0.78rem;color:var(--text-dim);margin-top:12px;">Vous n'avez pas encore de listes ou de critiques.</p>
                <?php else: ?>
                <div style="display:flex;flex-direction:column;gap:8px;margin-top:12px;">
                    <?php foreach ($myContents as $c): renderContentRow($c); endforeach; ?>
                    <a href="archives.php" style="font-size:0.68rem;color:var(--text-dim);text-align:center;margin-top:4px;">Voir toutes les archives →</a>
                </div>
                <?php endif; ?>
            </section>

            <?php if ($isOwnProfile): ?>

                <!-- ═══════════════ DÉCONNEXION ═══════════════ -->
                <div class="user-logout-section" style="margin-top:60px;text-align:center;display:flex;flex-direction:column;gap:15px;align-items:center;">
                    <?php if (has_role('admin')): ?>
                        <a href="admin.php" class="btn-base" style="background:var(--pastel-blue);color:#050505;border-color:var(--pastel-blue);width:100%;max-width:300px;font-weight:900;">🛡️ Command Center</a>
                    <?php endif; ?>
                    <?php if (has_role('moderator')): ?>
                        <a href="moderation.php" class="btn-base" style="background:rgba(110,231,183,0.15);color:#6ee7b7;border-color:rgba(110,231,183,0.4);width:100%;max-width:300px;font-weight:900;">🛡 Poste de Modération</a>
                    <?php endif; ?>
                    <a href="api/api_logout.php" class="btn-base btn-logout-final" style="width:100%;max-width:300px;">Déconnexion</a>
                    <button onclick="confirmAnonymization()" style="background:none;border:none;color:var(--text-dim);font-size:0.7rem;text-decoration:underline;cursor:pointer;opacity:0.6;transition:opacity 0.2s;margin-top:10px;" onmouseover="this.style.opacity='1'" onmouseout="this.style.opacity='0.6'">
                        Supprimer mon compte
                    </button>
                </div>

                <!-- ═══════════════ MODALE ÉDITION PROFIL (onglets) ═══════════════ -->
                <div id="editProfileModal" class="modal" onclick="if(window.innerWidth<=600&&event.target===this)closeModal('editProfileModal')">
                    <div class="modal-content" style="width:min(860px,90vw);max-width:none;">
                        <button class="modal-close" onclick="closeModal('editProfileModal')">&times;</button>

                        <!-- Onglets -->
                        <div class="profile-tabs">
                            <button id="tab-btn-profil"          class="profile-tab active" onclick="switchProfileTab('profil')">PROFIL</button>
                            <button id="tab-btn-plateformes"     class="profile-tab"        onclick="switchProfileTab('plateformes')">PLATEFORMES</button>
                            <button id="tab-btn-confidentialite" class="profile-tab"        onclick="switchProfileTab('confidentialite')">CONFIDENTIALITÉ</button>
                            <button id="tab-btn-import"          class="profile-tab"        onclick="switchProfileTab('import')">IMPORT</button>
                        </div>

                        <!-- ── Onglet Profil ── -->
                        <div id="tab-profil">

                            <!-- Avatar -->
                            <input type="file" id="avatarInput" accept="image/png,image/jpeg,image/webp" style="display:none;">
                            <img id="avatarPreview" src="<?= h($user['avatar']) ?>" class="edit-avatar-preview" onclick="document.getElementById('avatarInput').click()" style="cursor:pointer;">
                            <p style="font-size:0.6rem;color:var(--text-dim);margin-top:-10px;margin-bottom:20px;text-align:center;">Cliquez pour téléverser</p>

                            <div class="form-group" style="text-align:left;margin-bottom:15px;">
                                <label style="font-size:0.7rem;text-transform:uppercase;color:var(--text-dim);font-weight:bold;">Pseudo</label>
                                <input type="text" id="editUsernameInput" value="<?= h($user['username']) ?>"
                                       style="width:100%;padding:12px;background:rgba(255,255,255,0.05);border:1px solid var(--border);border-radius:8px;color:white;margin-top:5px;outline:none;">
                            </div>

                            <div class="form-group" style="text-align:left;margin-bottom:20px;">
                                <label style="font-size:0.7rem;text-transform:uppercase;color:var(--text-dim);font-weight:bold;">Description Narrative</label>
                                <textarea id="editDescriptionInput"
                                          style="width:100%;height:100px;padding:12px;background:rgba(255,255,255,0.05);border:1px solid var(--border);border-radius:8px;color:white;margin-top:5px;outline:none;resize:vertical;line-height:1.4;"><?= h($user['description'] ?? '') ?></textarea>
                            </div>

                            <div style="display:flex;gap:10px;">
                                <button onclick="saveProfile()" class="btn-base active" style="flex:1;">Enregistrer</button>
                                <button onclick="closeModal('editProfileModal')" class="btn-base" style="flex:1;background:transparent;border:1px solid var(--border);">Annuler</button>
                            </div>
                        </div>

                        <!-- ── Onglet Plateformes ── -->
                        <div id="tab-plateformes" style="display:none;">
                            <p style="font-size:0.7rem;color:var(--text-dim);margin-bottom:10px;">Sélectionnez les plateformes auxquelles vous êtes abonné.</p>
                            <input type="text" id="platformSearch" placeholder="Rechercher une plateforme..."
                                   oninput="filterPlatforms(this.value)"
                                   style="width:100%;box-sizing:border-box;padding:8px 12px;border-radius:8px;border:1px solid var(--border);background:rgba(255,255,255,0.05);color:var(--text-main);font-size:0.75rem;margin-bottom:10px;">
                            <div id="platformsGrid" class="platforms-grid">
                                <p class="platforms-loading">Chargement...</p>
                            </div>
                            <div style="display:flex;gap:10px;margin-top:20px;">
                                <button onclick="savePlatforms()" class="btn-base active" style="flex:1;">Enregistrer</button>
                                <button onclick="closeModal('editProfileModal')" class="btn-base" style="flex:1;background:transparent;border:1px solid var(--border);">Annuler</button>
                            </div>
                        </div>

                        <!-- ── Onglet Confidentialité ── -->
                        <div id="tab-confidentialite" style="display:none;">
                            <p style="font-size:0.7rem;color:var(--text-dim);margin-bottom:20px;">Contrôlez ce que les autres opérateurs peuvent voir sur votre profil public.</p>

                            <div class="privacy-toggle-row">
                                <div>
                                    <div class="privacy-toggle-label">Séquence ADN publique</div>
                                    <div class="privacy-toggle-desc">Votre radar cinéphile est visible sur la page ADN</div>
                                </div>
                                <label class="toggle-switch">
                                    <input type="checkbox" id="toggle-adn-public" <?= $adnPublic ? 'checked' : '' ?>>
                                    <span class="toggle-slider"></span>
                                </label>
                            </div>

                            <div class="privacy-toggle-row">
                                <div>
                                    <div class="privacy-toggle-label">Archives publiques</div>
                                    <div class="privacy-toggle-desc">Vos collections (coups de coeur, historique) sont visibles</div>
                                </div>
                                <label class="toggle-switch">
                                    <input type="checkbox" id="toggle-collection-public" <?= $collectionPublic ? 'checked' : '' ?>>
                                    <span class="toggle-slider"></span>
                                </label>
                            </div>

                            <div class="privacy-toggle-row">
                                <div>
                                    <div class="privacy-toggle-label">watchlist publique</div>
                                    <div class="privacy-toggle-desc">Votre liste de films à voir est visible sur votre profil</div>
                                </div>
                                <label class="toggle-switch">
                                    <input type="checkbox" id="toggle-wishlist-public" <?= $wishlistPublic ? 'checked' : '' ?>>
                                    <span class="toggle-slider"></span>
                                </label>
                            </div>

                            <div class="privacy-toggle-row">
                                <div>
                                    <div class="privacy-toggle-label">Créations publiques</div>
                                    <div class="privacy-toggle-desc">Vos listes et critiques sont visibles sur votre profil</div>
                                </div>
                                <label class="toggle-switch">
                                    <input type="checkbox" id="toggle-content-public" <?= $contentPublic ? 'checked' : '' ?>>
                                    <span class="toggle-slider"></span>
                                </label>
                            </div>

                            <div style="display:flex;gap:10px;margin-top:24px;">
                                <button onclick="savePrivacy()" class="btn-base active" style="flex:1;">Enregistrer</button>
                                <button onclick="closeModal('editProfileModal')" class="btn-base" style="flex:1;background:transparent;border:1px solid var(--border);">Annuler</button>
                            </div>
                        </div>

                        <!-- ── Onglet Import ── -->
                        <div id="tab-import" style="display:none;">
                            <p style="font-size:0.7rem;color:var(--text-dim);margin-bottom:16px;line-height:1.6;">
                                Importez votre historique depuis <strong>SensCritique</strong>, <strong>TMDB</strong>, <strong>Letterboxd</strong> ou <strong>IMDb</strong>.<br>
                                Les films importés sont marqués comme <em>vus</em> mais ne génèrent <strong>pas d'ADN</strong>, seules vos notationsÒ construisent votre séquence.
                            </p>

                            <div style="font-size:0.6rem;color:var(--text-dim);background:var(--card-bg);border:1px solid var(--border);border-radius:10px;padding:12px 14px;margin-bottom:16px;line-height:1.8;">
                                <strong style="color:var(--text-main);display:block;margin-bottom:6px;">Comment exporter :</strong>
                                🎬 <strong>SensCritique</strong> → <a href="https://sensboxd.phileas.tv/" target="_blank" rel="noopener" style="color:var(--pastel-blue);">sensboxd.phileas.tv</a> → Export CSV<br>
                                📋 <strong>TMDB</strong> → Compte → Exporter les notations (CSV)<br>
                                🎞️ <strong>Letterboxd</strong> → Settings → Import &amp; Export → Export Your Data<br>
                                ⭐ <strong>IMDb</strong> → Your Ratings → ··· → Export
                            </div>

                            <label id="import-drop-zone" style="display:flex;flex-direction:column;align-items:center;justify-content:center;gap:8px;border:2px dashed var(--border);border-radius:12px;padding:28px 16px;cursor:pointer;transition:border-color .2s;text-align:center;" ondragover="event.preventDefault();this.style.borderColor='var(--pastel-blue)'" ondragleave="this.style.borderColor='var(--border)'" ondrop="handleImportDrop(event)">
                                <span style="font-size:1.6rem;">📂</span>
                                <span style="font-size:0.7rem;font-weight:700;color:var(--text-main);">Déposer le fichier CSV ici</span>
                                <span style="font-size:0.6rem;color:var(--text-dim);">ou cliquer pour sélectionner</span>
                                <input type="file" id="import-csv-input" accept=".csv" style="display:none;" onchange="startCsvImport(this.files[0])">
                            </label>

                            <div id="import-progress" style="display:none;margin-top:16px;">
                                <div style="display:flex;align-items:center;gap:10px;margin-bottom:10px;">
                                    <div class="spinner" style="width:14px;height:14px;border:2px solid var(--border);border-top-color:var(--pastel-blue);border-radius:50%;animation:spin .8s linear infinite;flex-shrink:0;"></div>
                                    <span id="import-status" style="font-size:0.7rem;color:var(--text-dim);">Analyse en cours…</span>
                                </div>
                            </div>

                            <div id="import-result" style="display:none;margin-top:16px;padding:12px 14px;border-radius:10px;border:1px solid var(--border);font-size:0.7rem;line-height:1.8;background:var(--card-bg);">
                            </div>
                        </div>

                    </div>
                </div>

            <?php endif; ?>

        </main>
    </div>

    <div class="flex-spacer"></div>
    <?php renderFooter(); ?>

    <!-- ═══════════════ MODAL AMIS ═══════════════ -->
    <?php if ($isOwnProfile): ?>
    <div id="friendsModal" class="friends-modal-overlay hidden" onclick="if(event.target===this)this.classList.add('hidden')">
        <div class="friends-modal">
            <div class="friends-modal-header">
                <span class="friends-modal-title">Mes liens (<?= count($friendsList) ?>)</span>
                <button onclick="document.getElementById('friendsModal').classList.add('hidden')"
                        style="background:none;border:none;color:var(--text-dim);font-size:1.2rem;cursor:pointer;padding:0;line-height:1;">&times;</button>
            </div>
            <?php if (empty($friendsList)): ?>
                <p style="font-size:0.78rem;color:var(--text-dim);text-align:center;padding:20px 0;">Aucun lien tissé pour l'instant.</p>
            <?php else: ?>
                <?php foreach ($friendsList as $f): ?>
                <div class="friend-row">
                    <img src="<?= h($f['avatar'] ?: 'assets/default-avatar.png') ?>"
                         onerror="this.src='assets/default-avatar.png'"
                         class="friend-row-avatar">
                    <a href="adn.php?id=<?= urlencode($f['id']) ?>" class="friend-row-name"><?= h($f['username']) ?></a>
                    <div class="friend-row-actions">
                        <a href="chat.php?with=<?= urlencode($f['id']) ?>"
                           class="btn-base" style="font-size:0.58rem;padding:5px 9px;text-decoration:none;">Message</a>
                        <button onclick="removeFriend('<?= addslashes($f['id']) ?>', this)"
                                class="btn-base" style="font-size:0.58rem;padding:5px 9px;color:var(--text-dim);">Retirer</button>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
    <script>
    async function removeFriend(targetId, btn) {
        if (!confirm('Retirer ce lien ?')) return;
        btn.disabled = true;
        const fd = new FormData();
        fd.append('action', 'remove');
        fd.append('target_id', targetId);
        const res  = await fetch('api/api_friends.php', { method: 'POST', body: fd });
        const data = await res.json();
        if (data.ok) btn.closest('.friend-row').remove();
        else btn.disabled = false;
    }
    </script>
    <?php endif; ?>

    <script>
    let _userPlatforms = <?= json_encode(
        is_array(json_decode($user['user_platforms'] ?? 'null', true))
            ? json_decode($user['user_platforms'], true)
            : []
    ) ?>;
    </script>
    <script src="/assets/js/user.js?v=<?= filemtime('assets/js/user.js') ?>" defer></script>
    <script>
    document.getElementById('import-drop-zone')?.addEventListener('click', () => {
        document.getElementById('import-csv-input').click();
    });

    function handleImportDrop(e) {
        e.preventDefault();
        document.getElementById('import-drop-zone').style.borderColor = 'var(--border)';
        const file = e.dataTransfer.files[0];
        if (file) startCsvImport(file);
    }

    function startCsvImport(file) {
        if (!file || !file.name.endsWith('.csv')) {
            alert('Merci de fournir un fichier .csv');
            return;
        }
        document.getElementById('import-progress').style.display = 'block';
        document.getElementById('import-result').style.display   = 'none';
        document.getElementById('import-status').textContent     = 'Analyse en cours… (peut prendre 1-2 min selon la taille)';

        const fd = new FormData();
        fd.append('csv', file);

        fetch('api/api_import_csv.php', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(data => {
                document.getElementById('import-progress').style.display = 'none';
                const el = document.getElementById('import-result');
                el.style.display = 'block';
                if (!data.success) {
                    el.innerHTML = `<span style="color:#FA6B6B;">❌ ${data.message}</span>`;
                    return;
                }
                const fmt = { imdb: 'IMDb', letterboxd: 'Letterboxd', tmdb: 'TMDB', sensboxd: 'SensCritique' }[data.format] || data.format;
                el.innerHTML = `
                    <div style="color:var(--text-main);font-weight:700;margin-bottom:8px;">✅ Import ${fmt} terminé</div>
                    <div>📥 <strong>${data.imported}</strong> films importés</div>
                    ${data.skipped  ? `<div style="color:var(--text-dim);">⏭ ${data.skipped} ignorés (non-films)</div>` : ''}
                    ${data.not_found ? `<div style="color:var(--text-dim);">❓ ${data.not_found} introuvables sur TMDB</div>` : ''}
                    <div style="margin-top:12px;padding:10px;background:rgba(250,107,107,0.08);border-left:3px solid #FA6B6B;border-radius:4px;font-size:0.65rem;line-height:1.6;color:var(--text-dim);">
                        Ces films sont marqués comme vus mais <strong style="color:#FA6B6B;">ne génèrent pas d'ADN</strong>.<br>
                        Notez vos films préférés via leur fiche pour construire votre séquence.
                    </div>`;
            })
            .catch(() => {
                document.getElementById('import-progress').style.display = 'none';
                document.getElementById('import-result').style.display   = 'block';
                document.getElementById('import-result').innerHTML = '<span style="color:#FA6B6B;">❌ Erreur réseau ou délai dépassé.</span>';
            });
    }
    </script>
<?php if ($signalPerdu): ?>
<script>
(function () {
    const wrap = document.getElementById('adnSvgWrap_userAdnChart');
    if (!wrap) return;

    const inner = wrap.querySelector('div');
    if (!inner) return;

    // Fond (première img) reste visible — on anime tout le reste
    const imgs = Array.from(inner.querySelectorAll('img')).slice(1);

    // Masquer toutes les barres/reflets immédiatement
    imgs.forEach(img => { img.style.opacity = '0'; });

    // Overlay de bruit statique
    inner.classList.add('adn-signal-active');

    // Révéler chaque image avec un décalage
    const DELAY_STEP = 90; // ms par image
    imgs.forEach((img, i) => {
        setTimeout(() => {
            img.style.transition = 'opacity 0.25s ease, filter 0.25s ease';
            img.style.filter     = 'blur(3px) brightness(1.6)';
            img.style.opacity    = '1';
            setTimeout(() => {
                img.style.filter = '';
            }, 180);
        }, i * DELAY_STEP);
    });

    // Supprimer le bruit après que toutes les barres soient apparues
    const totalMs = imgs.length * DELAY_STEP + 500;
    setTimeout(() => inner.classList.remove('adn-signal-active'), totalMs);

    // Faire apparaître le banner
    const banner = document.getElementById('signal-perdu-banner');
    if (banner) {
        setTimeout(() => banner.classList.add('signal-perdu-banner--visible'), 300);
    }
})();
</script>
<?php endif; ?>
</body>
</html>