<?php
/**
 * ARCHIVES.PHP — Découverte des listes et critiques publiques
 */
require_once 'functions/utils.php';
require_once 'functions/auth.php';
require_once 'components/header.php';
require_once 'components/nav.php';
require_once 'components/footer.php';
require_once 'components/avatar.php';
require_once 'components/content-row.php';

$currentUserId = $_SESSION['user_id'] ?? null;
$currentUser   = $currentUserId ? get_user_by_id($currentUserId) : null;

$contents = db_fetch_all(
    "SELECT uc.id, uc.slug, uc.type, uc.title, uc.body, uc.created_at, uc.updated_at,
            u.id AS user_id, u.username, u.avatar, u.role,
            (SELECT COUNT(*) FROM user_content_items uci WHERE uci.content_id = uc.id) AS item_count,
            (SELECT COUNT(*) FROM content_comments cc WHERE cc.content_id = uc.id) AS comment_count,
            GREATEST(
                uc.updated_at,
                COALESCE((SELECT MAX(cc2.created_at) FROM content_comments cc2 WHERE cc2.content_id = uc.id), uc.updated_at)
            ) AS last_activity
     FROM user_content uc
     JOIN users u ON u.id = uc.user_id
     WHERE uc.is_public = 1
     ORDER BY
         (uc.type = 'guide') DESC,
         last_activity DESC
     LIMIT 60",
    []
) ?: [];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Listes &amp; Critiques Cinéma — Archives · ADN Movie</title>
    <link rel="canonical" href="https://adnmovie.fr/archives.php">
    <link rel="icon" href="/assets/Icons/Logo2.ico" type="image/x-icon">
    <link rel="apple-touch-icon" href="/assets/Icons/Logo3.png">
    <meta name="description" content="Listes, critiques et guides cinéma rédigés par la communauté ADN Movie. Découvrez les meilleures sélections et analyses de films.">
    <meta property="og:site_name" content="ADN Movie">
    <meta property="og:type" content="website">
    <meta property="og:title" content="Archives · Listes &amp; Critiques Cinéma — ADN Movie">
    <meta property="og:description" content="Listes, critiques et guides cinéma rédigés par la communauté ADN Movie.">
    <meta property="og:image" content="https://adnmovie.fr/assets/Icons/Named_logo1.png">
    <meta property="og:url" content="https://adnmovie.fr/archives.php">
    <meta name="twitter:card" content="summary">
    <meta name="twitter:title" content="Archives · ADN Movie">
    <meta name="twitter:description" content="Listes, critiques et guides cinéma de la communauté ADN Movie.">
    <meta name="twitter:image" content="https://adnmovie.fr/assets/Icons/Named_logo1.png">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="assets/style.css?v=<?= filemtime('assets/style.css') ?>">
    <?php if (function_exists('renderScripts')) renderScripts(); ?>
    <meta name="theme-color" content="#050505">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
</head>
<body class="page-archives">
<div class="container">
    <?php renderHeader($currentUser); ?>
    <?php renderNav('archives'); ?>

    <main>
        <section class="rooms-header">
            <h1>ARCHIVES</h1>
        </section>

        <?php if ($currentUser): ?>
        <div style="margin-bottom:20px;">
            <a href="content-edit.php" class="btn-base active">+ Créer</a>
        </div>
        <?php endif; ?>

        <?php if (empty($contents)): ?>
        <div style="text-align:center;padding:60px 20px;color:var(--text-dim);font-size:0.85rem;">
            Aucun contenu pour le moment. Soyez le premier à publier.
        </div>
        <?php else: ?>
        <div style="display:flex;flex-direction:column;gap:8px;">
            <?php foreach ($contents as $c): ?>
            <div style="display:flex;align-items:center;gap:10px;">
                <div style="flex:1;min-width:0;"><?php renderContentRow($c); ?></div>
                <div style="flex-shrink:0;display:flex;align-items:center;gap:8px;">
                    <?php $mockUser = ['username' => $c['username'], 'avatar' => $c['avatar']]; ?>
                    <?php renderAvatar($mockUser, $c['user_id'], 22); ?>
                    <span style="font-size:0.7rem;color:var(--text-dim);"><?= h($c['username']) ?></span>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </main>
</div>

<div class="flex-spacer"></div>
<?php renderFooter(); ?>
</body>
</html>
