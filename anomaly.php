<?php
// L'inclusion de utils.php s'occupe de démarrer la session de 30 jours
require_once 'functions/utils.php';
require_once 'functions/auth.php';
require_once 'functions/core_db.php';

$targetId = $_GET['id'] ?? null;
if (!$targetId) die("ERREUR_CRITIQUE_0x0001 : SÉQUENCE_MANQUANTE");

$targetUser = get_user_by_id($targetId);
if (!$targetUser) die("ERREUR_CRITIQUE_0x0002 : CITOYEN_SUPPRIMÉ");

$currentUserId = $_SESSION['user_id'] ?? null;
$badgeAwarded  = false;

if ($currentUserId) {
    $already = db_fetch_one(
        'SELECT 1 FROM user_badges WHERE user_id = ? AND badge_id = ?',
        [$currentUserId, 'ANOMALY']
    );
    if (!$already) {
        db_execute(
            'INSERT IGNORE INTO user_badges (user_id, badge_id, earned_at) VALUES (?, ?, NOW())',
            [$currentUserId, 'ANOMALY']
        );
        $badgeAwarded = true;
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>/// CORRUPTION_SYSTÈME ///</title>
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
<body class="page-anomaly">
    <div class="glitch-container">
        <img src="<?= h($targetUser['avatar']) ?>" class="glitch-avatar" alt="Corrupted Avatar" onerror="this.src='assets/default-avatar.png';">
        <br><br>
        <div class="glitch-text" data-text="ADN_<?= h($targetUser['username']) ?>_CORROMPU">
            ADN_<?= h($targetUser['username']) ?>_CORROMPU
        </div>
        <div class="system-log">
            > EXÉCUTION DIAGNOSTIQUE...<br>
            > ÉCHEC. SECTEURS DÉFECTUEUX DÉTECTÉS.<br>
            > TENTATIVE DE RÉCUPÉRATION DU RADAR... [FAILED]<br>
            > FUITE DE DONNÉES EN COURS...
        </div>
        <?php if ($badgeAwarded): ?>
            <div class="award-alert">> ACCRÉDITATION MISE À JOUR : SUCCÈS "VISION TROUBLE" DÉVERROUILLÉ.</div>
        <?php endif; ?>
        <div style="margin-top:40px;">
            <a href="adn.php?id=<?= h($targetId) ?>" class="btn-base" style="background:transparent;border:1px solid var(--danger);color:var(--danger);">RETOUR AU SYSTÈME SÉCURISÉ</a>
    </div>
    <script src="assets/js/anomaly.js?v=<?= filemtime('assets/js/anomaly.js') ?>" defer></script>
</body>
</html>