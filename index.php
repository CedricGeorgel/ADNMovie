<?php
require_once 'functions/utils.php';
require_once 'functions/auth.php';
require_once 'components/header.php';
require_once 'components/footer.php';

$currentUser = isset($_SESSION['user_id']) ? get_user_by_id($_SESSION['user_id']) : null;

$ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
$isMobile = preg_match('/iPhone|iPad|iPod|Android/i', $ua);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>ADNMovie - Décodez votre cinéma</title>
    <link rel="icon" href="/assets/Icons/Logo2.ico" type="image/x-icon">
    <link rel="apple-touch-icon" href="/assets/Icons/Logo3.png">
    <meta name='impact-site-verification' value='402dd0c3-d3a8-4a27-a539-1534c3165f8e'>
    <meta property="og:site_name" content="ADN Movie">
    <meta property="og:type" content="website">
    <meta property="og:title" content="ADN Movie">
    <meta property="og:description" content="Découvrez vos résonances cinématographiques">
    <meta property="og:image" content="https://adnmovie.fr/assets/Icons/Named_logo1.png">
    <meta name="twitter:card" content="summary">
    <meta name="twitter:image" content="https://adnmovie.fr/assets/Icons/Named_logo1.png">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="assets/style.css?v=<?= filemtime('assets/style.css') ?>">
    <link rel="stylesheet" href="assets/index.style.css?v=<?= filemtime('assets/index.style.css') ?>">
    <?php if (function_exists('renderScripts')) renderScripts(); ?>
    <meta name="theme-color" content="#050505">

<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
</head>
<body class="page-landing">
    <div class="container">
        <?php renderHeader($currentUser); ?>
        <main class="landing-layout" style="position: relative;">
            <section class="hero-manifesto" style="position: relative; z-index: 1;">
                <div class="hero-content">
                    <span class="tagline">PROJET : SÉQUENCE ADN</span>
                    <h1>L'ALGORITHME QUI RESSENT.</h1>
                    <p class="manifesto-text">
                        Marre des algorithmes qui vous proposent des comédies parce que vous avez regardé un dessin animé ? Nous aussi.<br><br>
                        Séquence ADN ne s'intéresse pas aux étiquettes, mais à ce que vous ressentez vraiment. Nous décodons l'âme, l'ADN de chaque film : son rythme, sa folie, sa beauté brute. On ne vous propose pas ce que vous devriez aimer, mais ce qui va vous faire vibrer.
                    </p>
                    <div class="hero-actions">
                        <a href="home.php" class="btn-base active">EXPLORER LA BIBLIOTHÈQUE</a>
                        <?php if (!$currentUser): ?>
                            <a href="login.php" class="btn-base">SE CONNECTER</a>
                        <?php endif; ?>
                        <?php if ($isMobile): ?>
                            <a href="install.php" class="btn-base">INSTALLER L'APPLICATION</a>
                        <?php endif; ?>
                    </div>
                </div>
            </section>
            <section class="visual-dna-split" style="position: relative; z-index: 1;">
                <div class="visual-text">
                    <h2>LA RÉSONANCE <br>PLUTÔT QUE LE CLASSEMENT.</h2>
                    <p>Chaque film possède une signature unique. Notre moteur calcule la distance entre votre identité et l'œuvre pour trouver la pépite qui vous correspond, ici et maintenant.</p>
                </div>
                <div class="radar-wrapper" style="position: relative; width: 100%; max-width: 450px; margin: 0 auto;">
                    <canvas id="homeRadarDemo"></canvas>
                </div>
                <script src="/assets/js/index.js?v=1" defer></script>
            </section>
            <section class="algo-details" style="position: relative; z-index: 1;">
                <div class="algo-grid">
                    <div class="algo-card"><div class="algo-num">01</div><h3>Votre Signature</h3><p>On synthétise votre ADN de cinéphile en analysant vos émotions les plus fortes. Plus vous notez, plus votre profil devient précis.</p></div>
                    <div class="algo-card"><div class="algo-num">02</div><h3>Consensus Social</h3><p>On filtre le bruit. Le système identifie les critères sur lesquels la communauté s'accorde pour vous garantir une recommandation fiable.</p></div>
                    <div class="algo-card"><div class="algo-num">03</div><h3>Match Émotionnel</h3><p>L'algorithme cherche une résonance. Il compare vos attentes aux gènes du film pour prédire votre prochain coup de cœur.</p></div>
                    <div class="algo-card"><div class="algo-num">04</div><h3>Dose de Destin</h3><p>Pour éviter de tourner en rond, on injecte une dose de hasard. Le système vous propose parfois l'inattendu pour tester vos limites.</p></div>
                </div>
            </section>
        </main>
    </div>
    <?php renderFooter(); ?>
</body>
</html>