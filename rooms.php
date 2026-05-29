<?php

require_once 'functions/utils.php';
require_once 'functions/auth.php';
require_once 'functions/rooms_logic.php';
require_once 'components/header.php';
require_once 'components/nav.php';
require_once 'components/footer.php';
require_once 'components/room-card.php';
require_once 'components/button.php';

check_auth();
$userId = $_SESSION['user_id'];
$currentUser = get_user_by_id($userId);

if (!$currentUser) {
    die("Erreur : Utilisateur introuvable.");
}

$myRooms     = get_user_rooms($userId);
$publicRooms = get_public_rooms($userId);
$allUsers    = get_users_directory();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Mes Sessions</title>
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
    <?php if (function_exists('renderScripts')) renderScripts(); else { echo '<script src="https://cdn.jsdelivr.net/npm/chart.js"></script><script src="assets/js/ui.js" defer></script><script src="assets/js/sessions.js" defer></script><script src="assets/script.js" defer></script>'; } ?>
<meta name="theme-color" content="#050505">

<meta name="apple-mobile-web-app-capable" content="yes">

<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    </head>
<body class="page-rooms">
    <div class="container">
        <?php renderHeader($currentUser); ?>
        <?php renderNav('rooms'); ?>
        <main>
            <section class="rooms-header">
                <div class="header-text"><h1>SESSIONS</h1></div>
                <div class="header-actions">
                    <div class="join-quick">
                        <input type="text" id="roomCodeInput" placeholder="Code salon">
                        <button onclick="joinRoomByCode()" class="btn-base">Rejoindre</button>
                    </div>
                    <?php renderButton('Créer un salon', 'button', 'active', ['onclick' => 'openCreateRoomModal()']); ?>
                </div>
            </section>

            <?php if (!empty($myRooms)): ?>
            <div class="rooms-grid">
                <?php foreach ($myRooms as $room):
                    $host = $allUsers[$room['host_id']] ?? null;
                    $room['host_name'] = $host ? $host['username'] : 'Inconnu';
                    renderRoomCard($room);
                endforeach; ?>
            </div>
            <?php else: ?>
            <div class="empty-state"><p>Aucune session active. Créez-en une ou utilisez un code d'invitation.</p></div>
            <?php endif; ?>

            <?php if (!empty($publicRooms)): ?>
            <section style="margin-top:40px;">
                <h2 style="font-size:0.7rem;font-weight:900;letter-spacing:2px;text-transform:uppercase;color:var(--text-dim);margin-bottom:20px;">Sessions publiques</h2>
                <div class="rooms-grid">
                    <?php foreach ($publicRooms as $room):
                        $host = $allUsers[$room['host_id']] ?? null;
                        $room['host_name'] = $host ? $host['username'] : 'Inconnu';
                        renderRoomCard($room);
                    endforeach; ?>
                </div>
            </section>
            <?php endif; ?>
        </main>
    </div>
    <div class="flex-spacer"></div>
    <?php renderFooter(); ?>
    <div id="createRoomModal" class="modal"></div>
</body>
</html>