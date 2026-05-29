<?php
/**
 * COMPONENTS/MANAGEMENT-CARD.PHP
 */
require_once __DIR__ . '/avatar.php';

function renderManagementCard(array $item, bool $isAdmin = false): void {
    $isDone     = ($item['status'] === 'done');
    $isRejected = ($item['status'] === 'rejected');
    $isCritical = ($item['priority'] === 'critical');

    $author = get_user_by_id($item['user_id']);

    $opacity     = ($isDone || $isRejected) ? '0.4' : '1';
    $borderColor = $isRejected ? 'rgba(248, 113, 113, 0.4)' : ($isCritical ? 'var(--danger)' : 'var(--border)');
    $background  = $isRejected ? 'rgba(248, 113, 113, 0.08)' : 'rgba(255,255,255,0.02)';

    $priorityOptions = ['critical' => '⚠️ Critical', 'high' => '🔴 High', 'medium' => '🟡 Medium', 'low' => '🔵 Low'];
    $statusOptions   = [
        'pending'  => '📥 Nouveau (Pending)',
        'todo'     => '🚀 Roadmap: Todo',
        'soon'     => '🏗️ Roadmap: En cours',
        'done'     => '🏁 Terminé',
        'rejected' => '❌ Rejeté',
    ];
    $typeOptions = ['bug' => 'Bug', 'feature' => 'Feature', 'algo' => 'Algo', 'refacto' => 'Refacto', 'ui' => 'UI','infra' => 'Infra'];
    ?>
    <div class="roadmap-card"
         data-id="<?= $item['id'] ?>"
         style="opacity:<?= $opacity ?>; background:<?= $background ?>; border:1px solid <?= $borderColor ?>; padding:15px; border-radius:12px; display:flex; flex-direction:column; gap:8px; transition: all 0.2s ease;">

        <!-- Ligne priorité + ID -->
        <div style="display:flex; justify-content:space-between; align-items:center;">
            <?php if ($isAdmin): ?>
                <select onchange="updateSystemItem(<?= $item['id'] ?>, 'priority', this.value)"
                        style="font-size:0.55rem; font-weight:800; background:transparent; border:none; cursor:pointer;
                               color:<?= $isCritical ? 'var(--danger)' : 'var(--text-dim)' ?>; padding:0; appearance:auto;">
                    <?php foreach ($priorityOptions as $val => $label): ?>
                        <option value="<?= $val ?>" <?= $item['priority'] === $val ? 'selected' : '' ?>><?= $label ?></option>
                    <?php endforeach; ?>
                </select>
            <?php else: ?>
                <span style="font-size:0.55rem; font-weight:800; color:<?= $isCritical ? 'var(--danger)' : 'var(--text-dim)' ?>;">
                    <?= $isCritical ? '⚠️ CRITICAL' : strtoupper($item['priority']) ?>
                </span>
            <?php endif; ?>
            <span style="font-size:0.6rem; color:var(--text-dim); font-family:monospace;">#<?= $item['id'] ?></span>
        </div>

        <!-- Titre -->
        <h4 class="card-title card-title-display"
            style="font-size:0.85rem; font-weight:700; margin:0;"><?= h($item['title']) ?></h4>

        <!-- Description -->
        <p class="card-desc card-desc-display"
           style="font-size:0.7rem; color:var(--text-dim); margin:0; line-height:1.4;"><?= h($item['description']) ?></p>

        <!-- Auteur -->
        <div style="display:flex; align-items:center; gap:8px; margin-top:4px;
                    <?= $isRejected ? 'filter: sepia(1) saturate(3) hue-rotate(300deg) brightness(0.7);' : '' ?>">
            <?php renderAvatar($author, $item['user_id']); ?>
            <span style="font-size:0.65rem; color:var(--text-dim);">
                <?= h($author['username'] ?? 'Inconnu') ?>
            </span>
        </div>

        <?php if ($isAdmin): ?>
        <!-- Zone d'édition (cachée par défaut) -->
        <div class="card-edit-zone" style="display:none; flex-direction:column; gap:8px; margin-top:4px;">
            <input type="text"
                   class="card-edit-title"
                   value="<?= h($item['title']) ?>"
                   style="font-size:0.85rem; font-weight:700; background:var(--bg-color); color:var(--text-main);
                          border:1px solid var(--pastel-blue); border-radius:6px; padding:6px 8px; outline:none; width:100%; box-sizing:border-box;">
            <textarea class="card-edit-desc"
                      rows="3"
                      style="font-size:0.7rem; background:var(--bg-color); color:var(--text-dim);
                             border:1px solid var(--border); border-radius:6px; padding:6px 8px; outline:none;
                             width:100%; box-sizing:border-box; resize:vertical; line-height:1.4;"><?= h($item['description']) ?></textarea>
            <div style="display:flex; gap:8px;">
                <button onclick="confirmEditText(this, <?= $item['id'] ?>)"
                        style="flex:1; font-size:0.65rem; font-weight:800; background:var(--pastel-blue); color:#000;
                               border:none; border-radius:6px; padding:6px; cursor:pointer; letter-spacing:0.5px;">
                    ✓ CONFIRMER
                </button>
                <button onclick="cancelEditText(this)"
                        style="font-size:0.65rem; background:none; color:var(--text-dim);
                               border:1px solid var(--border); border-radius:6px; padding:6px 10px; cursor:pointer;">
                    Annuler
                </button>
            </div>
        </div>

        <!-- Actions admin -->
        <div style="margin-top:10px; padding-top:10px; border-top:1px solid var(--border); display:flex; gap:8px; flex-wrap:wrap; align-items:center;">
            <!-- Statut -->
            <select onchange="updateSystemItem(<?= $item['id'] ?>, 'status', this.value)"
                    style="font-size:0.6rem; background:var(--bg-color); color:var(--text-main); border:1px solid var(--border); padding:3px; border-radius:4px;">
                <?php foreach ($statusOptions as $val => $label): ?>
                    <option value="<?= $val ?>" <?= $item['status'] === $val ? 'selected' : '' ?>><?= $label ?></option>
                <?php endforeach; ?>
            </select>

            <!-- Type -->
            <select onchange="updateSystemItem(<?= $item['id'] ?>, 'type', this.value)"
                    style="font-size:0.6rem; background:var(--bg-color); color:var(--text-main); border:1px solid var(--border); padding:3px; border-radius:4px;">
                <?php foreach ($typeOptions as $val => $label): ?>
                    <option value="<?= $val ?>" <?= $item['type'] === $val ? 'selected' : '' ?>><?= $label ?></option>
                <?php endforeach; ?>
            </select>

            <!-- Bouton Éditer -->
            <button onclick="toggleEditText(this, <?= $item['id'] ?>)"
                    class="card-edit-btn"
                    style="font-size:0.6rem; color:var(--pastel-blue); background:none; border:1px solid var(--pastel-blue);
                           border-radius:4px; padding:3px 8px; cursor:pointer;">
                ✏️ ÉDITER
            </button>

            <!-- Bouton Purger -->
            <button onclick="deleteSystemItem(<?= $item['id'] ?>)"
                    style="font-size:0.6rem; color:var(--danger); background:none; border:none; cursor:pointer; margin-left:auto;">
                PURGER
            </button>
        </div>
        <?php endif; ?>
    </div>
    <?php
}