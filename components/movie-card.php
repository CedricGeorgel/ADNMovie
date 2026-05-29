<?php
/**
 * MOVIE-CARD.PHP - Version ADN Unifiée
 * Les tags Match et Découverte partagent désormais le même ADN visuel.
 */
require_once __DIR__ . '/match-badge.php';


function renderMovieCard($movie, $options = []) {
    $showMeta      = $options['show_meta']       ?? false;
    $showBadges    = $options['show_badges']     ?? true;
    $glitch        = $options['glitch']          ?? false;
    $extraLabel    = $options['extra_label']     ?? null; // Contient le "% MATCH"
    $isReco        = $options['is_reco']         ?? false;
    $isExploration = $options['is_exploration']  ?? false;
    $isSeen        = $options['is_seen']         ?? false;
    $isLiked       = $options['is_liked']        ?? false;
    $showVotes     = $options['show_votes']      ?? false;
    $voteCount     = $options['vote_count']      ?? 0;
    $hasVoted      = $options['has_voted']       ?? false;
    $isHost        = $options['is_host']         ?? false;
    $contentType   = $options['content_type']    ?? 'movie'; // 'movie', 'tv', 'season', 'episode'
    $ratingFooter  = $options['rating_footer']   ?? null; // array: rated_at, is_liked, like_score, scores, criteria_labels
    $seasonNumber  = $options['season_number']   ?? null;
    $episodeNumber = $options['episode_number']  ?? null;
    $movieId       = $movie['id'] ?? $movie['tmdb_id'] ?? '';

    // Build link URL based on content type
    if ($contentType === 'tv') {
        $linkUrl = 'fiche.php?id=' . h($movieId) . '&type=tv';
    } elseif ($contentType === 'season') {
        $linkUrl = 'fiche.php?id=' . h($movieId) . '&type=tv&season=' . (int)$seasonNumber;
    } elseif ($contentType === 'episode') {
        $linkUrl = 'fiche.php?id=' . h($movieId) . '&type=tv&season=' . (int)$seasonNumber . '&episode=' . (int)$episodeNumber;
    } else {
        $linkUrl = 'fiche.php?id=' . h($movieId);
    }

    $noPoster  = 'assets/no-poster.svg';
    $posterSrc = !empty($movie['poster']) ? h($movie['poster']) : $noPoster;

    // Style commun pour les tags supérieurs (Match & Découverte)
    $tagStyle = "position:absolute; top:8px; left:8px; z-index:100; " .
                "font-size:0.55rem; font-weight:900; padding:3px 8px; " .
                "border-radius:20px; text-transform:uppercase; " .
                "white-space:nowrap; box-shadow:0 4px 8px rgba(0,0,0,0.3);";
    ?>
    <div class="movie-card" data-id="<?= h($movieId) ?>" <?= $isReco ? 'data-reco="true"' : '' ?> 
         style="width:138px; min-width:138px; flex-shrink:0; display:flex; flex-direction:column; height:auto; border-radius:12px; overflow:visible; background:none;">

        <div class="poster-wrapper" style="position:relative; width:100%; aspect-ratio:2/3; overflow:hidden; border-radius:12px 12px 0 0; z-index:1; background:#111;">
            
            <a href="<?= $linkUrl ?>" style="display:block; width:100%; height:100%;">
                <img src="<?= $posterSrc ?>" alt="<?= h($movie['title']) ?>"
                     onerror="this.onerror=null;this.src='assets/no-poster.svg'"
                     style="width:100%; height:100%; object-fit:cover; display:block;">
            </a>

            <?php if ($isReco): ?>
                <?php $skipId = ($contentType === 'tv') ? '-' . h($movieId) : h($movieId); ?>
                <button class="btn-skip" onclick="skipReco('<?= $skipId ?>', this)"
                        style="position:absolute; top:8px; right:8px; width:22px; height:22px; background:rgba(0,0,0,0.7); border:1px solid rgba(255,255,255,0.2); border-radius:50%; color:#fff; font-size:10px; cursor:pointer; z-index:101; backdrop-filter:blur(4px); display:flex; align-items:center; justify-content:center;">
                    ✕
                </button>
            <?php endif; ?>

            <?php if ($isReco): ?>
                <?php if ($isExploration): ?>
                    <div style="<?= $tagStyle ?> background:var(--color-amber); color:#000;">
                        Découverte
                    </div>
                <?php elseif ($extraLabel): ?>
                    <div style="<?= $tagStyle ?> background:var(--pastel-blue); color:#000;">
                        <?= h($extraLabel) ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>

            <?php if ($contentType === 'movie' && $showBadges && ($isSeen || $isLiked)): ?>
            <div style="position:absolute; bottom:8px; right:8px; display:flex; gap:4px; z-index:100;">
                <?php if ($isSeen): ?><div style="background:rgba(0,0,0,0.6); backdrop-filter:blur(4px); width:20px; height:20px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:0.6rem; border:1px solid var(--pastel-blue); color:var(--pastel-blue);">✓</div><?php endif; ?>
                <?php if ($isLiked): ?><div style="background:rgba(0,0,0,0.6); backdrop-filter:blur(4px); width:20px; height:20px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:0.6rem; border:1px solid var(--danger); color:var(--danger);">❤️</div><?php endif; ?>
            </div>
            <?php endif; ?>
        </div>

        <div class="movie-details" style="display:flex; flex-direction:column; background:var(--card-bg); border:1px solid var(--border); border-top:none; padding:10px; border-radius:<?= $ratingFooter ? '0' : '0 0 12px 12px' ?>; flex-grow:0; min-height:75px;">
            <?php if ($glitch): ?>
            <h4 class="movie-title label-glitch" data-text="<?= h($movie['title']) ?>"
                style="margin:0 0 4px 0; font-size:0.85rem; line-height:1.25em; height:2.5em; display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; overflow:hidden; font-weight:700; color:var(--text-main); --bg:var(--card-bg);">
                <?= h($movie['title']) ?>
            </h4>
            <?php else: ?>
            <h4 class="movie-title" style="margin:0 0 4px 0; font-size:0.85rem; line-height:1.25em; height:2.5em; display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; overflow:hidden; font-weight:700; color:var(--text-main);">
                <?= h($movie['title']) ?>
            </h4>
            <?php endif; ?>
            <p class="movie-meta" style="margin:0; font-size:0.7rem; color:var(--text-dim); font-weight:600;">
                <?php if ($contentType === 'tv'): ?>
                    <?= h($movie['year'] ?? '') ?>
                    <span style="display:inline-block; margin-left:4px; font-size:0.55rem; font-weight:900; padding:2px 6px; border-radius:20px; background:var(--pastel-blue); color:#000; text-transform:uppercase; vertical-align:middle;">Série</span>
                <?php elseif ($contentType === 'season'): ?>
                    <span style="color:var(--text-dim);">Saison <?= (int)$seasonNumber ?></span>
                <?php elseif ($contentType === 'episode'): ?>
                    <span style="color:var(--text-dim);">Épisode <?= (int)$episodeNumber ?></span>
                <?php else: ?>
                    <?= h($movie['year'] ?? '') ?>
                <?php endif; ?>
            </p>

            <?php if ($showVotes): ?>
            <div class="movie-votes" style="margin-top:8px; display:flex; align-items:center; gap:6px;">
                <span style="font-size:0.75rem; font-weight:800; color:var(--text-main);"><?= (int)$voteCount ?></span>
                <button onclick="toggleVote('<?= h($movieId) ?>', document.body.dataset.roomId)"
                        class="btn-fire <?= $hasVoted ? 'active' : '' ?>"
                        style="background:none; border:none; cursor:pointer; font-size:1rem; padding:0; opacity:<?= $hasVoted ? '1' : '0.4' ?>; transition:opacity 0.2s;">
                    🔥
                </button>
                <?php if ($isHost): ?>
                <button onclick="removeProposal('<?= h($movieId) ?>', document.body.dataset.roomId)"
                        title="Retirer ce film"
                        style="background:none; border:none; cursor:pointer; font-size:0.75rem; padding:0; margin-left:auto; color:rgba(255,255,255,0.25); transition:color 0.2s;"
                        onmouseover="this.style.color='var(--danger)'" 
                        onmouseout="this.style.color='rgba(255,255,255,0.25)'">
                    ✕
                </button>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>

        <?php if ($ratingFooter && !empty($ratingFooter['rated_at'])): ?>
        <?php
            $rf       = $ratingFooter;
            $rfScores = array_filter(json_decode($rf['scores'] ?? '{}', true) ?: [], fn($v) => $v !== null);
            $rfLike   = $rf['like_score'] !== null ? (float)$rf['like_score'] : null;
            $rfSign   = $rfLike !== null && $rfLike > 0 ? '+' : '';
            $rfLabels = $rf['criteria_labels'] ?? [];
        ?>
        <div style="background:var(--card-bg); border:1px solid var(--border); border-top:none; border-radius:0 0 12px 12px; padding:8px 10px 10px; display:flex; flex-direction:column; gap:5px;">
            <div style="font-size:0.55rem; color:var(--text-dim); letter-spacing:0.5px;">
                <?= date('d/m/Y', strtotime($rf['rated_at'])) ?>
            </div>
            <div style="display:flex; align-items:center; gap:6px;">
                <span style="font-size:0.8rem; line-height:1;"><?= $rf['is_liked'] ? '❤️' : '🤍' ?></span>
                <?php if ($rfLike !== null): ?>
                <span style="font-size:0.72rem; font-weight:700; color:var(--text-main);"><?= $rfSign . number_format($rfLike, 1) ?></span>
                <?php endif; ?>
            </div>
            <?php if (!empty($rfScores)): ?>
            <div style="display:flex; flex-direction:column; gap:2px; margin-top:1px;">
                <?php foreach ($rfScores as $key => $val):
                    $s = $val > 0 ? '+' : '';
                ?>
                <div style="display:flex; justify-content:space-between; font-size:0.58rem;">
                    <span style="color:var(--text-dim);"><?= $rfLabels[$key] ?? $key ?></span>
                    <span style="font-weight:700; color:var(--text-main);"><?= $s . (int)$val ?></span>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

    </div>
    <?php
}