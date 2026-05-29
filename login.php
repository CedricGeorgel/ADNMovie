<?php

require_once 'functions/utils.php';
require_once 'functions/auth.php';
require_once 'components/header.php';
require_once 'components/footer.php';

if (isset($_SESSION['user_id'])) {
    header('Location: home.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Accès Système - ADNMovie</title>
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
<body class="page-login">
    <div class="container">
        <?php renderHeader(null); ?>
        <main class="login-page-wrapper" style="display:flex;flex-direction:column;align-items:center;justify-content:center;min-height:65vh;position:relative;padding:40px 20px;">
            <div class="dna-background" style="position:absolute;top:0;left:0;width:100%;height:100%;z-index:0;opacity: 1;pointer-events:none;mask-image: linear-gradient(to bottom, black 25%, transparent 100%); ">
                <?php include 'components/dna-animation.php'; ?>
            </div>
            <div class="login-card" style="position:relative;z-index:1;background:rgba(12, 13, 16, 0.01);padding:50px 40px;border:1px solid rgba(255,255,255,0.07);border-radius:16px;text-align:center; max-width:450px;width:100%; backdrop-filter:blur(6px) saturate(1.4);-webkit-backdrop-filter:blur(6px) saturate(1.4); box-shadow:0 8px 40px rgba(0,0,0,0.1),inset 0 1px 0 rgba(255,255,255,0.06);">
                <h1 style="font-size:2.5rem;margin-bottom:15px;font-weight:900;letter-spacing:-1px;">SÉQUENCE ADN<span>.</span></h1>
                <p style="color:var(--text-dim);font-size:0.95rem;margin-bottom:40px;line-height:1.5;">Identifiez-vous pour initialiser l'analyse de votre patrimoine cinématographique.</p>
                <div class="login-options" style="display:flex;flex-direction:column;gap:15px;">
                    <a href="api/api_login.php" class="btn-base" style="display:flex;align-items:center;justify-content:center;gap:12px;background:#5865F2;border-color:#5865F2;color:white;">
                        <img src="https://www.svgrepo.com/show/333523/discord-alt.svg" alt="Discord" style="width:20px;filter:brightness(0) invert(1);">
                        Accréditation Discord
                    </a>
                    <a href="api/api_google_login.php" class="btn-base" style="display:flex;align-items:center;justify-content:center;gap:12px;background:white;border-color:white;color:black;">
                        <img src="https://www.svgrepo.com/show/475656/google-color.svg" alt="Google" style="width:20px;">
                        Accréditation Google
                    </a>
                </div>
                <div class="login-footer" style="margin-top:40px;font-size:0.65rem;color:var(--text-dim);text-transform:uppercase;letter-spacing:1px;">Protocoles d'accès sécurisés • V2</div>
            </div>
        </main>
    </div>
    <div class="flex-spacer"></div>
    <?php renderFooter(); ?>
    <?php if (function_exists('renderScripts')) renderScripts(); ?>
</body>
</html>