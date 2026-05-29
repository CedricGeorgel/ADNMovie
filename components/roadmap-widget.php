<?php
require_once 'management-card.php';

function renderRoadmap(string $view = 'roadmap', bool $isAdmin = false): void {
    $tagColors = [
        'bug'     => ['color' => '#f87171', 'label' => '🐞 BUGS'],
        'algo'    => ['color' => '#6ee7b7', 'label' => '🧬 ALGORITHME'],
        'feature' => ['color' => '#A7C7E7', 'label' => '💡 FEATURES'],
        'refacto' => ['color' => '#B8A7E7', 'label' => '🛠️ MAINTENANCE'],
        'ui'      => ['color' => '#E8C07A', 'label' => '🎨 INTERFACE'],
    ];

    $priorityOrder = ['critical' => 0, 'high' => 1, 'medium' => 2, 'low' => 3];

    // ── Requête selon la vue ──────────────────────────────────
    if ($view === 'all') {
        $items = db_fetch_all(
            "SELECT * FROM system_management
             ORDER BY FIELD(status, 'todo', 'soon', 'pending', 'approved', 'done', 'rejected') ASC,
                      FIELD(priority, 'critical', 'high', 'medium', 'low') ASC"
        );
    } elseif ($view === 'archive') {
        // Vue "Cards passées" : uniquement done + rejected, tous types
        $items = db_fetch_all(
            "SELECT * FROM system_management
             WHERE status IN ('done', 'rejected')
             ORDER BY FIELD(priority, 'critical', 'high', 'medium', 'low') ASC"
        );
    } elseif ($view === 'roadmap') {
        // Roadmap : todo + soon uniquement (plus de done ici)
        $items = db_fetch_all(
            "SELECT * FROM system_management
             WHERE status IN ('todo', 'soon')
             ORDER BY FIELD(priority, 'critical', 'high', 'medium', 'low') ASC"
        );
    } else {
        // Onglets bug/feature : pending + approved uniquement (plus de rejected ici)
        $items = db_fetch_all(
            "SELECT * FROM system_management
             WHERE type = ? AND status IN ('pending', 'approved')
             ORDER BY FIELD(priority, 'critical', 'high', 'medium', 'low') ASC",
            [$view]
        );
    }

    // ── Tri applicatif par priorité ───────────────────────────
    usort($items, function($a, $b) use ($priorityOrder) {
        $aPrio = $priorityOrder[$a['priority']] ?? 99;
        $bPrio = $priorityOrder[$b['priority']] ?? 99;
        return $aPrio - $bPrio;
    });

    if (empty($items)) {
        echo '<p style="color:var(--text-dim); font-size:0.8rem; padding:20px;">Aucun signal détecté dans ce secteur.</p>';
        return;
    }

    // ── Vue ALL : groupement par statut ───────────────────────
    if ($view === 'all') {
        $statusConfig = [
            'todo'     => ['label' => '🚀 Roadmap — Todo',     'color' => '#A7C7E7'],
            'soon'     => ['label' => '🏗️ En cours',           'color' => '#6ee7b7'],
            'pending'  => ['label' => '📥 En attente',          'color' => '#E8C07A'],
            'approved' => ['label' => '✅ Approuvé',            'color' => '#B8A7E7'],
            'done'     => ['label' => '🏁 Terminé',             'color' => '#6ee7b7'],
            'rejected' => ['label' => '❌ Rejeté',              'color' => '#f87171'],
        ];
        $byStatus = [];
        foreach ($items as $item) {
            $byStatus[$item['status']][] = $item;
        }
        $statusOrder = ['todo', 'soon', 'pending', 'approved', 'done', 'rejected'];
        foreach ($statusOrder as $status):
            if (empty($byStatus[$status])) continue;
            $cfg = $statusConfig[$status];
        ?>
            <div class="roadmap-group" style="margin-bottom:40px;">
                <h3 style="color:<?= $cfg['color'] ?>; font-size:0.65rem; letter-spacing:2px; margin-bottom:15px;
                            border-left:3px solid <?= $cfg['color'] ?>; padding-left:10px; text-transform:uppercase;">
                    <?= $cfg['label'] ?>
                    <span style="color:var(--text-dim); font-weight:400; margin-left:8px;">(<?= count($byStatus[$status]) ?>)</span>
                </h3>
                <div class="roadmap-grid" style="display:grid; grid-template-columns:repeat(auto-fill, minmax(320px, 1fr)); gap:15px;">
                    <?php foreach ($byStatus[$status] as $item) { renderManagementCard($item, $isAdmin); } ?>
                </div>
            </div>
        <?php endforeach;
        return;
    }

    // ── Autres vues : groupement par type ─────────────────────
    $groups = [];
    foreach ($items as $item) {
        $groups[$item['type']][] = $item;
    }

    $typeOrder = ['bug', 'feature', 'ui', 'algo', 'refacto'];
    uksort($groups, function($a, $b) use ($typeOrder) {
        $posA = array_search($a, $typeOrder);
        $posB = array_search($b, $typeOrder);
        $posA = $posA === false ? 99 : $posA;
        $posB = $posB === false ? 99 : $posB;
        return $posA - $posB;
    });

    foreach ($groups as $type => $groupItems):
        $config = $tagColors[$type] ?? $tagColors['feature'];
    ?>
        <div class="roadmap-group" style="margin-bottom: 40px;">
            <h3 style="color:<?= $config['color'] ?>; font-size: 0.65rem; letter-spacing: 2px; margin-bottom: 15px;
                        border-left: 3px solid <?= $config['color'] ?>; padding-left: 10px; text-transform:uppercase;">
                <?= $config['label'] ?>
                <span style="color:var(--text-dim); font-weight:400; margin-left:8px;">
                    (<?= count($groupItems) ?>)
                </span>
            </h3>
            <div class="roadmap-grid" style="display:grid; grid-template-columns:repeat(auto-fill, minmax(320px, 1fr)); gap:15px;">
                <?php foreach ($groupItems as $item) { renderManagementCard($item, $isAdmin); } ?>
            </div>
        </div>
    <?php endforeach;
}