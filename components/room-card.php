<?php
/**
 * ROOM-CARD.PHP
 * @param array $room Données de la session
 */
function renderRoomCard($room) {
    // Si 'members' est un tableau, on compte. 
    // Sinon, on cherche la clé 'member_count' (que nous allons ajouter dans la requête SQL)
    if (isset($room['members']) && is_array($room['members'])) {
        $memberCount = count($room['members']);
    } else {
        $memberCount = $room['member_count'] ?? 0;
    }
    ?>
    <a href="session.php?id=<?= h($room['id']) ?>" class="room-card">
        <div class="room-info">
            <h3 class="room-name"><?= h($room['name']) ?></h3>
            <p class="room-meta">
                <strong style="color:<?= !empty($room['is_public']) ? 'var(--pastel-blue)' : 'var(--text-dim)' ?>;">
                    <?= !empty($room['is_public']) ? 'Public' : 'Privé' ?>
                </strong>
                · Hébergé par <span><?= h($room['host_name'] ?? 'Inconnu') ?></span>
            </p>
        </div>
        <div class="room-stats">
            <div class="member-badge">
                <i>👥</i> <?= (int)$memberCount ?>
            </div>
            <div class="arrow-icon">→</div>
        </div>
    </a>
    <?php
}