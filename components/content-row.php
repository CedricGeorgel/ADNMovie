<?php
/**
 * COMPONENTS/CONTENT-ROW.PHP
 * Ligne de publication partagée (user.php, adn.php, archives.php).
 *
 * Attend dans $c : slug, type, title, last_activity, comment_count
 */
function renderContentRow(array $c): void {
    $cl = match($c['type']) {
        'guide'    => 'var(--color-lavender)',
        'list'     => 'var(--pastel-blue)',
        default    => 'var(--color-amber)',
    };
    $ct = match($c['type']) {
        'guide'    => 'GUIDE',
        'list'     => 'LISTE',
        default    => 'CRITIQUE',
    };
    $date         = !empty($c['last_activity']) ? date('d/m/Y', strtotime($c['last_activity'])) : '';
    $commentCount = (int)($c['comment_count'] ?? 0);
    ?>
    <a href="content.php?slug=<?= h($c['slug']) ?>"
       style="display:flex;align-items:center;gap:10px;padding:10px 14px;
              background:rgba(255,255,255,0.02);border:1px solid var(--border);
              border-radius:10px;text-decoration:none;color:inherit;transition:border-color 0.2s;"
       onmouseover="this.style.borderColor='<?= $cl ?>'"
       onmouseout="this.style.borderColor='var(--border)'">
        <span style="font-size:0.55rem;font-weight:800;letter-spacing:1px;color:<?= $cl ?>;
                     border:1px solid <?= $cl ?>;border-radius:4px;padding:2px 6px;white-space:nowrap;">
            <?= $ct ?>
        </span>
        <span style="flex:1;font-size:0.82rem;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
            <?= h($c['title']) ?>
        </span>
        <span style="display:flex;align-items:center;gap:8px;flex-shrink:0;">
            <?php if ($commentCount > 0): ?>
            <span style="font-size:0.6rem;color:var(--text-dim);">💬 <?= $commentCount ?></span>
            <?php endif; ?>
            <?php if ($date): ?>
            <span style="font-size:0.62rem;color:var(--text-dim);"><?= $date ?></span>
            <?php endif; ?>
        </span>
    </a>
    <?php
}
