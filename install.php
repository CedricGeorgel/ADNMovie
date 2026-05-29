<?php
require_once 'functions/utils.php';
require_once 'components/header.php';
require_once 'components/nav.php';
require_once 'components/footer.php';
$currentUser = isset($_SESSION['user_id']) ? get_user_by_id($_SESSION['user_id']) : null;

$userAgent   = $_SERVER['HTTP_USER_AGENT'] ?? '';
$defaultTab  = 'desktop';

if (preg_match('/iPhone|iPad|iPod/i', $userAgent)) {
    $defaultTab = 'ios';
} elseif (preg_match('/Android/i', $userAgent)) {
    $defaultTab = 'android';
}

$tab = in_array($_GET['tab'] ?? '', ['ios', 'android', 'desktop'])
       ? $_GET['tab']
       : $defaultTab;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Installation - ADN Movie</title>
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

    <link rel="manifest" href="/manifest.json">

    <link rel="stylesheet" href="assets/style.css?v=<?= filemtime('assets/style.css') ?>">
</head>
<body class="page-legal">
<div class="container">
    <?php renderHeader($currentUser); ?>
    <?php renderNav(''); ?>

    <main class="legal-wrap">

        <nav class="legal-tabs">
            <a href="install.php?tab=ios"
               class="legal-tab <?= $tab === 'ios' ? 'active' : '' ?>">
               iOS (iPhone)
            </a>
            <a href="install.php?tab=android"
               class="legal-tab <?= $tab === 'android' ? 'active' : '' ?>">
               Android
            </a>
        </nav>

        <!-- ── iOS ── -->
        <section class="legal-section <?= $tab === 'ios' ? 'active' : '' ?>">
            <div class="legal-article">
                <h2><span class="legal-tag tag-blue">iPhone</span></h2>

                <div class="legal-highlight">
                    <?php
                        $protocol   = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                        $safariUrl  = 'x-safari-' . $protocol . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
                    ?>
                    <p><strong>Prérequis :</strong> Ouvrez rigoureusement cette page dans le navigateur <a href="<?= htmlspecialchars($safariUrl) ?>" style="color:inherit;text-decoration:underline;font-weight:bold;">Safari</a>.</p>
                </div>

                <ul>
                    <li>Appuyez sur l'icône de partage (le carré traversé par une flèche ascendante, situé dans la barre de navigation inférieure).</li>
                    <li>Faites défiler le menu d'options et sélectionnez <strong>"Sur l'écran d'accueil"</strong> (ou "Add to Home Screen").</li>
                    <li>Validez en appuyant sur <strong>"Ajouter"</strong> dans l'angle supérieur droit. L'icône sera instantanément déployée sur votre appareil.</li>
                </ul>
            </div>
        </section>

        <!-- ── Android ── -->
        <section class="legal-section <?= $tab === 'android' ? 'active' : '' ?>">
            <div class="legal-article">
                <h2><span class="legal-tag tag-green">Android</span></h2>

                <!-- Bouton installation directe (affiché par JS si disponible) -->
                <div id="install-direct" style="display:none; margin: 20px 0; padding: 25px; background: rgba(255,255,255,0.03); border: 1px solid var(--border); border-radius: 12px; text-align: center;">
                    <p style="font-size: 0.85rem; color: var(--text-dim); margin-bottom: 15px;">Le système autorise une installation directe de l'application.</p>
                    <button id="btn-install" class="btn-base active" style="width: 100%; max-width: 300px;">Installer ADN Movie</button>
                </div>

                <!-- Bouton de secours toujours visible sur Android -->
                <div id="install-manual-trigger" style="display:none; margin: 20px 0; padding: 25px; background: rgba(255,255,255,0.03); border: 1px solid var(--border); border-radius: 12px; text-align: center;">
                    <p style="font-size: 0.85rem; color: var(--text-dim); margin-bottom: 15px;">
                        Pour installer ADN Movie, utilisez le menu de votre navigateur Chrome.
                    </p>
                    <button id="btn-install-manual" class="btn-base active" style="width: 100%; max-width: 300px;" onclick="document.getElementById('install-manual-steps').style.display='block'; this.style.display='none';">
                        Voir la procédure
                    </button>
                </div>

                <div id="install-manual-steps" style="display: none;">
                    <p>Procédure d'installation manuelle :</p>
                    <ul>
                        <li>Appuyez sur le menu principal de votre navigateur (les trois points verticaux, généralement situés dans l'angle supérieur droit).</li>
                        <li>Recherchez et sélectionnez l'option <strong>"Ajouter à l'écran d'accueil"</strong> ou <strong>"Installer l'application"</strong>.</li>
                        <li>Confirmez la requête système. L'application ADN Movie est désormais autonome et présente dans votre tiroir d'applications.</li>
                    </ul>
                </div>

                <p id="install-fallback-text">En cas de restriction système empêchant l'installation automatique, la procédure manuelle s'applique :</p>
                <ul id="install-fallback-list">
                    <li>Appuyez sur le menu principal de votre navigateur (les trois points verticaux, généralement situés dans l'angle supérieur droit de l'écran).</li>
                    <li>Recherchez et sélectionnez l'option <strong>"Ajouter à l'écran d'accueil"</strong> ou <strong>"Installer l'application"</strong>.</li>
                    <li>Confirmez la requête système. L'application ADN Movie est désormais autonome et présente dans votre tiroir d'applications.</li>
                </ul>
            </div>
        </section>

    </main>
</div>

<?php renderFooter(); ?>

<script src="/assets/js/install.js?v=1" defer></script>
</body>
</html>
