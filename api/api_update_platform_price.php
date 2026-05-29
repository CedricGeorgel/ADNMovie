<?php
/**
 * API_UPDATE_PLATFORM_PRICE.PHP
 * Sauvegarde le prix d'abonnement personnalisé d'un utilisateur pour un provider.
 * DELETE si price = null (retour au prix par défaut).
 */
require_once 'auth_helpers.php';
start_persistent_session();
require_once __DIR__ . '/../config/settings.php';
require_once __DIR__ . '/../functions/core_db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Non connecté']);
    exit;
}

$body       = json_decode(file_get_contents('php://input'), true) ?? [];
$providerId = isset($body['provider_id']) ? (int)$body['provider_id'] : 0;
$price      = isset($body['price']) ? $body['price'] : null;

if (!$providerId) {
    echo json_encode(['success' => false, 'message' => 'provider_id requis']);
    exit;
}

$userId = $_SESSION['user_id'];

if ($price === null || $price === '') {
    db_execute(
        'DELETE FROM user_platform_prices WHERE user_id = ? AND provider_id = ?',
        [$userId, $providerId]
    );
    // Log le retour au prix par défaut pour que l'historique reste cohérent
    $default = db_fetch_one(
        'SELECT monthly_price FROM providers_config WHERE provider_id = ?',
        [$providerId]
    );
    if ($default && $default['monthly_price'] !== null) {
        db_execute(
            'INSERT INTO user_platform_price_history (user_id, provider_id, price, effective_from)
             VALUES (?, ?, ?, CURDATE())
             ON DUPLICATE KEY UPDATE price = VALUES(price)',
            [$userId, $providerId, (float)$default['monthly_price']]
        );
    }
} else {
    $price = round((float)$price, 2);
    if ($price < 0 || $price > 999) {
        echo json_encode(['success' => false, 'message' => 'Prix invalide']);
        exit;
    }
    db_execute(
        'INSERT INTO user_platform_prices (user_id, provider_id, monthly_price)
         VALUES (?, ?, ?)
         ON DUPLICATE KEY UPDATE monthly_price = VALUES(monthly_price)',
        [$userId, $providerId, $price]
    );
    // Versionne le changement de prix avec la date du jour
    db_execute(
        'INSERT INTO user_platform_price_history (user_id, provider_id, price, effective_from)
         VALUES (?, ?, ?, CURDATE())
         ON DUPLICATE KEY UPDATE price = VALUES(price)',
        [$userId, $providerId, $price]
    );
}

echo json_encode(['success' => true]);
