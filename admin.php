<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

require_once 'functions/utils.php'; 
require_once 'config/settings.php';
require_once 'functions/core_db.php';
require_once 'functions/badges_logic.php';
require_once 'components/header.php';
require_once 'components/nav.php';
require_once 'components/footer.php';
require_once 'components/movie-card.php';
require_once 'components/roadmap-widget.php';
require_once 'components/management-card.php';

require_role('admin');

$currentUser  = get_user_by_id($_SESSION['user_id']);
$bannerConfig = get_site_banner_config();

$cronLogPath = 'config/cron_status.json';
$cronHistory = [];
$lastCronHuman = "Jamais";
if (file_exists($cronLogPath)) {
    $cronHistory = json_decode(file_get_contents($cronLogPath), true) ?: [];
    if (!empty($cronHistory)) {
        $lastRun = $cronHistory[0]['last_run'];
        $lastTs  = strtotime($lastRun);
        $lastCronHuman = (date('Y-m-d') === date('Y-m-d', $lastTs)) ? date('H:i', $lastTs) : date('d/m', $lastTs);
    }
}

$userCount    = (int)db_fetch_one('SELECT COUNT(*) AS cnt FROM users WHERE is_anonymized = 0 AND id != ?', [ORACLE_USER_ID ?? 'IA-ORACLE-001'])['cnt'];
$totalMovies  = (int)db_fetch_one('SELECT COUNT(*) AS cnt FROM movies')['cnt'];
$totalSeries  = (int)db_fetch_one('SELECT COUNT(*) AS cnt FROM series')['cnt'];
$ratings7j    = (int)db_fetch_one('SELECT COUNT(*) AS cnt FROM ratings WHERE rated_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) AND user_id != ?', ['IA-ORACLE-001'])['cnt']
              + (int)db_fetch_one('SELECT COUNT(*) AS cnt FROM series_ratings WHERE rated_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) AND user_id != ?', ['IA-ORACLE-001'])['cnt'];
$newUsersThisWeek = (int)db_fetch_one(
    "SELECT COUNT(*) AS cnt FROM users
     WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY)
       AND is_anonymized = 0 AND id != 'IA-ORACLE-001'"
)['cnt'];
$newUsersLastWeek = (int)db_fetch_one(
    "SELECT COUNT(*) AS cnt FROM users
     WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) + 7 DAY)
       AND created_at  <  DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY)
       AND is_anonymized = 0 AND id != 'IA-ORACLE-001'"
)['cnt'];
if ($newUsersLastWeek > 0) {
    $newUsersEvol = round(($newUsersThisWeek - $newUsersLastWeek) / $newUsersLastWeek * 100);
    $newUsersEvolStr = ($newUsersEvol >= 0 ? '+' : '') . $newUsersEvol . '%';
    $newUsersEvolColor = $newUsersEvol >= 0 ? '#9dffb0' : '#FA6B6B';
} elseif ($newUsersThisWeek > 0) {
    $newUsersEvolStr  = 'nouveau';
    $newUsersEvolColor = '#9dffb0';
} else {
    $newUsersEvolStr  = '—';
    $newUsersEvolColor = 'var(--text-dim)';
}

$topUsers = db_fetch_all('SELECT u.id, u.username, u.avatar, COUNT(r.movie_id) AS vote_count FROM users u JOIN ratings r ON r.user_id = u.id WHERE r.scores IS NOT NULL GROUP BY u.id ORDER BY vote_count DESC LIMIT 5');
$topMovies = db_fetch_all('SELECT m.tmdb_id, m.title, m.poster, m.year, m.total_votes FROM movies m ORDER BY m.total_votes DESC LIMIT 3');

$badgeLibrary = get_badge_library();
$allUsers = db_fetch_all('SELECT id, username, avatar, role, is_flagged FROM users WHERE is_anonymized = 0');
$activityRows = db_fetch_all('SELECT user_id, COUNT(*) AS cnt FROM ratings WHERE scores IS NOT NULL GROUP BY user_id');
$accessAttempts = db_fetch_all(
    "SELECT aa.*, u.username FROM access_attempts aa
     LEFT JOIN users u ON aa.user_id = u.id
     ORDER BY aa.created_at DESC LIMIT 50"
);
$userActivity = array_column($activityRows, 'cnt', 'user_id');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Command Center </title>
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
        <?php renderNav('admin'); ?>
        <main class="dash-container">

            <div class="stats-banner responsive-stats">
                <div class="widget stat-mini cron-active" style="cursor:pointer;" onclick="openCronModal()">
                    <h3 style="color:var(--pastel-blue);"><?= $lastCronHuman ?></h3>
                    <p>Cron Master</p>
                </div>
                <div class="widget stat-mini" style="cursor:pointer;" onclick="openCitoyensModal()"><h3><?= $userCount ?></h3><p>Citoyens</p></div>
                <div class="widget stat-mini"><h3><?= $totalMovies ?></h3><p>Films</p></div>
                <div class="widget stat-mini"><h3><?= $totalSeries ?></h3><p>Séries</p></div>
                <div class="widget stat-mini"><h3><?= $ratings7j ?></h3><p>Ratings 7j</p></div>
                <div class="widget stat-mini"><h3><?= $newUsersThisWeek ?> <span style="font-size:0.6rem;color:<?= $newUsersEvolColor ?>"><?= $newUsersEvolStr ?></span></h3><p>Nouveaux (sem.)</p></div>
            </div>

            <div class="main-column">
                <div class="widget">
                    <div class="widget-title">Accréditations & Protocoles</div>
                    <div class="admin-search-box" style="margin-bottom:20px;">
                        <div class="autocomplete-wrapper">
                            <input type="text" id="userSearchDisplay" class="admin-select" placeholder="Pseudo citoyen..." autocomplete="off">
                            <input type="hidden" id="targetUser" value="">
                            <div id="autocompleteResults" class="autocomplete-dropdown"></div>
                        </div>
                    </div>
                    <div id="userDetailsPanel" class="user-details-panel" style="display:none;">
                        <div class="panel-header" style="display:flex;align-items:center;gap:15px;margin-bottom:15px;">
                            <img id="panelAvatar" src="" class="panel-avatar" style="width:60px;height:60px;border-radius:50%;object-fit:cover;">
                            <div class="panel-info"><h2 id="panelUsername" style="margin:0;font-size:1.2rem;">---</h2><p id="panelId" class="panel-id-tag" style="margin:0;font-size:0.8rem;color:var(--text-dim);">ID: ---</p></div>
                        </div>
                        <div class="panel-stats-grid" style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:20px;">
                            <div class="p-stat" style="background:var(--card-bg);padding:10px;border-radius:8px;text-align:center;border:1px solid var(--border);"><span id="panelVoteCount" class="p-val" style="display:block;font-size:1.5rem;font-weight:900;">0</span><span class="p-lab" style="font-size:0.7rem;color:var(--text-dim);text-transform:uppercase;">Votes ADN</span></div>
                            <div class="p-stat" style="background:var(--card-bg);padding:10px;border-radius:8px;text-align:center;border:1px solid var(--border);"><span id="panelSeenCount" class="p-val" style="display:block;font-size:1.5rem;font-weight:900;">0</span><span class="p-lab" style="font-size:0.7rem;color:var(--text-dim);text-transform:uppercase;">Films Vus</span></div>
                        </div>
                        <div id="panelBadgesList" class="p-badges-flex" style="display:flex;flex-wrap:wrap;gap:8px;margin-bottom:20px;"></div>
                        <div class="panel-actions-box" style="border-top:1px solid var(--border);padding-top:15px;">
                            <select id="targetBadge" class="admin-select" style="width:100%;margin-bottom:15px;"><?php foreach ($badgeLibrary as $key => $badge): ?><option value="<?= h($key) ?>"><?= h($badge['label']) ?></option><?php endforeach; ?></select>
                            <div class="admin-btns-row" style="display:flex;gap:10px;"><button id="btnAward" class="btn-base active" style="flex:2;">Octroyer</button><button id="btnRevoke" class="btn-base btn-danger-soft" style="flex:1;">Révoquer</button></div>
                        </div>

                        <div class="panel-actions-box" style="border-top:1px solid var(--border);padding-top:15px;margin-top:15px;">
                            <p style="font-size:0.65rem;text-transform:uppercase;letter-spacing:1px;color:var(--text-dim);margin-bottom:10px;font-weight:800;">Accréditation</p>
                            <div class="admin-btns-row" style="display:flex;gap:10px;align-items:center;">
                                <select id="targetRole" class="admin-select" style="flex:1;margin:0;">
                                    <option value="user">Spécimen (user)</option>
                                    <option value="moderator">Modérateur</option>
                                    <option value="admin">Administrateur</option>
                                </select>
                                <button id="btnSetRole" class="btn-base" style="flex:1;">Définir</button>
                            </div>
                            <button id="btnFlagUser" class="btn-base btn-danger-soft" style="width:100%;margin-top:10px;">🚩 Signaler</button>
                        </div>
                    </div>
                </div>

                <div class="admin-bento">

                    <div class="widget">
                        <div class="widget-title">Bandeau d'annonce</div>
                        <?php if (isset($_GET['banner_saved'])): ?>
                        <p style="color:#9dffb0;font-size:0.75rem;margin-bottom:12px;">✓ Bandeau sauvegardé.</p>
                        <?php endif; ?>
                        <form method="POST" action="api/api_banner_save.php">
                            <div class="form-group" style="margin-bottom:14px;">
                                <textarea name="message" rows="3" class="admin-select" placeholder="Message du bandeau…" style="width:100%;flex:1;resize:none;"><?= htmlspecialchars($bannerConfig['message'], ENT_QUOTES) ?></textarea>
                            </div>
                            <div class="form-group" style="margin-bottom:14px;">
                                <p style="font-size:0.65rem;text-transform:uppercase;letter-spacing:1px;color:var(--text-dim);margin-bottom:8px;font-weight:800;">Couleur</p>
                                <div style="display:flex;gap:6px;flex-wrap:wrap;">
                                    <?php foreach (['blue','green','red','yellow','orange','purple'] as $c): ?>
                                    <label style="display:flex;align-items:center;gap:4px;cursor:pointer;font-size:0.7rem;">
                                        <input type="radio" name="color" value="<?= $c ?>" <?= $bannerConfig['color'] === $c ? 'checked' : '' ?>>
                                        <span class="site-banner site-banner--<?= $c ?>" style="padding:2px 8px;border-radius:4px;font-size:0.6rem;"><?= ucfirst($c) ?></span>
                                    </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <div class="form-group" style="margin-bottom:14px;display:flex;align-items:center;gap:8px;">
                                <input type="checkbox" name="is_active" id="bannerActive" value="1" <?= $bannerConfig['is_active'] ? 'checked' : '' ?>>
                                <label for="bannerActive" style="font-size:0.75rem;cursor:pointer;">Activer le bandeau</label>
                            </div>
                            <button type="submit" class="btn-base active" style="width:100%;margin-top:auto;">Sauvegarder</button>
                        </form>
                    </div>

                    <div class="widget">
                        <div class="widget-title">Notification push</div>
                        <div id="push-broadcast-msg" style="display:none;font-size:0.75rem;margin-bottom:12px;"></div>
                        <div class="admin-bento-inputs">
                            <input id="pb-title" type="text" class="admin-select" placeholder="Titre…" maxlength="80" style="width:100%;">
                            <textarea id="pb-body" class="admin-select" placeholder="Message…" maxlength="200" style="width:100%;flex:1;resize:none;"></textarea>
                            <button class="btn-base active" style="width:100%;margin-top:auto;" onclick="pushBroadcast()">Envoyer à tous</button>
                        </div>
                    </div>

                    <div class="widget">
                        <div class="widget-title">Import catalogue TMDB</div>
                        <p style="font-size:0.75rem;color:var(--text-dim);margin-bottom:14px;">
                            Import complet (synopsis, casting, poster, Oracle ADN + note) — identique à une visite organique.<br>
                            Films filtrés sur les sorties France · Séries filtrées par vote_count.
                        </p>

                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:14px;">
                            <div style="background:var(--card-bg);border:1px solid var(--border);border-radius:8px;padding:10px;">
                                <div style="font-size:0.65rem;text-transform:uppercase;letter-spacing:1px;color:var(--text-dim);font-weight:800;margin-bottom:6px;">Films</div>
                                <div id="importMovieCount" style="font-size:1.4rem;font-weight:900;color:var(--pastel-blue);">—</div>
                                <div id="importMoviePhase" style="font-size:0.65rem;color:var(--text-dim);margin-top:2px;">En attente</div>
                                <div style="margin-top:8px;height:4px;background:rgba(255,255,255,0.08);border-radius:2px;overflow:hidden;">
                                    <div id="importMovieBar" style="height:100%;width:0%;background:var(--pastel-blue);transition:width 0.3s;border-radius:2px;"></div>
                                </div>
                            </div>
                            <div style="background:var(--card-bg);border:1px solid var(--border);border-radius:8px;padding:10px;">
                                <div style="font-size:0.65rem;text-transform:uppercase;letter-spacing:1px;color:var(--text-dim);font-weight:800;margin-bottom:6px;">Séries</div>
                                <div id="importSerieCount" style="font-size:1.4rem;font-weight:900;color:#c4b5fd;">—</div>
                                <div id="importSeriePhase" style="font-size:0.65rem;color:var(--text-dim);margin-top:2px;">En attente</div>
                                <div style="margin-top:8px;height:4px;background:rgba(255,255,255,0.08);border-radius:2px;overflow:hidden;">
                                    <div id="importSerieBar" style="height:100%;width:0%;background:#c4b5fd;transition:width 0.3s;border-radius:2px;"></div>
                                </div>
                            </div>
                        </div>

                        <div id="importLog" style="display:none;font-size:0.7rem;font-family:monospace;padding:8px 10px;background:var(--card-bg);border:1px solid var(--border);border-radius:6px;margin-bottom:12px;max-height:60px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"></div>

                        <div style="display:flex;gap:8px;">
                            <button id="btnImportMovies" class="btn-base active" style="flex:1;" onclick="runTmdbImport('movie', this)">
                                🎬 Importer Films
                            </button>
                            <button id="btnImportSeries" class="btn-base" style="flex:1;background:rgba(196,181,253,0.15);border-color:rgba(196,181,253,0.3);color:#c4b5fd;" onclick="runTmdbImport('tv', this)">
                                📺 Importer Séries
                            </button>
                        </div>
                    </div>

                    <div class="widget">
                        <div class="widget-title">Oracle — Sync appréciations TMDB</div>
                        <p style="font-size:0.75rem;color:var(--text-dim);margin-bottom:14px;">
                            Recalcule le <code style="font-size:0.7rem;">like_score</code> Oracle sur tous les films depuis les votes TMDB (50&nbsp;%&nbsp;=&nbsp;0 chez nous).<br>
                            Ne touche <strong>pas</strong> aux scores ADN existants.
                        </p>
                        <div id="oracleSyncLog" style="display:none;font-size:0.72rem;font-family:monospace;padding:8px 10px;background:var(--card-bg);border:1px solid var(--border);border-radius:6px;margin-bottom:12px;"></div>
                        <button id="btnOracleSync" class="btn-base active" style="width:100%;" onclick="runOracleSync(this)">
                            ⚡ Lancer Oracle (toute la base)
                        </button>
                    </div>

                </div>
            </div>

            <aside class="sidebar-column" style="display:flex;flex-direction:column;">
                <div class="widget" style="flex:1;">
                    <div class="widget-title">Top Contributeurs</div>
                    <div class="user-ranking" style="display:flex;flex-direction:column;gap:10px;">
                        <?php foreach ($topUsers as $i => $u): ?>
                        <div class="user-rank-item" style="cursor:pointer;display:flex;align-items:center;gap:10px;padding:10px;background:var(--card-bg);border:1px solid var(--border);border-radius:8px;min-width:0;" onclick="selectUserDirect('<?= $u['id'] ?>', '<?= h($u['username']) ?>')">
                            <img src="<?= h($u['avatar']) ?>" style="width:36px;height:36px;border-radius:50%;object-fit:cover;flex-shrink:0;" onerror="this.src='assets/default-avatar.png';">
                            <div style="display:flex;flex-direction:column;min-width:0;overflow:hidden;">
                                <span style="font-weight:bold;font-size:0.85rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?= h($u['username']) ?></span>
                                <span style="font-size:0.7rem;color:var(--text-dim);"><?= $u['vote_count'] ?> analyses</span>
                            </div>
                        </div><?php endforeach; ?>
                    </div>
                </div>
            </aside>
        </main>

        <section style="margin-top:40px;">
            <div class="widget">
                <div class="widget-title" style="display:flex;justify-content:space-between;align-items:center;">
                    Tentatives d'accès bloquées
                    <span style="font-size:0.65rem;color:var(--text-dim);font-weight:400;">50 dernières</span>
                </div>
                <?php if (empty($accessAttempts)): ?>
                    <p style="color:var(--text-dim);font-size:0.8rem;padding:10px 0;">Aucune tentative enregistrée.</p>
                <?php else: ?>
                <div style="overflow-x:auto;">
                    <table style="width:100%;border-collapse:collapse;font-size:0.75rem;">
                        <thead>
                            <tr style="border-bottom:1px solid var(--border);color:var(--text-dim);text-transform:uppercase;letter-spacing:1px;font-size:0.6rem;">
                                <th style="text-align:left;padding:8px 6px;">Utilisateur</th>
                                <th style="text-align:left;padding:8px 6px;">Page</th>
                                <th style="text-align:left;padding:8px 6px;">Rôle requis</th>
                                <th style="text-align:left;padding:8px 6px;">IP</th>
                                <th style="text-align:left;padding:8px 6px;">Date</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($accessAttempts as $a): ?>
                            <tr style="border-bottom:1px solid rgba(255,255,255,0.04);">
                                <td style="padding:8px 6px;color:var(--pastel-blue);">
                                    <?= $a['username'] ? h($a['username']) : '<span style="opacity:0.4;">Inconnu</span>' ?>
                                </td>
                                <td style="padding:8px 6px;font-family:monospace;"><?= h($a['page']) ?></td>
                                <td style="padding:8px 6px;">
                                    <span style="background:rgba(248,113,113,0.1);color:#f87171;border:1px solid rgba(248,113,113,0.25);padding:2px 7px;border-radius:4px;font-size:0.65rem;font-weight:700;">
                                        <?= h($a['required_role']) ?>
                                    </span>
                                </td>
                                <td style="padding:8px 6px;font-family:monospace;opacity:0.5;"><?= h($a['ip'] ?? '–') ?></td>
                                <td style="padding:8px 6px;font-family:monospace;opacity:0.6;"><?= date('d/m H:i', strtotime($a['created_at'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </section>

        <section class="admin-management-section" style="margin-top:40px;">
            <?php $activeTab = $_GET['tab'] ?? 'roadmap'; ?>
            <div class="tabs-header" style="display:flex;gap:15px;border-bottom:1px solid var(--border);margin-bottom:20px;padding-bottom:10px;">
                <button class="tab-btn <?= $activeTab === 'roadmap' ? 'active' : '' ?>" onclick="switchTab('roadmap', this)" style="background:none;border:none;font-weight:800;cursor:pointer;font-size:0.7rem;color:<?= $activeTab === 'roadmap' ? 'var(--pastel-blue)' : 'var(--text-dim)' ?>;">ROADMAP</button>
                <button class="tab-btn <?= $activeTab === 'bug' ? 'active' : '' ?>" onclick="switchTab('bug', this)" style="background:none;border:none;font-weight:800;cursor:pointer;font-size:0.7rem;color:<?= $activeTab === 'bug' ? 'var(--pastel-blue)' : 'var(--text-dim)' ?>;">BUGS</button>
                <button class="tab-btn <?= $activeTab === 'feature' ? 'active' : '' ?>" onclick="switchTab('feature', this)" style="background:none;border:none;font-weight:800;cursor:pointer;font-size:0.7rem;color:<?= $activeTab === 'feature' ? 'var(--pastel-blue)' : 'var(--text-dim)' ?>;">FEATURES</button>
                <button class="tab-btn <?= $activeTab === 'all' ? 'active' : '' ?>" onclick="switchTab('all', this)" style="background:none;border:none;font-weight:800;cursor:pointer;font-size:0.7rem;color:<?= $activeTab === 'all' ? 'var(--pastel-blue)' : 'var(--text-dim)' ?>;">TOUT</button>
            </div>
            <div id="management-container"><?php renderRoadmap($activeTab, true); ?></div>
        </section>

        <div id="cronModal" class="modal">
            <div class="modal-content" style="max-width:540px; width:100%;">
                <button class="modal-close" onclick="closeModal('cronModal')">&times;</button>
                <h3 style="margin-bottom:3px;">LOGS DE MAINTENANCE</h3>
                <p style="font-size:0.6rem; color:var(--text-dim); margin-bottom:16px;">HISTORIQUE DES 50 DERNIERS PASSAGES</p>
                <button onclick="runCronNow(this)" class="btn-base active" style="width:100%;margin-bottom:20px;">⚡ Lancer la cron maintenant</button>
                <p id="cronRunResult" style="font-size:0.72rem;text-align:center;margin:-12px 0 16px;display:none;"></p>
                <div style="max-height:65vh; overflow-y:auto; padding-right:2px;">
                    <?php if (empty($cronHistory)): ?>
                        <p style="padding:20px; text-align:center; color:var(--text-dim);">Aucun log disponible.</p>
                    <?php else: foreach ($cronHistory as $idx => $log):
                        $ts       = strtotime($log['last_run']);
                        $dateStr  = date('d/m/Y à H:i:s', $ts);
                        $mode     = $log['execution_mode'] ?? 'unknown';
                        $isCli    = ($mode === 'cli');
                        $triggerLabel = $isCli ? 'Automatique (CRON)' : 'Manuel (Modo)';
                        $triggerClass = $isCli ? 'auto' : 'manual';
                        $tasks    = $log['tasks'] ?? [];
                        $errors   = $log['errors'] ?? [];
                        $hasErrors = !empty($errors);

                        // helpers
                        $val = fn($v) => ($v === null) ? '–' : (string)$v;
                        $cls = function($v) {
                            if ($v === null) return 'zero';
                            if ($v === 0)    return 'zero';
                            return 'ok';
                        };

                        $oracleFilms = $tasks['oracle_films_cleaned'] ?? null;
                        $oracleCount = is_array($oracleFilms) ? count($oracleFilms) : ($oracleFilms === null ? null : 0);
                    ?>
                    <div class="cron-log-entry <?= $idx === 0 ? 'open' : '' ?>">
                        <div class="cron-log-header" onclick="this.parentElement.classList.toggle('open')">
                            <span class="cron-date"><?= $dateStr ?></span>
                            <span class="cron-trigger-badge <?= $triggerClass ?>"><?= $triggerLabel ?></span>
                            <?php if ($hasErrors): ?><span class="cron-errors-flag">⚠ <?= count($errors) ?> erreur<?= count($errors) > 1 ? 's' : '' ?></span><?php endif; ?>
                            <span class="cron-chevron">▼</span>
                        </div>
                        <div class="cron-log-body">

                            <div class="cron-task-row">
                                <span class="cron-task-label">🗓 Événements supprimés</span>
                                <span class="cron-task-value <?= $cls($tasks['room_events_deleted'] ?? null) ?>">
                                    <?= $val($tasks['room_events_deleted'] ?? null) ?>
                                </span>
                            </div>

                            <div class="cron-task-row">
                                <span class="cron-task-label">🚪 Sessions purgées</span>
                                <span class="cron-task-value <?= $cls($tasks['sessions_deleted'] ?? null) ?>">
                                    <?= $val($tasks['sessions_deleted'] ?? null) ?>
                                </span>
                            </div>

                            <div class="cron-task-row">
                                <span class="cron-task-label">🍎 Fichiers .DS_Store</span>
                                <span class="cron-task-value <?= $cls($tasks['ds_store_deleted'] ?? null) ?>">
                                    <?= $val($tasks['ds_store_deleted'] ?? null) ?>
                                </span>
                            </div>

                            <div class="cron-task-row">
                                <span class="cron-task-label">🎬 Films TMDB mis à jour</span>
                                <span class="cron-task-value <?= $cls($tasks['tmdb_films_inserted'] ?? null) ?>">
                                    <?= $val($tasks['tmdb_films_inserted'] ?? null) ?>
                                </span>
                            </div>

                            <div class="cron-task-row" style="flex-direction:column;align-items:flex-start;gap:6px;">
                                <div style="display:flex;justify-content:space-between;width:100%;">
                                    <span class="cron-task-label">🤖 Oracle retiré</span>
                                    <span class="cron-task-value <?= $cls($oracleCount) ?>">
                                        <?= $oracleCount === null ? '–' : $oracleCount . ' film' . ($oracleCount > 1 ? 's' : '') ?>
                                    </span>
                                </div>
                                <?php if (!empty($oracleFilms)): ?>
                                <div class="cron-oracle-list">
                                    <?php foreach ($oracleFilms as $film): ?>
                                    <div class="cron-oracle-film">
                                        <span><?= h($film['title']) ?> <span style="opacity:0.4;">#<?= $film['id'] ?></span></span>
                                        <span class="votes"><?= $film['human_votes'] ?> votes</span>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                                <?php endif; ?>
                            </div>

                            <div class="cron-task-row">
                                <span class="cron-task-label">🧬 Snapshots ADN</span>
                                <span class="cron-task-value <?= $cls($tasks['dna_snapshots_taken'] ?? null) ?>">
                                    <?= $val($tasks['dna_snapshots_taken'] ?? null) ?>
                                </span>
                            </div>

                            <div class="cron-task-row">
                                <span class="cron-task-label">📡 Providers refreshés</span>
                                <span class="cron-task-value <?= $cls($tasks['providers_refreshed'] ?? null) ?>">
                                    <?= $val($tasks['providers_refreshed'] ?? null) ?>
                                </span>
                            </div>

                            <div class="cron-task-row">
                                <span class="cron-task-label">🔔 Notifs purgées</span>
                                <span class="cron-task-value <?= $cls($tasks['notifications_purged'] ?? null) ?>">
                                    <?= $val($tasks['notifications_purged'] ?? null) ?>
                                </span>
                            </div>

                            <div class="cron-task-row">
                                <span class="cron-task-label">🛡 Tentatives accès purgées</span>
                                <span class="cron-task-value <?= $cls($tasks['access_attempts_purged'] ?? null) ?>">
                                    <?= $val($tasks['access_attempts_purged'] ?? null) ?>
                                </span>
                            </div>

                            <div class="cron-task-row">
                                <span class="cron-task-label">📺 Épisodes notifiés</span>
                                <span class="cron-task-value <?= $cls($tasks['new_episodes_notified'] ?? null) ?>">
                                    <?= $val($tasks['new_episodes_notified'] ?? null) ?>
                                </span>
                            </div>

                            <div class="cron-task-row">
                                <span class="cron-task-label">🎞 Watchlist × providers</span>
                                <span class="cron-task-value <?= $cls($tasks['watchlist_notified'] ?? null) ?>">
                                    <?= $val($tasks['watchlist_notified'] ?? null) ?>
                                </span>
                            </div>

                            <?php if ($hasErrors): ?>
                            <div class="cron-errors-block">
                                <?php foreach ($errors as $err): ?>
                                <p>⚠ <?= h($err) ?></p>
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>

                        </div>
                    </div>
                    <?php endforeach; endif; ?>
                </div>
            </div>
        </div>

        <div id="citoyensModal" class="modal">
            <div class="modal-content" style="max-width:600px; width:100%;">
                <button class="modal-close" onclick="closeModal('citoyensModal')">&times;</button>
                <h3 style="margin-bottom:20px;">CITOYENS ENREGISTRÉS</h3>
                <input type="text" id="citoyensFilter" placeholder="Filtrer..." oninput="filterCitoyens(this.value)" class="admin-select">
                <ul id="citoyensList" style="list-style:none; padding:0; display:flex; flex-direction:column; gap:8px;"></ul>
            </div>
        </div>

    </div>

    <script>
    function openCronModal() { openModal('cronModal'); }

    async function runCronNow(btn) {
        btn.disabled = true;
        btn.textContent = '⏳ Exécution…';
        const el = document.getElementById('cronRunResult');
        el.style.display = 'none';
        try {
            const r   = await fetch('api/api_run_cron.php', { method: 'POST' });
            const res = await r.json();
            el.textContent = res.success ? '✓ Cron exécutée — ' + (res.message || 'OK') : '✗ ' + (res.message || 'Erreur');
            el.style.color = res.success ? '#9dffb0' : '#FA6B6B';
        } catch(e) {
            el.textContent = '✗ Erreur réseau';
            el.style.color = '#FA6B6B';
        }
        el.style.display = '';
        btn.disabled = false;
        btn.textContent = '⚡ Lancer la cron maintenant';
    }
    function openCitoyensModal() { renderCitoyensList(window.MOOVIE_ADMIN_DATA.users, window.MOOVIE_ADMIN_DATA.activity); openModal('citoyensModal'); }
    function switchTab(view, btn) { window.location.href = 'admin.php?tab=' + view; }

    function renderCitoyensList(users, activity, filter = '') {
        const list = document.getElementById('citoyensList');
        const f = filter.toLowerCase();
        list.innerHTML = users.filter(u => u.username.toLowerCase().includes(f)).map(u => `
            <li style="display:flex; align-items:center; gap:12px; padding:10px; background:var(--card-bg); border-radius:8px; border:1px solid var(--border);">
                <img src="${u.avatar || 'assets/default-avatar.png'}" style="width:35px;height:35px;border-radius:50%;object-fit:cover;">
                <div style="flex:1;"><strong>${u.username}</strong><br><small style="font-family:monospace;opacity:0.5;">${u.id}</small></div>
                <div style="text-align:right;"><span style="color:var(--pastel-blue);font-weight:900;">${activity[u.id] || 0}</span><br><small>votes</small></div>
            </li>`).join('');
    }
    function filterCitoyens(v) { renderCitoyensList(window.MOOVIE_ADMIN_DATA.users, window.MOOVIE_ADMIN_DATA.activity, v); }

    window.MOOVIE_ADMIN_DATA = {
        users:    <?= json_encode($allUsers) ?>,
        activity: <?= json_encode($userActivity) ?>,
        badgeLib: <?= json_encode($badgeLibrary) ?>,
    };
    window.MOOVIE_ROLE_LABELS = { user: 'Spécimen', moderator: 'Modérateur', admin: 'Administrateur', superadmin: 'Administrateur' };
    </script>
    <script src="assets/js/ui.js" defer></script>
    <script src="assets/js/admin.js?v=<?= filemtime('assets/js/admin.js') ?>"></script>
    <script>
    async function runOracleSync(btn) {
        btn.disabled = true;
        const log = document.getElementById('oracleSyncLog');
        log.style.display = 'block';
        log.style.color = 'var(--text-dim)';

        let offset = 0, totalUpdated = 0, totalSkipped = 0, totalErrors = 0, grandTotal = 0;
        let secs = 0;
        const timer = setInterval(() => { secs++; }, 1000);

        try {
            let done = false;
            while (!done) {
                btn.textContent = `Oracle en cours… ${offset}/${grandTotal || '?'} (${secs}s)`;
                log.textContent = `Batch en cours : films ${offset + 1}–${offset + 50}… (${secs}s)`;

                const body = new URLSearchParams({ offset, limit: 50 });
                const res  = await fetch('api/api_admin_oracle_likes.php', { method: 'POST', body });

                if (!res.ok) throw new Error(`HTTP ${res.status}`);
                const data = await res.json();
                if (!data.success) throw new Error(data.error || 'Erreur serveur');

                totalUpdated += data.updated;
                totalSkipped += data.skipped;
                totalErrors  += data.errors;
                grandTotal    = data.total;
                offset        = data.offset;
                done          = data.done;
            }
            clearInterval(timer);
            log.style.color = '#9dffb0';
            log.innerHTML = `✓ ${totalUpdated} mis à jour &middot; ${totalSkipped} ignorés (&lt; 5 votes) &middot; ${totalErrors} erreurs &middot; ${grandTotal} films &middot; ${secs}s`;
        } catch (e) {
            clearInterval(timer);
            log.style.color = '#f87171';
            log.textContent = `✗ ${e.message} (après ${offset} films traités)`;
        }
        btn.disabled = false;
        btn.textContent = '⚡ Relancer Oracle';
    }

    async function runTmdbImport(type, btn) {
        btn.disabled = true;
        const otherBtn = document.getElementById(type === 'movie' ? 'btnImportSeries' : 'btnImportMovies');
        if (otherBtn) otherBtn.disabled = true;

        const countEl = document.getElementById(type === 'movie' ? 'importMovieCount' : 'importSerieCount');
        const phaseEl = document.getElementById(type === 'movie' ? 'importMoviePhase' : 'importSeriePhase');
        const barEl   = document.getElementById(type === 'movie' ? 'importMovieBar'  : 'importSerieBar');
        const logEl   = document.getElementById('importLog');

        logEl.style.display = 'block';
        logEl.style.color   = 'var(--text-dim)';

        const phaseLabels = ['Classiques (−1980)', 'Années 80−90', 'Années 2000', 'Années 2010', '2020+'];
        let totalInserted = 0, totalSkipped = 0, totalErrors = 0;
        const storageKey = `tmdbImport_${type}`;
        const saved = JSON.parse(localStorage.getItem(storageKey) || 'null');
        let phase = saved ? saved.phase : 0;
        let page  = saved ? saved.page  : 1;
        let secs = 0;
        if (saved) {
            phaseEl.textContent = `Reprise — Phase ${phase + 1}/5 · Page ${page}`;
        }
        const timer = setInterval(() => { secs++; }, 1000);

        try {
            let done = false, consecutiveErrors = 0;
            while (!done) {
                const body = new URLSearchParams({ type, phase, page });
                let data;
                try {
                    const res = await fetch('api/api_admin_tmdb_import.php', { method: 'POST', body });
                    if (!res.ok) throw new Error(`HTTP ${res.status}`);
                    data = await res.json();
                } catch (fetchErr) {
                    consecutiveErrors++;
                    if (consecutiveErrors >= 3) throw fetchErr;
                    logEl.style.color = '#fbbf24';
                    logEl.textContent = `⚠ Erreur réseau, reprise dans 10s… (tentative ${consecutiveErrors}/3)`;
                    await new Promise(r => setTimeout(r, 10000));
                    continue;
                }

                if (!data.success) {
                    if (data.retryable) {
                        consecutiveErrors++;
                        if (consecutiveErrors >= 3) throw new Error(data.error || 'Erreur serveur');
                        logEl.style.color = '#fbbf24';
                        logEl.textContent = `⚠ TMDB injoignable, reprise dans 15s… (tentative ${consecutiveErrors}/3)`;
                        await new Promise(r => setTimeout(r, 15000));
                        // phase/page inchangés — on réessaie la même page
                        continue;
                    }
                    throw new Error(data.error || 'Erreur serveur');
                }

                consecutiveErrors = 0;
                totalInserted += data.inserted;
                totalSkipped  += data.skipped;
                totalErrors   += data.errors;
                done           = data.done;
                const totalPages = data.total_pages || 500;
                const pct = Math.min(100, ((data.phase * 500 + data.page) / (5 * totalPages)) * 100);

                countEl.textContent = totalInserted;
                barEl.style.width   = pct.toFixed(1) + '%';
                phaseEl.textContent = `Phase ${data.phase + 1}/5 — ${phaseLabels[data.phase] || ''} · Page ${data.page}/${totalPages}`;
                logEl.style.color   = 'var(--text-dim)';
                logEl.textContent   = `+${data.inserted} insérés · ${data.skipped} existants · ${data.errors} err — ${secs}s`;

                if (!done) {
                    phase = data.next_phase;
                    page  = data.next_page;
                    localStorage.setItem(storageKey, JSON.stringify({ phase, page }));
                }
            }
            clearInterval(timer);
            localStorage.removeItem(storageKey);
            barEl.style.width   = '100%';
            phaseEl.textContent = 'Terminé';
            logEl.style.color   = '#9dffb0';
            logEl.textContent   = `✓ ${totalInserted} importés · ${totalSkipped} existants · ${totalErrors} erreurs · ${secs}s`;
        } catch (e) {
            clearInterval(timer);
            logEl.style.color   = '#f87171';
            logEl.textContent   = `✗ ${e.message}`;
            phaseEl.textContent = 'Erreur';
        }
        btn.disabled = false;
        if (otherBtn) otherBtn.disabled = false;
        btn.textContent = '↺ Relancer';
    }

    async function pushBroadcast() {
        const title = document.getElementById('pb-title').value.trim();
        const body  = document.getElementById('pb-body').value.trim();
        const msgEl = document.getElementById('push-broadcast-msg');
        if (!title || !body) { msgEl.style.display='block'; msgEl.style.color='var(--danger)'; msgEl.textContent='Titre et message requis.'; return; }
        msgEl.style.display='block'; msgEl.style.color='var(--text-dim)'; msgEl.textContent='Envoi…';
        const res = await fetch('api/api_push_broadcast.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ title, body, url: '/', target: 'all' }),
        });
        const json = await res.json();
        msgEl.style.color = json.ok ? '#9dffb0' : 'var(--danger)';
        msgEl.textContent = json.ok ? `✓ Envoyé à ${json.sent} abonné(s).` : (json.error || 'Erreur');
    }
    </script>
</body>
</html>