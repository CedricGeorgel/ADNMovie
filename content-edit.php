<?php
/**
 * CONTENT-EDIT.PHP — Création ou édition d'une liste / critique
 * URL create : content-edit.php
 * URL edit   : content-edit.php?id=42
 */
require_once 'functions/utils.php';
require_once 'functions/auth.php';
require_once 'components/header.php';
require_once 'components/nav.php';
require_once 'components/footer.php';

check_auth();

$currentUserId = $_SESSION['user_id'];
$currentUser   = get_user_by_id($currentUserId);

$editId  = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$content = null;
$items   = [];

if ($editId) {
    $content = db_fetch_one('SELECT * FROM user_content WHERE id = ? AND user_id = ?', [$editId, $currentUserId]);
    if (!$content) { header('Location: archives.php'); exit; }

    $items = db_fetch_all(
        'SELECT uci.position, uci.note, m.tmdb_id AS movie_id, m.title, m.poster, m.year
         FROM user_content_items uci
         JOIN movies m ON m.tmdb_id = uci.movie_id
         WHERE uci.content_id = ? ORDER BY uci.position ASC',
        [$editId]
    ) ?: [];
}

$pageTitle = $editId ? 'Modifier' : 'Nouveau contenu';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title><?= $pageTitle ?></title>
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
    <?php if (function_exists('renderScripts')) renderScripts(); ?>
    <meta name="theme-color" content="#050505">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
</head>
<body class="page-content-edit">
<div class="container">
    <?php renderHeader($currentUser); ?>
    <?php renderNav('archives'); ?>

    <main class="edit-layout">
        <div style="margin-bottom:24px;display:flex;align-items:center;justify-content:space-between;">
            <h1 style="font-size:1rem;font-weight:900;text-transform:uppercase;letter-spacing:2px;"><?= $pageTitle ?></h1>
            <a href="<?= $editId ? 'content.php?slug=' . h($content['slug']) : 'archives.php' ?>" style="font-size:0.7rem;color:var(--text-dim);">Annuler</a>
        </div>

        <!-- Type + titre -->
        <div class="edit-card">
            <label class="edit-label">Type</label>
            <div class="type-toggle" style="margin-bottom:20px;">
                <button type="button" class="type-btn <?= (!$editId || $content['type'] === 'critique') ? 'active' : '' ?>"
                        data-type="critique" onclick="setType('critique')">Critique</button>
                <button type="button" class="type-btn <?= ($editId && $content['type'] === 'list') ? 'active' : '' ?>"
                        data-type="list" onclick="setType('list')">Liste</button>
                <?php if (has_role('moderator')): ?>
                <button type="button" class="type-btn <?= ($editId && $content['type'] === 'guide') ? 'active' : '' ?>"
                        data-type="guide" onclick="setType('guide')"
                        style="color:#A7C7E7;border-color:rgba(167,199,231,0.4);">Guide</button>
                <?php endif; ?>
            </div>
            <input type="hidden" id="selectedType" value="<?= h($content['type'] ?? 'critique') ?>">

            <label class="edit-label" style="margin-top:4px;">Titre</label>
            <input type="text" id="inputTitle" class="edit-input"
                   placeholder="Mon titre…"
                   value="<?= h($content['title'] ?? '') ?>">
        </div>

        <!-- Corps de texte avec prévisualisation live -->
        <div class="edit-card" style="padding-bottom:0;">
            <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:12px;">
                <label class="edit-label" style="margin:0;">Texte</label>
                <div style="display:flex; gap:0; border:1px solid var(--border); border-radius:8px; overflow:hidden;">
                    <button type="button" id="tabWrite" onclick="switchTab('write')"
                            style="padding:5px 14px; font-size:0.7rem; font-weight:800; letter-spacing:1px; text-transform:uppercase; background:rgba(255,255,255,0.08); color:var(--text-main); border:none; cursor:pointer; border-right:1px solid var(--border);">
                        Écrire
                    </button>
                    <button type="button" id="tabPreview" onclick="switchTab('preview')"
                            style="padding:5px 14px; font-size:0.7rem; font-weight:800; letter-spacing:1px; text-transform:uppercase; background:transparent; color:var(--text-dim); border:none; cursor:pointer;">
                        Aperçu
                    </button>
                </div>
            </div>

            <div id="paneWrite">
                <textarea id="inputBody" class="edit-textarea"
                          data-ac-context="fiche"
                          placeholder="Écrivez votre texte… #slug pour citer une archive, /id ou //id pour un film/série (carte), @pseudo pour un utilisateur. Tableaux : | Col1 | Col2 |"><?= h($content['body'] ?? '') ?></textarea>
                <p style="font-size:0.62rem; color:var(--text-dim); padding:8px 0 12px; margin:0; opacity:0.6;">
                    **gras** · *italique* · `code` · # Titre · &gt; citation · - liste · | tableau |
                </p>
            </div>

            <div id="panePreview" style="display:none; min-height:160px; padding:16px 0 20px;">
                <div id="previewContent" class="content-body"
                     style="color:var(--text-muted); font-size:0.9rem; line-height:1.7;">
                    <span style="color:var(--text-dim); font-style:italic; font-size:0.8rem;">Chargement…</span>
                </div>
            </div>
        </div>

        <!-- Films (visible uniquement si type = list) -->
        <div class="edit-card" id="itemsCard" style="<?= (!$editId || $content['type'] === 'list') ? '' : 'display:none;' ?>">
            <label class="edit-label">Films de la liste</label>
            <div class="film-search-wrap" style="margin-bottom:16px;">
                <input type="text" id="filmSearch" class="edit-input" placeholder="Rechercher un film à ajouter…" autocomplete="off">
                <div class="film-suggestions" id="filmSuggestions" style="display:none;"></div>
            </div>
            <div id="itemsList">
                <?php foreach ($items as $i => $item): ?>
                <div class="item-row" data-movie-id="<?= (int)$item['movie_id'] ?>" data-pos="<?= $i ?>">
                    <span class="item-handle">⠿</span>
                    <span class="item-rank"><?= $i + 1 ?></span>
                    <div class="item-poster"><img src="<?= h($item['poster']) ?>" onerror="this.src='assets/default-avatar.png'"></div>
                    <div class="item-info">
                        <div class="item-title"><?= h($item['title']) ?> <span style="color:var(--text-dim);font-weight:400;"><?= h($item['year']) ?></span></div>
                        <textarea class="item-note-input" rows="2" placeholder="Commentaire optionnel…"><?= h($item['note'] ?? '') ?></textarea>
                    </div>
                    <button class="item-remove" onclick="removeItem(this)" title="Retirer">✕</button>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Visibilité + soumission -->
        <div class="edit-card" style="display:flex;justify-content:space-between;align-items:center;gap:20px;flex-wrap:wrap;">
            <div class="visibility-toggle">
                <button type="button" id="togglePublic" class="toggle-pill <?= (!$editId || $content['is_public']) ? 'on' : '' ?>"
                        onclick="this.classList.toggle('on')"></button>
                <span style="font-size:0.78rem;color:var(--text-muted);">Visible publiquement</span>
            </div>
            <button onclick="saveContent()" class="btn-base active" style="padding:12px 28px;">
                <?= $editId ? 'Enregistrer' : 'Publier' ?>
            </button>
        </div>

        <div id="saveError" style="display:none;color:var(--danger);font-size:0.78rem;text-align:center;margin-top:10px;"></div>
    </main>
</div>

<div class="flex-spacer"></div>
<?php renderFooter(); ?>

<script>
const EDIT_ID = <?= $editId ?>;
</script>
<script src="/assets/js/content-edit.js?v=1" defer></script>
<script src="assets/js/comment-autocomplete.js" defer></script>
</body>
</html>
