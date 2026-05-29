<?php
/**
 * COMPONENTS/COMMENT.PHP
 * Composant réutilisable pour les sections de commentaires.
 *
 * Fonctions exposées :
 *   renderCommentItem($com, $isAdmin)  — un commentaire (utilisé aussi par api_comments.php)
 *   renderCommentSection($movieId, $currentUser, $currentUserId) — section complète
 */

require_once __DIR__ . '/../functions/utils.php';

if (!function_exists('renderAvatar')) {
    require_once __DIR__ . '/../components/avatar.php';
}
if (!function_exists('renderSignalBtn')) {
    require_once __DIR__ . '/../components/signal-btn.php';
}

// ── Fonctions de formatage ────────────────────────────────────────────────────

function formatCommentContent(string $rawContent): string {
    $esc = fn(string $s) => htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $tokens = [];
    $i = 0;
    $tok = function(string $html) use (&$tokens, &$i): string {
        $key = "\x02" . $i++ . "\x03";
        $tokens[$key] = $html;
        return $key;
    };

    $safe = $rawContent;

    // Échappement backslash : \* \_ \` \# \/ \! → caractère littéral
    $safe = preg_replace_callback('/\\\\([*_`#\/!\\-\\\\>])/', fn($m) => $tok($esc($m[1])), $safe);

    // @moderation aliases → badge spécial (avant @pseudo générique)
    $safe = preg_replace_callback('/@(moderation|mod[eé]ration|modo|mod[eé]rateur)\b/iu', function ($m) use ($tok) {
        return $tok('<span style="color:#f87171;font-weight:800;font-size:0.9em;">@modération</span>');
    }, $safe);

    // @pseudo → lien adn.php (roulette russe 1/24)
    $safe = preg_replace_callback('/@(\w+)/', function ($m) use ($tok, $esc) {
        $pseudo  = $m[1];
        $userRow = db_fetch_one('SELECT id FROM users WHERE username = ?', [$pseudo]);
        $safeId  = $userRow ? $esc($userRow['id']) : $esc($pseudo);
        $page    = (rand(1, 24) === 1) ? 'anomaly.php' : 'adn.php';
        return $tok('<a href="' . $page . '?id=' . $safeId . '" style="color:var(--pastel-blue);font-weight:bold;text-decoration:none;">@' . $esc($pseudo) . '</a>');
    }, $safe);

    // Bold, italic, code
    $safe = preg_replace_callback('/\*\*(.+?)\*\*/s', fn($m) =>
        $tok('<strong style="color:var(--text-main);">' . $esc($m[1]) . '</strong>'), $safe);
    $safe = preg_replace_callback('/\*([^*\n]+?)\*/', fn($m) =>
        $tok('<em>' . $esc($m[1]) . '</em>'), $safe);
    $safe = preg_replace_callback('/`([^`]+)`/', fn($m) =>
        $tok('<code style="background:rgba(255,255,255,0.07);padding:2px 5px;border-radius:4px;font-size:0.85em;font-family:monospace;">' . $esc($m[1]) . '</code>'), $safe);

    // #slug → lien archive vert  (^ ou whitespace avant, \s inclut \n)
    $safe = preg_replace_callback('/(^|[\s])#([\w-]+)/', function ($m) use ($tok, $esc) {
        $slug  = $m[2];
        $row   = db_fetch_one('SELECT title FROM user_content WHERE slug = ? AND is_public = 1', [$slug]);
        $label = $row ? $esc($row['title']) : $slug;
        return $m[1] . $tok('<a href="content.php?slug=' . $esc($slug) . '" style="color:var(--pastel-green);font-weight:bold;">#' . $label . '</a>');
    }, $safe);

    // //tv-digits → mini carte série (avant //digits et /tv-)
    $safe = preg_replace_callback('/(^|[\s])\/\/tv-(\d+)/', function ($m) use ($tok, $esc) {
        $id  = (int)$m[2];
        $row = db_fetch_one('SELECT title, poster, year FROM series WHERE tmdb_id = ?', [$id]);
        if (!$row) return $m[1] . $tok('<a href="fiche.php?id='.$id.'&type=tv" style="color:var(--color-amber);font-weight:bold;">/Série #'.$id.'</a>');
        $poster = $esc($row['poster'] ?? '');
        $title  = $esc($row['title']  ?? '');
        $year   = $esc($row['year']   ?? '');
        $img    = $poster ? '<img src="'.$poster.'" style="width:26px;height:38px;object-fit:cover;border-radius:4px;flex-shrink:0;">' : '';
        $yr     = $year ? ' <span style="color:var(--text-dim);font-weight:400;">('.$year.')</span>' : '';
        return $m[1] . $tok('<a href="fiche.php?id='.$id.'&type=tv" style="display:inline-flex;align-items:center;gap:8px;background:rgba(255,255,255,0.04);border:1px solid var(--border);border-radius:8px;padding:4px 10px 4px 4px;text-decoration:none;color:var(--text-main);font-weight:700;font-size:0.82rem;vertical-align:middle;">'.$img.'<span>'.$title.$yr.'</span></a>');
    }, $safe);

    // //digits → mini carte film (avant /digits)
    $safe = preg_replace_callback('/(^|[\s])\/\/(\d+)/', function ($m) use ($tok, $esc) {
        $id  = (int)$m[2];
        $row = db_fetch_one('SELECT title, poster, year FROM movies WHERE tmdb_id = ?', [$id]);
        if (!$row) return $m[1] . $tok('<a href="fiche.php?id='.$id.'" style="color:var(--color-amber);font-weight:bold;">/Film #'.$id.'</a>');
        $poster = $esc($row['poster'] ?? '');
        $title  = $esc($row['title']  ?? '');
        $year   = $esc($row['year']   ?? '');
        $img    = $poster ? '<img src="'.$poster.'" style="width:26px;height:38px;object-fit:cover;border-radius:4px;flex-shrink:0;">' : '';
        $yr     = $year ? ' <span style="color:var(--text-dim);font-weight:400;">('.$year.')</span>' : '';
        return $m[1] . $tok('<a href="fiche.php?id='.$id.'" style="display:inline-flex;align-items:center;gap:8px;background:rgba(255,255,255,0.04);border:1px solid var(--border);border-radius:8px;padding:4px 10px 4px 4px;text-decoration:none;color:var(--text-main);font-weight:700;font-size:0.82rem;vertical-align:middle;">'.$img.'<span>'.$title.$yr.'</span></a>');
    }, $safe);

    // /tv-digits → lien série amber (avant /digits)
    $safe = preg_replace_callback('/(^|[\s])\/tv-(\d+)/', function ($m) use ($tok, $esc) {
        $id    = (int)$m[2];
        $row   = db_fetch_one('SELECT title FROM series WHERE tmdb_id = ?', [$id]);
        $label = $row ? $esc($row['title']) : "Série #{$id}";
        return $m[1] . $tok('<a href="fiche.php?id=' . $id . '&type=tv" style="color:var(--color-amber);font-weight:bold;">/' . $label . '</a>');
    }, $safe);

    // /digits → lien film amber
    $safe = preg_replace_callback('/(^|[\s])\/(\d+)/', function ($m) use ($tok, $esc) {
        $id    = (int)$m[2];
        $row   = db_fetch_one('SELECT title FROM movies WHERE tmdb_id = ?', [$id]);
        $label = $row ? $esc($row['title']) : "Film #{$id}";
        return $m[1] . $tok('<a href="fiche.php?id=' . $id . '" style="color:var(--color-amber);font-weight:bold;">/' . $label . '</a>');
    }, $safe);

    // Échappe le texte restant, restaure les tokens
    return strtr($esc($safe), $tokens);
}

// ── Item commentaire ──────────────────────────────────────────────────────────

function renderCommentItem(array $com, bool $isAdmin = false, bool $isLoggedIn = false): void {
    $mockUser = [
        'username' => $com['username'] ?? 'Anonyme',
        'avatar'   => $com['avatar']   ?? 'assets/default-avatar.png',
    ];

    $content = formatCommentContent($com['content']);
    $isReply = !empty($com['parent_id']);
    $indent  = $isReply
        ? 'margin-left:50px; border-left:2px solid var(--border); padding-left:20px;'
        : '';
    ?>
    <div class="comment-item" data-id="<?= (int)$com['id'] ?>"
         style="display:flex; gap:15px; margin-bottom:25px; padding-bottom:10px; <?= $indent ?> width:100%;">

        <div class="comment-avatar" style="flex-shrink:0;">
            <?php renderAvatar($mockUser, $com['user_id']); ?>
        </div>

        <div class="comment-content" style="flex:1; min-width:0;">
            <div class="comment-header"
                 style="display:flex; justify-content:space-between; align-items:baseline; margin-bottom:5px;">
                <div style="display:flex; align-items:center; gap:10px;">
                    <span style="font-weight:700; font-size:0.85rem; color:var(--text-main);">
                        <?= h($mockUser['username']) ?><?= role_shield_html($com['role'] ?? 'user') ?>
                    </span>
                    <span style="font-size:0.65rem; color:var(--text-dim); font-family:monospace;">
                        <?= date('d/m/Y H:i', strtotime($com['created_at'])) ?>
                    </span>
                </div>

                <div style="display:flex; align-items:center; gap:8px;">
                    <?php if (!$isReply): ?>
                    <button onclick="replyToComment(<?= (int)$com['id'] ?>, '<?= h(addslashes($mockUser['username'])) ?>')"
                            class="comment-reply-btn">
                        Répondre
                    </button>
                    <?php endif; ?>

                    <?php renderSignalBtn('comment', (int)$com['id'], $isLoggedIn && !$isAdmin); ?>

                    <?php if ($isAdmin): ?>
                    <button onclick="deleteComment(<?= (int)$com['id'] ?>)"
                            style="background:none; border:none; color:var(--danger); font-size:0.6rem; cursor:pointer; text-transform:uppercase; font-weight:800; opacity:0.5;"
                            onmouseover="this.style.opacity=1" onmouseout="this.style.opacity=0.5">
                        [Purger]
                    </button>
                    <?php endif; ?>
                </div>
            </div>

            <p style="font-size:0.85rem; line-height:1.5; color:var(--text-muted); margin:0; word-wrap:break-word;">
                <?= nl2br($content) ?>
            </p>
        </div>
    </div>
    <?php
}

// ── Section complète ──────────────────────────────────────────────────────────

function renderCommentSection(string $refId, ?array $currentUser, ?string $currentUserId): void {
    ?>
    <div class="movie-comments-section" data-movie-id="<?= h($refId) ?>"
         style="margin-top:60px; border-top:1px dashed var(--border); padding-top:30px;">

        <h3 style="font-size:0.8rem; letter-spacing:2px; color:var(--text-dim); margin-bottom:25px;">
            ARCHIVES DE PENSÉES
        </h3>

        <?php if ($currentUser): ?>
        <form id="commentForm" style="display:flex; gap:15px; margin-bottom:40px;">
            <input type="hidden" id="commentParentId" name="parent_id" value="">

            <div style="flex-shrink:0;">
                <?php renderAvatar($currentUser, $currentUserId); ?>
            </div>

            <div style="flex:1; display:flex; flex-direction:column; gap:8px;">

                <!-- Indicateur de réponse -->
                <div id="commentReplyIndicator"
                     style="display:none; align-items:center; justify-content:space-between;
                            padding:6px 12px; background:rgba(167,199,231,0.08);
                            border:1px solid rgba(167,199,231,0.2); border-radius:8px;
                            font-size:0.7rem; color:var(--pastel-blue);">
                    <span id="commentReplyLabel"></span>
                    <button type="button" onclick="cancelReply()"
                            style="background:none; border:none; color:var(--text-dim);
                                   font-size:0.85rem; cursor:pointer; line-height:1;">✕</button>
                </div>

                <textarea id="commentInput" placeholder="Partagez votre analyse… (!# film, !## archive, @ mention)"
                          data-ac-context="fiche"
                          data-ac-ref="<?= h($refId) ?>"
                          style="width:100%; min-height:80px; padding:15px;
                                 background:rgba(255,255,255,0.03); border:1px solid var(--border);
                                 border-radius:12px; color:white; font-family:inherit;
                                 resize:vertical; outline:none;"></textarea>

                <button type="submit" class="btn-base active"
                        style="align-self:flex-end; padding:10px 25px;">
                    Transmettre
                </button>
            </div>
        </form>
        <?php endif; ?>

        <div id="commentsList"></div>
        <div id="commentsSentinel" style="height:20px; width:100%;"></div>
    </div>
    <?php
    if ($currentUser) renderSignalModal();
    ?>
    <script src="assets/js/comment-autocomplete.js" defer></script>
    <?php
}

// ── Section commentaires pour user_content ────────────────────────────────────

function renderContentCommentSection(int $contentId, ?array $currentUser, ?string $currentUserId): void {
    ?>
    <div id="contentCommentsSection" style="margin-top:60px;border-top:1px dashed var(--border);padding-top:30px;">
        <h3 style="font-size:0.8rem;letter-spacing:2px;color:var(--text-dim);margin-bottom:25px;">RÉACTIONS</h3>

        <?php if ($currentUser): ?>
        <div style="display:flex;gap:15px;margin-bottom:40px;">
            <?php renderAvatar($currentUser, $currentUserId); ?>
            <div style="flex:1;display:flex;flex-direction:column;gap:8px;">
                <div id="ccReplyIndicator"
                     style="display:none;align-items:center;justify-content:space-between;
                            padding:6px 12px;background:rgba(167,199,231,0.08);
                            border:1px solid rgba(167,199,231,0.2);border-radius:8px;
                            font-size:0.7rem;color:var(--pastel-blue);">
                    <span id="ccReplyLabel"></span>
                    <button type="button" onclick="ccCancelReply()"
                            style="background:none;border:none;color:var(--text-dim);font-size:0.85rem;cursor:pointer;">✕</button>
                </div>
                <textarea id="ccInput" placeholder="Partagez votre réaction… (!# film, !## archive, @ mention)"
                          data-ac-context="archive"
                          data-ac-ref="<?= (int)$contentId ?>"
                          style="width:100%;min-height:80px;padding:15px;
                                 background:rgba(255,255,255,0.03);border:1px solid var(--border);
                                 border-radius:12px;color:white;font-family:inherit;
                                 resize:vertical;outline:none;"></textarea>
                <button onclick="ccPost()" class="btn-base active"
                        style="align-self:flex-end;padding:10px 25px;">Transmettre</button>
            </div>
        </div>
        <?php endif; ?>

        <div id="ccList"></div>
        <button id="ccLoadMore" onclick="ccLoad()" class="btn-base"
                style="display:none;width:100%;margin-top:20px;font-size:0.7rem;">
            Charger plus
        </button>
    </div>
    <script src="assets/js/comment-autocomplete.js" defer></script>
    <?php
}
