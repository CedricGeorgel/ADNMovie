<?php
/**
 * Composant Avatar
 * Intègre une mécanique de "roulette russe" sur le clic.
 */
function renderAvatar($user, $userId) {
    if (!$user) return;
    
    $username = h($user['username'] ?? 'Inconnu');
    $avatar   = h($user['avatar'] ?? 'assets/default-avatar.png');
    $safeId   = h($userId);
    
    // Roulette russe : 1 chance sur 24 de déclencher l'anomalie
    $isAnomaly = (rand(1, 24) === 1);
    $targetUrl = $isAnomaly ? "anomaly.php?id={$safeId}" : "adn.php?id={$safeId}";
    
    ?>
    <div class="member-avatar-container" 
         data-tooltip="<?= $username ?>" 
         data-user-id="<?= $safeId ?>" 
         data-avatar="<?= $avatar ?>"
         onclick="window.location.href='<?= $targetUrl ?>'"
         style="cursor: pointer;">
        <img src="<?= $avatar ?>" onerror="this.src='assets/default-avatar.png';" class="member-avatar-img">
    </div>
    <?php
}