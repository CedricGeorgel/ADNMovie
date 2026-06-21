<?php
require_once 'functions/utils.php';
require_once 'functions/auth.php';
require_once 'functions/friends_logic.php';
require_once 'components/header.php';
require_once 'components/nav.php';
require_once 'components/footer.php';
require_once 'components/avatar.php';

check_auth();

$currentUserId = $_SESSION['user_id'];
$currentUser   = get_user_by_id($currentUserId);

$activeWith = trim($_GET['with'] ?? '');
$activeUser = null;

if ($activeWith && $activeWith !== $currentUserId) {
    // Vérifie que l'on est bien amis
    $status = get_friendship_status($currentUserId, $activeWith);
    if ($status === 'accepted') {
        $activeUser = get_user_by_id($activeWith);
    }
    if (!$activeUser) {
        // Pas ami ou utilisateur inexistant → on ignore le paramètre
        $activeWith = '';
    }
}

$conversations = get_dm_conversations($currentUserId);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Messages — ADN Movie</title>
    <link rel="icon" href="/assets/Icons/Logo2.ico" type="image/x-icon">
    <link rel="apple-touch-icon" href="/assets/Icons/Logo3.png">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="assets/style.css?v=<?= filemtime('assets/style.css') ?>">
    <meta name="theme-color" content="#050505">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
</head>
<body>
    <div class="container">
        <?php renderHeader($currentUser); ?>
        <?php renderNav('chat'); ?>

        <main style="padding: 20px 0 40px;">
            <h2 style="font-size:0.75rem;font-weight:800;letter-spacing:2px;text-transform:uppercase;color:var(--text-dim);margin-bottom:16px;">Messages</h2>

            <div class="chat-layout" id="chatLayout">

                <!-- ── Colonne gauche : sidebar des conversations ── -->
                <div class="chat-sidebar" id="chatSidebar">
                    <?php if (empty($conversations)): ?>
                        <div style="padding:32px 20px;text-align:center;color:var(--text-dim);font-size:0.8rem;">
                            Aucune conversation pour le moment.
                        </div>
                    <?php else: ?>
                        <?php foreach ($conversations as $conv): ?>
                            <a href="chat.php?with=<?= h($conv['id']) ?>"
                               class="chat-sidebar-item <?= $activeWith === $conv['id'] ? 'active' : '' ?>">
                                <img src="<?= h($conv['avatar'] ?? 'assets/default-avatar.png') ?>"
                                     onerror="this.src='assets/default-avatar.png'"
                                     style="width:40px;height:40px;border-radius:50%;object-fit:cover;flex-shrink:0;">
                                <div style="flex:1;min-width:0;">
                                    <div style="font-size:0.82rem;font-weight:700;margin-bottom:2px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                                        <?= h($conv['username']) ?>
                                    </div>
                                    <div class="dm-preview"><?= h($conv['last_message'] ?? '') ?></div>
                                </div>
                                <?php if ($conv['unread_count'] > 0): ?>
                                    <span class="chat-unread-badge"><?= (int)$conv['unread_count'] ?></span>
                                <?php endif; ?>
                            </a>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <!-- ── Colonne droite : fil de conversation ── -->
                <div class="chat-thread" id="chatThread">
                    <?php if ($activeUser): ?>

                        <!-- Bouton retour mobile -->
                        <a href="chat.php" class="chat-back-btn" id="chatBackBtn" style="display:none;">← Retour</a>

                        <!-- En-tête de thread -->
                        <div class="chat-thread-header">
                            <?php renderAvatar($activeUser, $activeWith); ?>
                            <div>
                                <div style="font-size:0.9rem;font-weight:700;"><?= h($activeUser['username']) ?></div>
                                <a href="adn.php?id=<?= h($activeWith) ?>" style="font-size:0.65rem;color:var(--text-dim);">Voir le profil</a>
                            </div>
                        </div>

                        <!-- Zone des messages -->
                        <div class="dm-messages" id="dmMessages">
                            <!-- Les messages sont chargés via JS -->
                            <div id="dmLoadingState" style="text-align:center;color:var(--text-dim);font-size:0.75rem;padding:20px;">Chargement…</div>
                        </div>

                        <!-- Zone de saisie -->
                        <div class="dm-input-area" style="flex-direction:column;gap:0;">
                            <input type="hidden" id="dmReplyToId" value="">
                            <div id="dmReplyIndicator"
                                 style="display:none;align-items:center;justify-content:space-between;
                                        padding:5px 10px;background:rgba(167,199,231,0.08);
                                        border:1px solid rgba(167,199,231,0.2);border-radius:8px 8px 0 0;
                                        font-size:0.7rem;color:var(--pastel-blue);margin-bottom:-1px;">
                                <span id="dmReplyLabel" style="opacity:0.8;"></span>
                                <button type="button" onclick="cancelDmReply()"
                                        style="background:none;border:none;color:var(--text-dim);font-size:0.9rem;cursor:pointer;line-height:1;">✕</button>
                            </div>
                            <div style="display:flex;gap:8px;width:100%;">
                                <input type="text" id="dmInput" placeholder="Écrire un message…" maxlength="2000" autocomplete="off" style="flex:1;">
                                <button onclick="sendDm()" class="btn-base active" style="padding:10px 18px;font-size:0.8rem;border-radius:12px;flex-shrink:0;">Envoyer</button>
                            </div>
                        </div>

                    <?php else: ?>

                        <!-- État vide -->
                        <div style="flex:1;display:flex;align-items:center;justify-content:center;flex-direction:column;gap:12px;color:var(--text-dim);text-align:center;padding:40px;">
                            <div style="font-size:2rem;opacity:0.3;">💬</div>
                            <div style="font-size:0.85rem;font-weight:600;">Sélectionnez une conversation</div>
                            <div style="font-size:0.75rem;">Choisissez un ami dans la liste pour commencer à écrire.</div>
                        </div>

                    <?php endif; ?>
                </div>

            </div>
        </main>
    </div>

    <div class="flex-spacer"></div>
    <?php renderFooter(); ?>

    <?php if ($activeUser): ?>
    <script>
        const CHAT_WITH  = <?= json_encode($activeWith) ?>;
        const CHAT_MY_ID = <?= json_encode($currentUserId) ?>;
    </script>
    <script src="assets/js/ui.js?v=<?= filemtime('assets/js/ui.js') ?>" defer></script>
    <script src="assets/js/chat_dm.js?v=<?= filemtime('assets/js/chat_dm.js') ?>" defer></script>
    <?php endif; ?>

    <!-- Mobile : gérer sidebar/thread -->
    <script>
    (function() {
        var layout    = document.getElementById('chatLayout');
        var sidebar   = document.getElementById('chatSidebar');
        var thread    = document.getElementById('chatThread');
        var backBtn   = document.getElementById('chatBackBtn');

        function applyMobileLayout() {
            if (window.innerWidth <= 700) {
                var hasActive = <?= $activeUser ? 'true' : 'false' ?>;
                if (hasActive) {
                    sidebar.style.display = 'none';
                    if (backBtn) backBtn.style.display = 'block';
                } else {
                    sidebar.style.display = '';
                }
            } else {
                sidebar.style.display = '';
                if (backBtn) backBtn.style.display = 'none';
            }
        }

        applyMobileLayout();
        window.addEventListener('resize', applyMobileLayout);
    })();
    </script>
</body>
</html>
