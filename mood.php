<?php
require_once 'config/settings.php';
require_once 'functions/utils.php';
require_once 'functions/auth.php';
require_once 'components/header.php';
require_once 'components/nav.php';
require_once 'components/footer.php';
require_once 'components/movie-card.php';

if (session_status() === PHP_SESSION_NONE) session_start();
$user = isset($_SESSION['user_id']) ? get_user_by_id($_SESSION['user_id']) : null;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Ce soir j'ai envie de... </title>
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
<body class="page-mood">
<div class="container">
    <?php renderHeader($user); ?>
    <?php renderNav('mood'); ?>

    <main>

        <!-- Hero -->
        <section class="rooms-header">
            <h1>SÉLECTEUR D'HUMEUR</h1>
        </section>

        <!-- Onglets -->
        <div class="mood-tabs">
            <button class="mood-tab-btn active" data-tab="sequenceur">Séquenceur</button>
            <button class="mood-tab-btn" data-tab="anomalie">Anomalie</button>
        </div>

        <!-- ── Tab 1 : Séquenceur ─────────────────────────────────────── -->
        <div class="mood-tab-panel" id="tab-sequenceur">

        <section class="mood-sliders">

            <!-- Slider 1 : Énergie -->
            <div class="mood-slider-group">
                <div class="mood-slider-meta">
                    <div class="mood-slider-title">
                        <span>⚡</span> Énergie
                    </div>
                    <span class="mood-slider-value" id="val-energie">5</span>
                </div>
                <div class="mood-slider-labels">
                    <span>Détente totale</span>
                    <span>Pur adrénaline</span>
                </div>
                <input type="range" class="mood-range" id="slider-energie"
                       min="0" max="10" step="1" value="5"
                       oninput="onMoodSliderChange('energie', this.value)">
            </div>

            <!-- Slider 2 : Émotion -->
            <div class="mood-slider-group">
                <div class="mood-slider-meta">
                    <div class="mood-slider-title">
                        <span>💙</span> Émotion
                    </div>
                    <span class="mood-slider-value" id="val-emotion">5</span>
                </div>
                <div class="mood-slider-labels">
                    <span>Légèreté & fun</span>
                    <span>Profondeur & drame</span>
                </div>
                <input type="range" class="mood-range" id="slider-emotion"
                       min="0" max="10" step="1" value="5"
                       oninput="onMoodSliderChange('emotion', this.value)">
            </div>

            <!-- Slider 3 : Réalité -->
            <div class="mood-slider-group">
                <div class="mood-slider-meta">
                    <div class="mood-slider-title">
                        <span>🌍</span> Réalité
                    </div>
                    <span class="mood-slider-value" id="val-realite">5</span>
                </div>
                <div class="mood-slider-labels">
                    <span>Fantastique & évasion</span>
                    <span>Ancré dans le réel</span>
                </div>
                <input type="range" class="mood-range" id="slider-realite"
                       min="0" max="10" step="1" value="5"
                       oninput="onMoodSliderChange('realite', this.value)">
            </div>

        </section>

        <!-- Zone résultat -->
        <section class="mood-result-section">
            <?php
            $hasPlatforms = $user && !empty(json_decode($user['user_platforms'] ?? '[]', true));
            if ($hasPlatforms): ?>
            <label class="mood-platform-switch">
                <input type="checkbox" id="toggle-my-platforms">
                <span class="mood-platform-switch-track"></span>
                <span class="mood-platform-switch-label">Mes abonnements uniquement</span>
            </label>
            <?php endif; ?>
            <p class="mood-result-label">Meilleure correspondance</p>

            <div class="mood-loading" id="mood-loading">
                <div class="mood-loading-dots"></div>
            </div>

            <div class="mood-empty" id="mood-empty">
                Aucun film correspondant pour le moment.
            </div>

            <div id="mood-result"></div>
        </section>

        </div><!-- /tab-sequenceur -->

        <!-- ── Tab 2 : Anomalie ───────────────────────────────────────── -->
        <div class="mood-tab-panel" id="tab-anomalie" hidden>
            <div class="anomaly-panel">
                <div class="anomaly-log">
                    &gt; INVERSION ADN EN COURS...<br>
                    &gt; SÉLECTION : FILMS MASSIVEMENT APPRÉCIÉS<br>
                    &gt; CORRESPONDANCE : ADN INVERSE DÉTECTÉE
                </div>
                <span class="label-glitch anomaly-title" data-text="ANTI-RÉSONANCE">ANTI-RÉSONANCE</span>
                <p class="anomaly-desc">Ces films contredisent ton génome cinématographique, et pourtant, la communauté les adore.</p>
            </div>
            <div class="anomaly-result-wrap">
                <div class="mood-loading" id="anomaly-loading" style="display:none;"><div class="mood-loading-dots"></div></div>
                <div class="mood-empty" id="anomaly-empty" style="display:none;">Aucune anomalie détectable pour le moment.</div>
                <div id="anomaly-result"></div>
            </div>
        </div><!-- /tab-anomalie -->

    </main>

    <?php renderFooter(); ?>
</div>
</body>
</html>