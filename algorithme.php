<?php
// L'inclusion de utils.php gère désormais le démarrage de la session persistante
require_once 'functions/utils.php';
require_once 'functions/auth.php';
require_once 'functions/ratings_logic.php';
require_once 'components/header.php';
require_once 'components/nav.php';
require_once 'components/footer.php';

$currentUser = isset($_SESSION['user_id']) ? get_user_by_id($_SESSION['user_id']) : null;
$userId      = $_SESSION['user_id'] ?? null;

$analysisCount = 0;
$dnaCount      = 0;
if ($userId) {
    $analysisCount = (int)db_fetch_one('SELECT COUNT(*) AS c FROM ratings WHERE user_id = ? AND scores IS NOT NULL', [$userId])['c'];
    $dnaCount      = (int)db_fetch_one('SELECT COUNT(*) AS c FROM user_dna WHERE user_id = ?', [$userId])['c'];
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>L'Algorithme </title>
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
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="assets/js/radars.js" defer></script>
    <meta name="theme-color" content="#050505">

<meta name="apple-mobile-web-app-capable" content="yes">

<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
</head>
<body class="page-algo">
<div class="container">
    <?php renderHeader($currentUser); ?>
    <?php renderNav(''); ?>

    <main style="padding: 40px 0 80px; max-width: 780px;">

        <!-- Hero -->
        <div style="margin-bottom:56px;">
            <span class="algo-label">Documentation</span>
            <h1 style="font-size:2.8rem;font-weight:900;letter-spacing:-1.5px;margin-bottom:16px;">
                Comment fonctionne<br>la Séquence ADN ?
            </h1>
            <p style="color:var(--text-dim);font-size:0.9rem;line-height:1.7;max-width:580px;">
                Pas de boîte noire. Pas de score mystérieux. Voici exactement comment l'algorithme
                construit votre profil et sélectionne vos recommandations.
            </p>

            <?php if ($userId): ?>
            <div class="user-stats">
                <div class="user-stat">
                    <div class="user-stat-val"><?= $analysisCount ?></div>
                    <div class="user-stat-lab">Analyses transmises</div>
                </div>
                <div class="user-stat">
                    <div class="user-stat-val"><?= $dnaCount ?>/9</div>
                    <div class="user-stat-lab">Axes ADN actifs</div>
                </div>
                <div class="user-stat">
                    <div class="user-stat-val"><?= $analysisCount >= 10 ? '✓' : $analysisCount . '/10' ?></div>
                    <div class="user-stat-lab">Seuil de recommandation</div>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <hr class="algo-sep">

        <!-- Étape 1 : Les 9 axes -->
        <div class="algo-section">
            <span class="algo-label">Étape 01</span>
            <h2 class="algo-title">Vous évaluez sur 9 axes bipolaires</h2>
            <p class="algo-body">
                Chaque film que vous analysez est évalué sur <strong>jusqu'à 3 axes</strong> que vous choisissez parmi 9.
                Pas de note globale — vous décrivez l'œuvre sur les dimensions qui comptent vraiment pour vous.
                Les 6 axes non renseignés restent à <strong>null</strong> : ils n'influencent pas votre ADN.
            </p>

            <table class="criteria-table">
                <tr><th>Axe</th><th>Pôle négatif</th><th>Pôle positif</th></tr>
                <tr><td>Complexité</td>    <td>Récit évident</td>         <td>Récit alambiqué</td></tr>
                <tr><td>Vraisemblance</td> <td>Totalement irréel</td>      <td>Totalement crédible</td></tr>
                <tr><td>Effroi</td>        <td>Rassurant</td>              <td>Terrifiant</td></tr>
                <tr><td>Aventure</td>      <td>Aucun ailleurs</td>         <td>Envie de partir</td></tr>
                <tr><td>Rythme</td>        <td>Lent &amp; dispersé</td>    <td>Intense &amp; prenant</td></tr>
                <tr><td>Suspense</td>      <td>Prévisible</td>             <td>Imprévisible</td></tr>
                <tr><td>Vibe</td>          <td>Agréable</td>               <td>Dérangeant</td></tr>
                <tr><td>Esthétique</td>    <td>Brut / naturaliste</td>     <td>Très stylisé</td></tr>
                <tr><td>Sentiment</td>     <td>Détaché</td>                <td>Très chargé</td></tr>
            </table>
        </div>

        <hr class="algo-sep">

        <!-- Étape 2 : DNA différentiel -->
        <div class="algo-section">
            <span class="algo-label">Étape 02</span>
            <h2 class="algo-title">Votre ADN est construit par différence</h2>
            <p class="algo-body">
                L'algorithme ne moyenne pas vos notes. Il calcule un <strong>signal différentiel</strong> :
                ce qui vous plaît dans les films que vous aimez, moins ce qui vous plaît dans les films que vous n'aimez pas.
            </p>

            <div class="algo-formula">
                DNA[axe] = avg_pondéré(<span style="color:#6ee7b7">films aimés</span>) − avg_pondéré(<span style="color:#f87171">films pas aimés</span>)<br>
                <span class="comment">// Si vous notez "effroi +8" sur les films adorés et "effroi +8" sur les films détestés</span><br>
                <span class="comment">// → effroi = 0 : ce critère ne vous différencie pas</span>
            </div>

            <p class="algo-body" style="margin-top:12px;">
                Chaque note est aussi pondérée dans le temps :
            </p>
            <div class="algo-formula">
                poids = rating_weight × e<sup>−0.01 × jours</sup><br>
                <span class="comment">// Une note d'hier compte plus qu'une note de 6 mois</span><br>
                <span class="comment">// Votre ADN suit l'évolution naturelle de vos goûts</span>
            </div>

            <p class="algo-body" style="margin-top:12px;">
                Un signal inférieur à <strong>|1.5|</strong> est filtré comme du bruit.
                Seules vos convictions fortes entrent dans votre profil.
            </p>
        </div>

        <hr class="algo-sep">

        <!-- Étape 3 : Score de similarité -->
        <div class="algo-section">
            <span class="algo-label">Étape 03</span>
            <h2 class="algo-title">Les films sont scorés par produit scalaire</h2>
            <p class="algo-body">
                Pour chaque film du catalogue, l'algorithme calcule un score de correspondance
                en multipliant votre ADN par le DNA collectif du film — pondéré par la fiabilité des données.
            </p>

            <div class="algo-formula">
                score = Σ DNA_user[c] × film_avg[c] × fiabilité[c]<br><br>
                fiabilité[c] = votes_sur_ce_critère / (votes + 5)<br>
                <span class="comment">// 2 votes → fiabilité 0.28 | 20 votes → 0.80 | 50 votes → 0.91</span>
            </div>

            <p class="algo-body" style="margin-top:12px;">
                Un DNA <strong>négatif</strong> sur un axe génère un <strong>malus automatique</strong>
                si le film est fort sur cet axe. Pas besoin de logique "j'aime / je n'aime pas" séparée —
                le signe du vecteur fait le travail.
            </p>
        </div>

        <hr class="algo-sep">

        <!-- Étape 4 : Exploration -->
        <div class="algo-section">
            <span class="algo-label">Étape 04</span>
            <h2 class="algo-title">Un slot sur 6 vous pousse hors de votre zone</h2>
            <p class="algo-body">
                Parmi vos 6 recommandations, <strong>1 film</strong> est sélectionné différemment.
                L'algorithme cible vos axes les moins représentés dans votre ADN
                et injecte un film fort sur ces dimensions inexplorées.
            </p>
            <p class="algo-body" style="margin-top:12px;">
                Si vous aimez ce film → le critère entre dans votre profil.
                Si non → il est progressivement marqué comme non-pertinent pour vous.
                Ce slot est signalé par le badge <span class="pill pill-amber">🔭 Découverte</span>.
            </p>
            <p class="algo-body" style="margin-top:12px;">
                Le taux d'exploration diminue avec l'expérience :
            </p>
            <div class="algo-formula">
                ε = 1 / (1 + log(films_notés))<br>
                <span class="comment">// 10 films → ε ≈ 0.30 | 50 films → ε ≈ 0.25 | 200 films → ε ≈ 0.19</span><br>
                <span class="comment">// Ne descend jamais à 0 — l'exploration ne s'arrête jamais</span>
            </div>
        </div>

        <hr class="algo-sep">

        <!-- Étape 5 : Feedback loop -->
        <div class="algo-section">
            <span class="algo-label">Étape 05</span>
            <h2 class="algo-title">Les 6 films restent jusqu'à ce que vous réagissiez</h2>
            <p class="algo-body">
                Les recommandations ne changent pas au rechargement de la page.
                Elles sont <strong>persistées</strong> et évoluent uniquement quand vous agissez :
            </p>

            <div class="step-grid" style="margin-top:20px;">
                <div class="step-card">
                    <div class="step-num">Action 01</div>
                    <div class="step-title">🎬 Vous analysez le film</div>
                    <div class="step-body">La reco passe à "vu" ou "aimé". Votre ADN est recalculé. Les 6 films se renouvellent quand tous ont été traités.</div>
                </div>
                <div class="step-card">
                    <div class="step-num">Action 02</div>
                    <div class="step-title">✕ Vous refusez le film</div>
                    <div class="step-body">Le film est marqué "skippé" et exclu de toutes vos futures recommandations. Il ne reviendra plus jamais.</div>
                </div>
                <div class="step-card">
                    <div class="step-num">Aucune action</div>
                    <div class="step-title">⏸ Vous ne faites rien</div>
                    <div class="step-body">Les mêmes 6 films vous attendent à la prochaine visite. L'algorithme attend votre signal avant de progresser.</div>
                </div>
            </div>
        </div>

        <hr class="algo-sep">

        <!-- CTA -->
        <div style="text-align:center;padding:20px 0;">
            <p style="color:var(--text-dim);font-size:0.8rem;margin-bottom:20px;">
                <?php if ($userId && $analysisCount >= 10): ?>
                    Votre ADN est actif. <?= $dnaCount ?> axe<?= $dnaCount > 1 ? 's' : '' ?> en signal fort.
                <?php elseif ($userId): ?>
                    Il vous manque <?= 10 - $analysisCount ?> analyse<?= (10 - $analysisCount) > 1 ? 's' : '' ?> pour activer vos recommandations.
                <?php else: ?>
                    Connectez-vous pour activer votre Séquence ADN.
                <?php endif; ?>
            </p>
            <?php if ($userId && $analysisCount >= 10): ?>
                <a href="recommendations.php" class="btn-base active">Voir mes recommandations</a>
            <?php elseif ($userId): ?>
                <a href="home.php" class="btn-base active">Analyser des films</a>
            <?php else: ?>
                <a href="login.php" class="btn-base active">Se connecter</a>
            <?php endif; ?>
        </div>

    </main>
</div>
<div class="flex-spacer"></div>
<?php renderFooter(); ?>
</body>
</html>