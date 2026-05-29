<?php
require_once 'functions/utils.php';
require_once 'functions/auth.php';
require_once 'functions/badges_logic.php';
require_once 'functions/friends_logic.php';
require_once 'components/header.php';
require_once 'components/nav.php';
require_once 'components/footer.php';
require_once 'components/adn-chart.php';
require_once 'components/dna-bars.php';
require_once 'components/content-row.php';
require_once 'config/settings.php';

$targetId = $_GET['id'] ?? null;
if (!$targetId) { header('Location: 404.php'); exit; }

$targetUser = get_user_by_id($targetId);
if (!$targetUser || !empty($targetUser['is_anonymized'])) { header('Location: 404.php'); exit; }

$currentUserId = $_SESSION['user_id'] ?? null;
$currentUser   = $currentUserId ? get_user_by_id($currentUserId) : null;
$isOwnProfile  = ($currentUserId === $targetId);


$adnData             = get_user_adn($targetId) ?: ['count' => 0, 'scores' => []];
$seriesAdnData       = get_user_series_adn($targetId);
$badgeLibrary        = get_badge_library();
$accountCreationDate = isset($targetUser['created_at']) ? date('d/m/Y', strtotime($targetUser['created_at'])) : 'Date inconnue';

$defaultDesc    = "Spécimen trouvé dans une salle sombre de cinéma, l'identification n'a pas encore été faite, mais le spécimen semble se plaire sur un canapé devant un bon film.";
$userDescription = !empty($targetUser['description']) ? $targetUser['description'] : $defaultDesc;

$userBadgesRaw = db_fetch_all(
    'SELECT badge_id FROM user_badges WHERE user_id = ? ORDER BY earned_at ASC',
    [$targetId]
) ?: [];
$userBadges = array_column($userBadgesRaw, 'badge_id');

// ── Lecture des préférences de confidentialité ───────────────────────────────
$privacySettings  = json_decode($targetUser['privacy_settings'] ?? '{}', true) ?: [];
$adnPublic        = $privacySettings['adn_public']        ?? true;
$collectionPublic = $privacySettings['collection_public'] ?? true;
$wishlistPublic   = $privacySettings['wishlist_public']   ?? true;
$contentPublic    = $privacySettings['content_public']    ?? true;

$backdropImage = $targetUser['backdrop_poster'] ?? null;

$profileStats = db_fetch_one(
    "SELECT
        (SELECT COUNT(*) FROM friendships WHERE (requester_id=? OR addressee_id=?) AND status='accepted') AS friend_count,
        (SELECT COUNT(*) FROM ratings        WHERE user_id=?) AS rating_count,
        (SELECT COUNT(*) FROM movie_comments WHERE user_id=?) AS comment_count,
        (SELECT COUNT(*) FROM user_content   WHERE user_id=? AND is_public=1) AS archive_count",
    [$targetId, $targetId, $targetId, $targetId, $targetId]
) ?: ['friend_count' => 0, 'rating_count' => 0, 'comment_count' => 0, 'archive_count' => 0];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title><?= h($targetUser['username']) ?> — Séquence ADN</title>
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
<body class="page-adn">
    <?php if ($backdropImage): ?>
    <div class="user-backdrop-blur" style="background-image:url('<?= h($backdropImage) ?>');"></div>
    <?php endif; ?>
    <div class="container">
        <?php renderHeader($currentUser); ?>
        <?php if ($currentUser) renderNav('discover'); ?>

        <main class="user-profile-layout">

            <!-- ═══════════════ HERO ═══════════════ -->
            <section class="hero-split-container">
                <div class="hero-left-pane">
                    <div class="user-avatar-large">
                        <img src="<?= h($targetUser['avatar']) ?>" onerror="this.src='assets/default-avatar.png';">
                    </div>
                    <div>
                        <h1 class="user-name" style="margin:0 0 5px 0; font-size:1.4rem;"><?= h($targetUser['username']) ?></h1>
                        <?= render_role_badge($targetUser['role'] ?? 'user') ?>
                    </div>
                    <span style="color:var(--text-dim); font-size:0.7em;">Membre depuis le <?= $accountCreationDate ?></span>

                    <div class="user-stats-bar">
                        <span class="user-stat-item" data-label="Liens">
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
                        <a href="user.php" class="btn-base" style="width:100%; padding:8px 15px; font-size:0.7rem; margin-top:5px; text-align:center;">
                            ⚙️ Mon profil
                        </a>
                    <?php endif; ?>

                    <a href="javascript:void(0)" onclick="copyAdnLink('<?= h($targetId) ?>')"
                       style="font-size:0.7rem; color:var(--text-dim); text-decoration:underline; margin-top:5px;">
                        Partager cette séquence
                    </a>

                    <?php if ($currentUser && !$isOwnProfile):
                        $friendStatus = get_friendship_status($currentUserId, $targetId);
                    ?>
                    <div id="friend-btn-wrap" style="margin-top:8px;display:flex;gap:8px;flex-wrap:nowrap;align-items:center;">
                        <?php if ($friendStatus === 'none'): ?>
                            <button class="btn-base active friend-btn" onclick="friendAction('send')" style="font-size:0.7rem;padding:7px 14px;">
                                Tisser un lien
                            </button>
                        <?php elseif ($friendStatus === 'pending_sent'): ?>
                            <button class="btn-base friend-btn" disabled style="font-size:0.7rem;padding:7px 14px;opacity:0.5;cursor:default;">
                                Demande envoyée
                            </button>
                            <button class="btn-base friend-btn" onclick="friendAction('decline')" style="font-size:0.7rem;padding:7px 14px;color:var(--danger);border-color:var(--danger);">
                                Annuler
                            </button>
                        <?php elseif ($friendStatus === 'pending_received'): ?>
                            <button class="btn-base active friend-btn" onclick="friendAction('accept')" style="font-size:0.7rem;padding:7px 14px;">
                                ✓ Accepter
                            </button>
                            <button class="btn-base friend-btn" onclick="friendAction('decline')" style="font-size:0.7rem;padding:7px 14px;color:var(--danger);border-color:var(--danger);">
                                Refuser
                            </button>
                        <?php elseif ($friendStatus === 'accepted'): ?>
                            <a href="chat.php?with=<?= h($targetId) ?>" class="btn-base active friend-btn" style="font-size:0.7rem;padding:7px 14px;text-decoration:none;">
                                💬 Message
                            </a>
                            <button class="btn-base friend-btn" onclick="friendAction('remove')" style="font-size:0.7rem;padding:7px 14px;color:var(--text-dim);">
                                Retirer
                            </button>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>

                </div>

                <div class="hero-right-pane">
                    <h3 style="font-size:0.7rem; color:var(--text-dim); text-transform:uppercase; letter-spacing:2px; margin-bottom:15px;">Dossier d'identification</h3>
                    <p style="font-size:0.9rem; line-height:1.6; color:var(--text-muted); margin:0;">
                        <?= nl2br(h($userDescription)) ?>
                    </p>
                </div>
            </section>

            <!-- ═══════════════ COMPARATEUR ADN ═══════════════ -->
            <?php if ($currentUser && !$isOwnProfile && ($friendStatus ?? null) === 'accepted'):
                $dnaCriteria  = ['complexite','previsibilite','intensite','malaise',
                                 'stylisation','dynamique','depaysement','coherence'];
                $myDnaRows    = db_fetch_all('SELECT criterio, avg_score FROM user_dna WHERE user_id = ?', [$currentUserId]);
                $theirDnaRows = db_fetch_all('SELECT criterio, avg_score FROM user_dna WHERE user_id = ?', [$targetId]);

                if (!empty($myDnaRows) && !empty($theirDnaRows)):
                    $myDna    = array_column($myDnaRows,    'avg_score', 'criterio');
                    $theirDna = array_column($theirDnaRows, 'avg_score', 'criterio');
                    $dot = $normMe = $normThem = 0;
                    foreach ($dnaCriteria as $c) {
                        $a = (float)($myDna[$c] ?? 0);
                        $b = (float)($theirDna[$c] ?? 0);
                        $dot += $a * $b; $normMe += $a * $a; $normThem += $b * $b;
                    }
                    $comparePct = ($normMe > 0 && $normThem > 0)
                        ? round(($dot / (sqrt($normMe) * sqrt($normThem)) + 1) / 2 * 100, 1)
                        : 0;
            ?>
            <section class="adn-comparator-section">
                <div class="adn-comparator-inner">
                    <div class="adn-comparator-header">
                        <span class="adn-comparator-pct"><?= $comparePct ?>%</span>
                        <span class="adn-comparator-label">de résonance génomique</span>
                    </div>
                    <div class="adn-comparator-bars">
                        <?= renderDnaBarsPair(
                            $currentUserId,
                            $targetId,
                            $comparePct,
                            'Votre ADN',
                            'ADN ' . h($targetUser['username'])
                        ) ?>
                    </div>
                </div>
            </section>
            <?php endif; endif; ?>

            <!-- ═══════════════ ADN CINÉMA + SÉRIE ═══════════════ -->
            <?php if ($adnPublic || $isOwnProfile):
                // On passe toujours les données série au chart.
                // Sans aucun vote → stats vides → tous les critères retournent 'null_serie'.
                $hasSeriesAdn = ($seriesAdnData['count'] >= 1);
                $seriesScores = $seriesAdnData['scores'] ?? array_fill(0, 8, 0);
                $seriesStats  = $seriesAdnData['stats']  ?? [];
            ?>
            <section class="user-adn">
                <div class="section-title" style="border-bottom:1px solid var(--border);padding-bottom:10px;">
                    <h3 style="font-size:0.8rem;color:var(--text-dim);letter-spacing:1px;">ADN</h3>
                </div>

                <div class="adn-flex-container">
                    <div class="adn-radar-wrapper">
                        <?php renderAdnChart(
                            $adnData['scores'] ?? [],
                            $seriesScores,
                            0,
                            'userAdnChart',
                            false,
                            0,
                            $adnData['stats'] ?? null,
                            $seriesStats
                        ); ?>
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
            </section>
            <?php else: ?>
            <section class="user-adn">
                <div style="padding:40px; text-align:center; color:var(--text-dim); font-size:0.85rem; border:1px solid var(--border); border-radius:12px;">
                    🔒 Cet opérateur a verrouillé sa séquence ADN.
                </div>
            </section>
            <?php endif; ?>

            <!-- ═══════════════ ARCHIVES (COLLECTION) ═══════════════ -->
            <?php if ($collectionPublic || $isOwnProfile): ?>
            <section class="user-collection-preview" style="margin-top:40px;">
                <div class="dash-card-header"><h4>ARCHIVES OPÉRATEUR</h4></div>
                <div class="dash-links" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:15px;margin-top:15px;">
                    <a href="collection.php?id=<?= h($targetId) ?>&filter=liked"    class="dash-link-item" style="background:rgba(255,77,77,0.05);border-color:rgba(255,77,77,0.2);">❤️ Coups de coeur</a>
                    <a href="collection.php?id=<?= h($targetId) ?>&filter=seen"     class="dash-link-item" style="background:rgba(167,199,231,0.05);border-color:rgba(167,199,231,0.2);">✓ Historique</a>
                    <?php if ($isOwnProfile || $wishlistPublic): ?>
                    <a href="collection.php?id=<?= h($targetId) ?>&filter=wishlist" class="dash-link-item" style="background:rgba(232,192,122,0.05);border-color:rgba(232,192,122,0.2);">🔖 Wishlist</a>
                    <?php endif; ?>
                </div>
            </section>
            <?php else: ?>
            <section class="user-collection-preview" style="margin-top:40px;">
                <div style="padding:30px; text-align:center; color:var(--text-dim); font-size:0.85rem; border:1px solid var(--border); border-radius:12px;">
                    🔒 Les archives de cet opérateur sont classifiées.
                </div>
            </section>
            <?php endif; ?>

            <!-- ═══════════════ CRÉATIONS ═══════════════ -->
            <?php if ($contentPublic || $isOwnProfile):
                $targetContents = db_fetch_all(
                    "SELECT uc.slug, uc.type, uc.title,
                            GREATEST(uc.updated_at, COALESCE((SELECT MAX(cc.created_at) FROM content_comments cc WHERE cc.content_id = uc.id), uc.updated_at)) AS last_activity,
                            (SELECT COUNT(*) FROM content_comments cc WHERE cc.content_id = uc.id) AS comment_count
                     FROM user_content uc
                     WHERE uc.user_id = ? AND uc.is_public = 1
                     ORDER BY last_activity DESC LIMIT 5",
                    [$targetId]
                ) ?: [];
                if (!empty($targetContents)):
            ?>
            <section style="margin-top:30px;">
                <div class="dash-card-header" style="display:flex;justify-content:space-between;align-items:center;">
                    <h4>CRÉATIONS</h4>
                    <?php if ($isOwnProfile): ?>
                    <a href="content-edit.php" class="btn-base" style="font-size:0.65rem;padding:5px 12px;">+ Nouveau</a>
                    <?php endif; ?>
                </div>
                <div style="display:flex;flex-direction:column;gap:8px;margin-top:12px;">
                    <?php foreach ($targetContents as $c): renderContentRow($c); endforeach; ?>
                </div>
            </section>
            <?php endif; endif; ?>


        </main>
    </div>

    <div class="flex-spacer"></div>
    <?php renderFooter(); ?>

<?php if ($currentUser && !$isOwnProfile): ?>
<script>const ADN_TARGET_ID = '<?= addslashes($targetId) ?>';</script>
<script src="assets/js/adn.js"></script>
<?php endif; ?>
</body>
</html>