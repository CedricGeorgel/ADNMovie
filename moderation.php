<?php
/**
 * MODERATION.PHP
 * Tableau de bord de modération — accessible modérateur et admin.
 */
require_once 'functions/utils.php';
require_once 'functions/auth.php';
require_once 'components/header.php';
require_once 'components/nav.php';
require_once 'components/footer.php';

require_role('moderator');

$currentUser = get_user_by_id($_SESSION['user_id']);
$isAdmin     = has_role('admin');

$censoredComments = db_fetch_all(
    "SELECT c.id, c.content, c.original_content, c.created_at,
            c.movie_id,
            u.id AS user_id, u.username, u.avatar, u.is_flagged
     FROM movie_comments c
     JOIN users u ON u.id = c.user_id
     WHERE c.is_censored = 1
     ORDER BY c.created_at DESC
     LIMIT 100"
);

// Ajoute le titre et le lien pour les commentaires sur des films (prefix M-)
require_once 'functions/notifications_logic.php';
foreach ($censoredComments as &$cc) {
    $cc['fiche_url']   = ref_id_to_url($cc['movie_id']);
    $cc['movie_label'] = $cc['movie_id'];
    if (str_starts_with($cc['movie_id'], 'M-')) {
        $row = db_fetch_one('SELECT title FROM movies WHERE tmdb_id = ?', [(int)substr($cc['movie_id'], 2)]);
        if ($row) $cc['movie_label'] = $row['title'];
    }
}
unset($cc);

$flaggedUsers = db_fetch_all(
    "SELECT id, username, avatar, role, created_at, last_login
     FROM users
     WHERE is_flagged = 1 AND is_anonymized = 0
     ORDER BY last_login DESC"
);

$signals = db_fetch_all(
    "SELECT sm.id, sm.title, sm.description, sm.created_at, sm.status,
            u.id AS reporter_id, u.username AS reporter, u.avatar AS reporter_avatar
     FROM system_management sm
     LEFT JOIN users u ON u.id = sm.user_id
     WHERE sm.type = 'signal'
     ORDER BY sm.created_at DESC
     LIMIT 100"
) ?: [];

$accessAttempts = [];
if ($isAdmin) {
    $accessAttempts = db_fetch_all(
        "SELECT aa.*, u.username FROM access_attempts aa
         LEFT JOIN users u ON aa.user_id = u.id
         ORDER BY aa.created_at DESC LIMIT 100"
    );
}

// Garantit l'existence de la room staff (INSERT IGNORE = no-op si déjà là)
db_execute(
    "INSERT IGNORE INTO rooms (id, host_id, name, status, created_at) VALUES ('STAFF-MOD', ?, 'Staff', 'active', NOW())",
    [$currentUser['id']]
);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Modération</title>
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
</head>
<body class="page-admin">
<div class="container">
    <?php renderHeader($currentUser); ?>
    <?php renderNav('none'); ?>

    <main>
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:30px;">
            <div>
                <h1 style="font-size:1.2rem; font-weight:900; margin:0;">Poste de Modération</h1>
                <p style="font-size:0.7rem; color:var(--text-dim); margin:4px 0 0;">
                    <?= has_role('admin') ? 'Accès Administrateur' : 'Accès Modérateur' ?>
                </p>
            </div>
            <?php if ($isAdmin): ?>
                <a href="admin.php" class="btn-base" style="font-size:0.7rem;">⚙️ Command Center</a>
            <?php endif; ?>
        </div>

        <!-- ── Compteurs ── -->
        <div style="display:grid; grid-template-columns:repeat(auto-fit,minmax(130px,1fr)); gap:15px; margin-bottom:35px;">
            <div class="widget stat-mini">
                <h3 style="color:#f87171;"><?= count($censoredComments) ?></h3>
                <p>Commentaires censurés</p>
            </div>
            <div class="widget stat-mini">
                <h3 style="color:#f87171;"><?= count($flaggedUsers) ?></h3>
                <p>Utilisateurs signalés</p>
            </div>
            <div class="widget stat-mini">
                <h3 style="color:#e8c07a;"><?= count(array_filter($signals, fn($s) => $s['status'] === 'pending')) ?></h3>
                <p>Signalements en attente</p>
            </div>
            <?php if ($isAdmin): ?>
            <div class="widget stat-mini">
                <h3 style="color:#f87171;"><?= count($accessAttempts) ?></h3>
                <p>Tentatives bloquées</p>
            </div>
            <?php endif; ?>
        </div>

        <!-- ── Onglets ── -->
        <div class="modo-tabs">
            <button class="modo-tab active" onclick="switchModoTab('censure', this)">Censure</button>
            <button class="modo-tab" onclick="switchModoTab('signalements', this)">
                Signalements<?php $pendingCount = count(array_filter($signals, fn($s) => $s['status'] === 'pending')); if ($pendingCount > 0): ?> <span style="background:#e8c07a;color:#050505;border-radius:10px;padding:1px 6px;font-size:0.6rem;margin-left:4px;"><?= $pendingCount ?></span><?php endif; ?>
            </button>
            <button class="modo-tab" onclick="switchModoTab('signales', this)">Signalés</button>
            <button class="modo-tab" onclick="switchModoTab('mots', this)">Mots censurés</button>
            <?php if ($isAdmin): ?>
            <button class="modo-tab" onclick="switchModoTab('acces', this)">Tentatives d'accès</button>
            <?php endif; ?>
            <button class="modo-tab" onclick="switchModoTab('chat', this)">Chat staff</button>
        </div>

        <!-- ═══════ SECTION CENSURE ═══════ -->
        <div id="section-censure" class="modo-section active">
            <div class="widget">
                <div class="widget-title">Commentaires censurés</div>
                <?php if (empty($censoredComments)): ?>
                    <div class="empty-state">Aucun commentaire censuré enregistré.</div>
                <?php else: ?>
                <div class="table-scroll">
                    <table class="modo-table">
                        <thead>
                            <tr>
                                <th>Utilisateur</th>
                                <th>Film</th>
                                <th>Contenu</th>
                                <th>Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($censoredComments as $c): ?>
                            <tr>
                                <td data-label="Utilisateur">
                                    <a href="user.php?id=<?= h($c['user_id']) ?>"
                                       style="display:flex;align-items:center;gap:8px;color:var(--pastel-blue);text-decoration:none;">
                                        <img src="<?= h($c['avatar']) ?>" style="width:28px;height:28px;border-radius:50%;object-fit:cover;" onerror="this.src='assets/default-avatar.png'">
                                        <span style="font-weight:700;"><?= h($c['username']) ?></span>
                                    </a>
                                    <?php if ($c['is_flagged']): ?>
                                        <span class="flag-badge flagged" style="margin-top:4px;display:inline-block;">Signalé</span>
                                    <?php endif; ?>
                                </td>
                                <td data-label="Film">
                                    <?php if (!empty($c['movie_id'])): ?>
                                        <a href="<?= h($c['fiche_url']) ?>"
                                           style="color:var(--text-muted);font-size:0.75rem;text-decoration:none;">
                                            <?= h($c['movie_label']) ?>
                                        </a>
                                    <?php else: ?>
                                        <span style="opacity:0.4;">—</span>
                                    <?php endif; ?>
                                </td>
                                <td data-label="Contenu" style="max-width:340px;">
                                    <div class="censored-clean"><?= h($c['content']) ?></div>
                                    <div class="censored-original">Orig : <?= h($c['original_content']) ?></div>
                                </td>
                                <td data-label="Date" style="white-space:nowrap;font-family:monospace;font-size:0.7rem;opacity:0.6;">
                                    <?= date('d/m/Y H:i', strtotime($c['created_at'])) ?>
                                </td>
                                <td data-label="Actions">
                                    <button class="unflag-btn" onclick="deleteComment(<?= (int)$c['id'] ?>, this)"
                                            title="Supprimer ce commentaire">
                                        Purger
                                    </button>
                                    <?php if (!$c['is_flagged']): ?>
                                    <button class="unflag-btn" style="margin-top:4px;display:block;"
                                            onclick="flagUser('<?= h($c['user_id']) ?>', 1, this)">
                                        Signaler user
                                    </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- ═══════ SECTION SIGNALEMENTS ═══════ -->
        <div id="section-signalements" class="modo-section">
            <div class="widget">
                <div class="widget-title">Signalements de contenu <span style="font-size:0.65rem;font-weight:400;color:var(--text-dim);">— 100 derniers</span></div>
                <?php if (empty($signals)): ?>
                    <div class="empty-state">Aucun signalement enregistré.</div>
                <?php else: ?>
                <div class="table-scroll">
                    <table class="modo-table">
                        <thead>
                            <tr>
                                <th>Signalé par</th>
                                <th>Contenu visé</th>
                                <th>Raison</th>
                                <th>Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($signals as $sig):
                            // Extraire type et ID depuis la description
                            preg_match('/Type: (\w+)/', $sig['description'] ?? '', $mType);
                            preg_match('/ID: (\d+)/',   $sig['description'] ?? '', $mId);
                            $entityType = $mType[1] ?? '';
                            $entityId   = (int)($mId[1] ?? 0);
                            $entityLinks = [
                                'movie'   => $entityId ? "fiche.php?id={$entityId}" : null,
                                'comment' => $entityId ? "fiche.php" : null,
                                'content' => null, // slug needed, pas dispo ici
                            ];
                            $entityLabels = ['movie' => '🎬 Film', 'comment' => '💬 Commentaire', 'content' => '📝 Liste/Critique'];
                            $entityLabel  = $entityLabels[$entityType] ?? $entityType;
                            $entityHref   = $entityLinks[$entityType] ?? null;
                            $isPending    = $sig['status'] === 'pending';
                        ?>
                            <tr style="<?= !$isPending ? 'opacity:0.45;' : '' ?>">
                                <td data-label="Signalé par">
                                    <a href="adn.php?id=<?= h($sig['reporter_id'] ?? '') ?>"
                                       style="display:flex;align-items:center;gap:8px;color:var(--pastel-blue);text-decoration:none;">
                                        <img src="<?= h($sig['reporter_avatar'] ?? 'assets/default-avatar.png') ?>"
                                             style="width:26px;height:26px;border-radius:50%;object-fit:cover;"
                                             onerror="this.src='assets/default-avatar.png'">
                                        <span style="font-weight:700;"><?= h($sig['reporter'] ?? '—') ?></span>
                                    </a>
                                </td>
                                <td data-label="Contenu">
                                    <?php if ($entityHref): ?>
                                        <a href="<?= $entityHref ?>" target="_blank"
                                           style="color:var(--color-amber);font-size:0.75rem;text-decoration:none;">
                                            <?= $entityLabel ?> #<?= $entityId ?>
                                        </a>
                                    <?php else: ?>
                                        <span style="font-size:0.75rem;color:var(--text-muted);"><?= $entityLabel ?> #<?= $entityId ?></span>
                                    <?php endif; ?>
                                </td>
                                <td data-label="Raison" style="font-size:0.75rem;color:var(--text-muted);max-width:220px;">
                                    <?= h(explode("\n", $sig['description'] ?? '')[0]) ?>
                                </td>
                                <td data-label="Date" style="white-space:nowrap;font-family:monospace;font-size:0.7rem;opacity:0.6;">
                                    <?= date('d/m/Y H:i', strtotime($sig['created_at'])) ?>
                                </td>
                                <td data-label="" style="display:flex;gap:6px;flex-wrap:wrap;">
                                    <?php if ($isPending): ?>
                                    <button class="unflag-btn" onclick="dismissSignal(<?= (int)$sig['id'] ?>, this)">
                                        Traité
                                    </button>
                                    <?php endif; ?>
                                    <button class="unflag-btn" style="border-color:rgba(248,113,113,0.3);color:#f87171;"
                                            onclick="deleteSignal(<?= (int)$sig['id'] ?>, this)">
                                        Purger
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- ═══════ SECTION SIGNALÉS ═══════ -->
        <div id="section-signales" class="modo-section">
            <div class="widget">
                <div class="widget-title">Utilisateurs signalés</div>
                <?php if (empty($flaggedUsers)): ?>
                    <div class="empty-state">Aucun utilisateur signalé.</div>
                <?php else: ?>
                <div class="table-scroll">
                    <table class="modo-table">
                        <thead>
                            <tr>
                                <th>Utilisateur</th>
                                <th>Rôle</th>
                                <th>Membre depuis</th>
                                <th>Dernière connexion</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($flaggedUsers as $fu): ?>
                            <?php
                                $roleClass = match($fu['role']) {
                                    'admin','superadmin' => 'role-adm',
                                    'moderator'          => 'role-mod',
                                    default              => 'role-usr',
                                };
                                $roleLabel = match($fu['role']) {
                                    'admin','superadmin' => 'Admin',
                                    'moderator'          => 'Modo',
                                    default              => 'User',
                                };
                            ?>
                            <tr>
                                <td data-label="Utilisateur">
                                    <a href="user.php?id=<?= h($fu['id']) ?>"
                                       style="display:flex;align-items:center;gap:10px;color:var(--pastel-blue);text-decoration:none;">
                                        <img src="<?= h($fu['avatar']) ?>" style="width:32px;height:32px;border-radius:50%;object-fit:cover;" onerror="this.src='assets/default-avatar.png'">
                                        <span style="font-weight:700;"><?= h($fu['username']) ?></span>
                                    </a>
                                </td>
                                <td data-label="Rôle"><span class="flag-badge <?= $roleClass ?>"><?= $roleLabel ?></span></td>
                                <td data-label="Membre depuis" style="font-family:monospace;font-size:0.7rem;opacity:0.6;"><?= date('d/m/Y', strtotime($fu['created_at'])) ?></td>
                                <td data-label="Dernière co." style="font-family:monospace;font-size:0.7rem;opacity:0.6;"><?= date('d/m/Y H:i', strtotime($fu['last_login'])) ?></td>
                                <td data-label="Actions">
                                    <button class="unflag-btn" data-user-id="<?= h($fu['id']) ?>"
                                            onclick="flagUser('<?= h($fu['id']) ?>', 0, this)">
                                        Désignaler
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- ═══════ SECTION MOTS CENSURÉS ═══════ -->
        <div id="section-mots" class="modo-section">
            <div class="widget">
                <div class="widget-title" style="display:flex;justify-content:space-between;align-items:center;">
                    Liste des mots censurés
                    <span id="word-count" style="font-size:0.65rem;color:var(--text-dim);font-weight:400;">chargement…</span>
                </div>

                <div style="display:flex;gap:10px;margin-bottom:20px;">
                    <input type="text" id="newWordInput" placeholder="Ajouter un mot ou expression…"
                           style="flex:1;padding:10px 14px;background:rgba(255,255,255,0.04);border:1px solid var(--border);border-radius:8px;color:white;font-size:0.85rem;outline:none;"
                           onkeydown="if(event.key==='Enter') addWord()">
                    <button onclick="addWord()" class="btn-base active" style="padding:10px 20px;font-size:0.75rem;">Ajouter</button>
                </div>

                <div id="wordsList" style="display:flex;flex-wrap:wrap;gap:8px;">
                    <span style="color:var(--text-dim);font-size:0.8rem;">Chargement…</span>
                </div>
            </div>
        </div>

        <!-- ═══════ SECTION TENTATIVES D'ACCÈS (admin) ═══════ -->
        <?php if ($isAdmin): ?>
        <div id="section-acces" class="modo-section">
            <div class="widget">
                <div class="widget-title">Tentatives d'accès bloquées <span style="font-size:0.65rem;font-weight:400;color:var(--text-dim);">— 100 dernières</span></div>
                <?php if (empty($accessAttempts)): ?>
                    <div class="empty-state">Aucune tentative enregistrée.</div>
                <?php else: ?>
                <div class="table-scroll">
                    <table class="modo-table">
                        <thead>
                            <tr>
                                <th>Utilisateur</th>
                                <th>Page</th>
                                <th>Rôle requis</th>
                                <th>IP</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($accessAttempts as $a): ?>
                            <tr>
                                <td data-label="Utilisateur" style="color:var(--pastel-blue);">
                                    <?= $a['username'] ? h($a['username']) : '<span style="opacity:0.4;">Inconnu</span>' ?>
                                </td>
                                <td data-label="Page" style="font-family:monospace;"><?= h($a['page']) ?></td>
                                <td data-label="Rôle requis">
                                    <span class="flag-badge role-adm"><?= h($a['required_role']) ?></span>
                                </td>
                                <td data-label="IP" style="font-family:monospace;opacity:0.5;"><?= h($a['ip'] ?? '–') ?></td>
                                <td data-label="Date" style="font-family:monospace;font-size:0.7rem;opacity:0.6;">
                                    <?= date('d/m/Y H:i', strtotime($a['created_at'])) ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- ═══════ SECTION CHAT STAFF ═══════ -->
        <div id="section-chat" class="modo-section">
            <div class="widget" style="display:flex;flex-direction:column;height:520px;">
                <div class="widget-title" style="flex-shrink:0;">Canal staff</div>
                <div id="staff-chat-log"
                     style="flex:1;overflow-y:auto;display:flex;flex-direction:column;gap:10px;padding:12px 0;min-height:0;">
                    <p style="color:var(--text-dim);font-size:0.75rem;text-align:center;margin:auto;">Chargement…</p>
                </div>
                <div style="flex-shrink:0;padding-top:12px;border-top:1px solid var(--border);">
                    <input type="hidden" id="staff-chat-reply-id" value="">
                    <div id="staff-chat-reply-indicator"
                         style="display:none;align-items:center;justify-content:space-between;
                                padding:5px 10px;background:rgba(167,199,231,0.08);
                                border:1px solid rgba(167,199,231,0.2);border-radius:8px 8px 0 0;
                                font-size:0.7rem;color:var(--pastel-blue);margin-bottom:-1px;">
                        <span id="staff-chat-reply-label" style="opacity:0.8;"></span>
                        <button type="button" onclick="staffChatCancelReply()"
                                style="background:none;border:none;color:var(--text-dim);font-size:0.9rem;cursor:pointer;line-height:1;">✕</button>
                    </div>
                    <div style="display:flex;gap:8px;">
                        <input id="staff-chat-input" type="text" maxlength="1000"
                               placeholder="Message…"
                               style="flex:1;padding:10px 14px;background:rgba(255,255,255,0.04);border:1px solid var(--border);
                                      border-radius:8px;color:var(--text-main);font-size:0.82rem;outline:none;"
                               onkeydown="if(event.key==='Enter'&&!event.shiftKey){event.preventDefault();staffChatSend();}">
                        <button onclick="staffChatSend()"
                                style="padding:10px 18px;background:var(--pastel-blue);color:#050505;border:none;
                                       border-radius:8px;font-weight:bold;font-size:0.75rem;cursor:pointer;flex-shrink:0;">
                            Envoyer
                        </button>
                    </div>
                </div>
            </div>
        </div>

    </main>
</div>

<div class="flex-spacer"></div>
<?php renderFooter(); ?>

<script>
const _staffCurrentUserId = '<?= h($_SESSION['user_id']) ?>';
</script>
<script src="/assets/js/moderation.js?v=1" defer></script>
<script src="assets/js/ui.js" defer></script>
</body>
</html>
