<?php
/**
 * CONTENT.PHP — Affichage d'une liste ou critique utilisateur
 * URL : content.php?slug=mon-top-kubrick
 */
require_once 'functions/utils.php';
require_once 'functions/auth.php';
require_once 'components/header.php';
require_once 'components/nav.php';
require_once 'components/footer.php';
require_once 'components/avatar.php';
require_once 'components/signal-btn.php';
require_once 'components/comment.php';

$slug = trim($_GET['slug'] ?? '');
if (!$slug) { header('Location: archives.php'); exit; }

$content = db_fetch_one(
    'SELECT uc.*, u.username, u.avatar, u.role
     FROM user_content uc
     JOIN users u ON u.id = uc.user_id
     WHERE uc.slug = ?',
    [$slug]
);
if (!$content) { header('Location: 404.php'); exit; }

$currentUserId = $_SESSION['user_id'] ?? null;
$currentUser   = $currentUserId ? get_user_by_id($currentUserId) : null;
$isOwner       = ($currentUserId === $content['user_id']);

// Vérification confidentialité
if (!$content['is_public'] && !$isOwner) {
    // Vérifie si l'auteur a rendu son contenu privé via privacy_settings
    $authorPrivacy = db_fetch_one('SELECT privacy_settings FROM users WHERE id = ?', [$content['user_id']]);
    $privacy = json_decode($authorPrivacy['privacy_settings'] ?? '{}', true) ?: [];
    if (($privacy['content_public'] ?? true) === false) {
        die('<div style="text-align:center;padding:80px;color:var(--text-dim);">🔒 Ce contenu est privé.</div>');
    }
}

// Films associés (listes)
$items = [];
if ($content['type'] === 'list') {
    $items = db_fetch_all(
        'SELECT uci.position, uci.note, m.tmdb_id AS id, m.title, m.poster, m.year
         FROM user_content_items uci
         JOIN movies m ON m.tmdb_id = uci.movie_id
         WHERE uci.content_id = ?
         ORDER BY uci.position ASC',
        [$content['id']]
    ) ?: [];
}

$typeLabel = match($content['type']) { 'list' => 'LISTE', 'guide' => 'GUIDE', default => 'CRITIQUE' };
$typeColor = match($content['type']) { 'list' => 'var(--pastel-blue)', 'guide' => '#A7C7E7', default => 'var(--color-amber)' };
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <?php
    $ogPageTitle = h($content['title']) . ' — ' . $typeLabel . ' · ADN Movie';
    $ogDesc      = h(mb_substr(strip_tags($content['body'] ?? ''), 0, 155));
    $ogImg       = (!empty($items[0]['poster']) && str_starts_with($items[0]['poster'], 'http'))
        ? h($items[0]['poster'])
        : 'https://adnmovie.fr/assets/Icons/Named_logo1.png';
    $canonical   = 'https://adnmovie.fr/content.php?slug=' . rawurlencode($content['slug']);
    ?>
    <title><?= $ogPageTitle ?></title>
    <link rel="canonical" href="<?= $canonical ?>">
    <?php if (!$content['is_public']): ?><meta name="robots" content="noindex,nofollow"><?php endif; ?>
    <link rel="icon" href="/assets/Icons/Logo2.ico" type="image/x-icon">
    <link rel="apple-touch-icon" href="/assets/Icons/Logo3.png">
    <meta name="description" content="<?= $ogDesc ?>">
    <meta name="author" content="<?= h($content['username']) ?>">
    <meta property="og:site_name" content="ADN Movie">
    <meta property="og:type" content="article">
    <meta property="og:title" content="<?= $ogPageTitle ?>">
    <meta property="og:description" content="<?= $ogDesc ?>">
    <meta property="og:image" content="<?= $ogImg ?>">
    <meta property="og:url" content="<?= $canonical ?>">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?= $ogPageTitle ?>">
    <meta name="twitter:description" content="<?= $ogDesc ?>">
    <meta name="twitter:image" content="<?= $ogImg ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="assets/style.css?v=<?= filemtime('assets/style.css') ?>">
    <?php if (function_exists('renderScripts')) renderScripts(); ?>
    <meta name="theme-color" content="#050505">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
</head>
<body class="page-content">
<div class="container">
    <?php renderHeader($currentUser); ?>
    <?php renderNav('archives'); ?>

    <main style="max-width:800px; margin:0 auto; padding-bottom:60px;">

        <!-- ═══ HERO ═══ -->
        <div class="content-hero">
            <span class="content-type-badge" style="color:<?= $typeColor ?>;border-color:<?= $typeColor ?>;">
                <?= $typeLabel ?>
            </span>

            <h1 class="content-title"><?= h($content['title']) ?></h1>
            <p style="font-size:0.65rem;color:var(--text-dim);font-family:monospace;margin:2px 0 10px;letter-spacing:0.5px;">Archive #<?= (int)$content['id'] ?></p>

            <div class="content-author">
                <?php
                $mockAuthor = ['username' => $content['username'], 'avatar' => $content['avatar']];
                renderAvatar($mockAuthor, $content['user_id'], 28);
                ?>
                <span>
                    <a href="adn.php?id=<?= h($content['user_id']) ?>"
                       style="color:var(--pastel-blue);text-decoration:none;font-weight:700;">
                        <?= h($content['username']) ?>
                    </a>
                    <?= role_shield_html($content['role'] ?? 'user') ?>
                </span>
                <span style="opacity:0.4;">·</span>
                <span><?= date('d/m/Y', strtotime($content['created_at'])) ?></span>
                <?php if ($content['updated_at'] !== $content['created_at']): ?>
                    <span style="opacity:0.4;">· modifié le <?= date('d/m/Y', strtotime($content['updated_at'])) ?></span>
                <?php endif; ?>
                <?php if ($currentUser && !$isOwner): ?>
                    <span style="opacity:0.4;">·</span>
                    <?php renderSignalBtn('content', (int)$content['id'], true); ?>
                <?php endif; ?>
            </div>

            <?php
            $isMod = has_role('moderator') || has_role('admin') || has_role('superadmin');
            if ($isOwner || $isMod):
            ?>
            <div class="content-actions">
                <?php if ($isOwner): ?>
                <a href="content-edit.php?id=<?= (int)$content['id'] ?>" class="btn-base" style="font-size:0.7rem;padding:7px 14px;">
                    ✏️ Modifier
                </a>
                <?php endif; ?>
                <button onclick="deleteContent(<?= (int)$content['id'] ?>)"
                        class="btn-base"
                        style="font-size:0.7rem;padding:7px 14px;color:var(--danger);border-color:rgba(255,77,77,0.3);">
                    <?= $isMod && !$isOwner ? '[Purger]' : 'Supprimer' ?>
                </button>
            </div>
            <?php endif; ?>

            <?php if (!empty($content['body'])): ?>
            <div class="content-body"><?= render_md($content['body']) ?></div>
            <?php endif; ?>
        </div>

        <!-- ═══ FILMS (listes uniquement) ═══ -->
        <?php if ($content['type'] === 'list' && !empty($items)): ?>
        <section class="content-items-list">
            <h3 style="font-size:0.75rem;letter-spacing:2px;color:var(--text-dim);margin-bottom:0;">
                SÉLECTION · <?= count($items) ?> FILM<?= count($items) > 1 ? 'S' : '' ?>
            </h3>
            <?php foreach ($items as $i => $item): ?>
            <div class="content-item-row">
                <div class="content-item-rank"><?= $i + 1 ?></div>
                <div class="content-item-poster">
                    <a href="fiche.php?id=<?= (int)$item['id'] ?>">
                        <img src="<?= h($item['poster']) ?>"
                             onerror="this.src='assets/default-avatar.png'"
                             alt="<?= h($item['title']) ?>">
                    </a>
                </div>
                <div class="content-item-info">
                    <div class="content-item-title">
                        <a href="fiche.php?id=<?= (int)$item['id'] ?>"
                           style="color:inherit;text-decoration:none;">
                            <?= h($item['title']) ?>
                        </a>
                    </div>
                    <div class="content-item-year"><?= h($item['year']) ?></div>
                    <?php if (!empty($item['note'])): ?>
                    <div class="content-item-note"><?= nl2br(h($item['note'])) ?></div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </section>
        <?php endif; ?>

        <!-- ═══ COMMENTAIRES ═══ -->
        <?php renderContentCommentSection((int)$content['id'], $currentUser, $currentUserId); ?>

    </main>
</div>

<div class="flex-spacer"></div>
<?php renderFooter(); ?>
<?php if ($currentUser) renderSignalModal(); ?>

<script>
const CC_CONTENT_ID     = <?= (int)$content['id'] ?>;
const CC_IS_LOGGED      = <?= $currentUser ? 'true' : 'false' ?>;
const CC_IS_ADMIN       = <?= (has_role('admin') || has_role('superadmin') || has_role('moderator')) ? 'true' : 'false' ?>;
const CC_CURRENT_USER_ID = '<?= addslashes($currentUserId ?? '') ?>';
</script>
<script src="/assets/js/content.js?v=<?= filemtime('assets/js/content.js') ?>" defer></script>
</body>
</html>
