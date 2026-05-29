<?php
// 404.php — aucune lecture JSON, aucun changement
require_once 'functions/utils.php'; // L'initialisation de la session persistante se fait désormais ici
require_once 'components/header.php';
require_once 'components/nav.php';
require_once 'components/footer.php';

$currentUser = isset($_SESSION['user_id']) ? get_user_by_id($_SESSION['user_id']) : null;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>404 - Scène coupée au montage </title>
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
    <meta name="theme-color" content="#050505">

<meta name="apple-mobile-web-app-capable" content="yes">

<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
</head>
<body class="page-404">
    <div class="container">
        <?php renderHeader($currentUser); ?>
        <main class="error-page">
            <div style="font-size:50px;margin-bottom:20px;">🎬</div>
            <div class="error-code">404</div>
            <div class="error-message">
                <div id="incidentBadge" class="incident-badge" style="display:none;"></div>
                <h1>SCÈNE COUPÉE AU MONTAGE</h1>
                <p id="errorMsg">Désolé, cette page ne fait pas partie du scénario. Elle a probablement été supprimée lors de la post-production ou n'a jamais existé.</p>
                <a href="index.php" class="btn-base active">RETOURNER À L'ACCUEIL</a>
            </div>
        </main>
    </div>
    <?php renderFooter(); ?>
    <script src="/assets/js/404.js?v=1" defer></script>
</body>
</html>