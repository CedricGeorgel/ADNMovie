<?php
require_once 'functions/utils.php';
require_once 'functions/auth.php';
require_once 'functions/rooms_logic.php';
require_once 'functions/badges_logic.php';
require_once 'functions/achievements_logic.php';
require_once 'components/header.php';
require_once 'components/nav.php';
require_once 'components/footer.php';
require_once 'components/radar-chart.php';
require_once 'components/event-card.php';
require_once 'components/room-card.php';
require_once 'config/settings.php';
require_once 'functions/notifications_logic.php';
check_auth();
$currentUser = get_user_by_id($_SESSION['user_id']);
$userId = $_SESSION['user_id'];

$message = "";
$activeTab = $_GET['tab'] ?? 'bug';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title  = trim($_POST['title'] ?? '');
    $type   = $_POST['type'] ?? 'bug';
    $desc   = trim($_POST['description'] ?? '');

    if (!in_array($type, ['bug', 'feature', 'ui', 'algo'])) {
        $type = 'bug';
    }

    if (mb_strlen($title) < 5) {
        $message = "❌ Le titre est trop court (5 caractères minimum).";
    } else {
        db_execute(
            "INSERT INTO system_management (user_id, type, title, description, status, priority) 
             VALUES (?, ?, ?, ?, 'pending', 'medium')",
            [$userId, $type, $title, $desc]
        );

        $reportId = (int)db_last_id();

        try {
            notify_admins_report($currentUser['username'], $type, $title, $reportId);
        } catch (Exception $e) {
            error_log('notify_admins_report failed: ' . $e->getMessage());
        }

        $message = $type === 'bug'
            ? "✅ Bug report transmis. Merci de nous aider à améliorer le système !"
            : "✅ Suggestion envoyée. Elle sera étudiée par l'équipe !";
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Rapport de Système</title>
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
<body>
    <div class="container">
        <?php renderHeader($currentUser); ?>
        <main style="max-width:600px; margin: 40px auto; padding: 0 16px 40px;">

            <h1 style="font-weight:900; letter-spacing:-1px; margin-bottom:6px;">SIGNALEMENT</h1>
            <p style="color:var(--text-dim); font-size:0.85rem; margin-bottom:30px;">
                Transmettez vos données au protocole. Émetteur : <span style="color:var(--pastel-blue); font-weight:700;"><?= h($currentUser['username']) ?></span>
                <span style="color:var(--text-dim); font-family:monospace; font-size:0.75rem;"> #<?= h($userId) ?></span>
            </p>

            <!-- ONGLETS -->
            <div class="report-tabs">
                <button class="report-tab <?= $activeTab === 'bug' ? 'active' : '' ?>"
                        onclick="window.location='?tab=bug'">🐞 BUG REPORT</button>
                <button class="report-tab <?= $activeTab === 'feature' ? 'active' : '' ?>"
                        onclick="window.location='?tab=feature'">💡 FEATURE REQUEST</button>
            </div>

            <?php if ($message): ?>
                <div style="padding:15px; border-radius:8px; background:rgba(255,255,255,0.05);
                            margin-bottom:20px; font-size:0.9rem; border:1px solid var(--border);">
                    <?= $message ?>
                </div>
            <?php endif; ?>

            <!-- ═══════════════ BUG REPORT ═══════════════ -->
            <?php if ($activeTab === 'bug'): ?>
            <form method="POST" action="?tab=bug" class="dash-card" style="display:flex; flex-direction:column; gap:20px;">
                <input type="hidden" name="type" value="bug">

                <div class="form-group">
                    <label style="display:block; font-size:0.7rem; font-weight:800; margin-bottom:8px; color:#f87171;">TITRE DU BUG</label>
                    <input type="text" name="title" class="input-room-create"
                           placeholder="Ex: La fenêtre de notation des films ne s'ouvre pas" required
                           style="width:100%;">
                </div>

                <div class="form-group">
                    <label style="display:block; font-size:0.7rem; font-weight:800; margin-bottom:8px; color:#f87171;">DESCRIPTION</label>
                    <textarea name="description" class="input-room-create"
                              style="width:100%; min-height:140px;"
                              placeholder="Décrivez le bug : que se passe-t-il ? Dans quelles conditions ? Sur quel appareil / navigateur ?"></textarea>
                </div>

                <button type="submit" class="btn-base active"
                        style="width:100%; background:#f87171; color:#000; border-color:#f87171;">
                    🐞 SIGNALER LE BUG
                </button>
            </form>

            <!-- ═══════════════ FEATURE REQUEST ═══════════════ -->
            <?php else: ?>
            <form method="POST" action="?tab=feature" class="dash-card" style="display:flex; flex-direction:column; gap:20px;">
                <input type="hidden" name="type" value="feature">

                <div class="form-group">
                    <label style="display:block; font-size:0.7rem; font-weight:800; margin-bottom:8px; color:var(--pastel-blue);">TITRE DE LA SUGGESTION</label>
                    <input type="text" name="title" class="input-room-create"
                           placeholder="Ex: Faire un distibuteur de popcorn" required
                           style="width:100%;">
                </div>

                <div class="form-group">
                    <label style="display:block; font-size:0.7rem; font-weight:800; margin-bottom:8px; color:var(--pastel-blue);">DESCRIPTION</label>
                    <textarea name="description" class="input-room-create"
                              style="width:100%; min-height:140px;"
                              placeholder="Décrivez votre idée en détail. Pourquoi serait-elle utile ?"></textarea>
                </div>

                <button type="submit" class="btn-base active" style="width:100%;">
                    💡 SOUMETTRE LA SUGGESTION
                </button>
            </form>
            <?php endif; ?>

        </main>
    </div>
    <?php renderFooter(); ?>
</body>
</html>