<?php
/**
 * API_GET_PROVIDERS.PHP
 * Retourne les providers SVOD actifs (depuis providers_config)
 * avec le prix personnalisé de l'utilisateur si disponible.
 */
require_once 'auth_helpers.php';
start_persistent_session();
require_once __DIR__ . '/../config/settings.php';
require_once __DIR__ . '/../functions/core_db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false]);
    exit;
}

$userId = $_SESSION['user_id'];

$providers = db_fetch_all(
    'SELECT
        pc.provider_id,
        pc.provider_name,
        pc.monthly_price                          AS default_price,
        COALESCE(upp.monthly_price, pc.monthly_price) AS price,
        pc.display_priority
     FROM providers_config pc
     LEFT JOIN user_platform_prices upp
            ON upp.provider_id = pc.provider_id AND upp.user_id = ?
     WHERE pc.type = "svod" AND pc.is_active = 1
     ORDER BY pc.display_priority ASC',
    [$userId]
);

// Ajoute le logo_path depuis le cache TMDB si dispo
$cachePath = __DIR__ . '/../config/providers_list.json';
$logoMap   = [];
if (file_exists($cachePath)) {
    $cached = json_decode(file_get_contents($cachePath), true);
    foreach ($cached['providers'] ?? [] as $p) {
        $logoMap[$p['provider_id']] = $p['logo_path'] ?? '';
    }
}

// Si pas de cache logo, on le reconstruit depuis TMDB
if (empty($logoMap)) {
    $url = 'https://api.themoviedb.org/3/watch/providers/movie?api_key=' . TMDB_API_KEY . '&language=fr-FR&watch_region=FR';
    $ch  = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 10, CURLOPT_USERAGENT => 'Moovie-App/2.0']);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($httpCode === 200 && $response) {
        $data = json_decode($response, true);
        foreach ($data['results'] ?? [] as $p) {
            $logoMap[$p['provider_id']] = $p['logo_path'] ?? '';
        }
        file_put_contents($cachePath, json_encode(['success' => true, 'providers' => $data['results'] ?? []], JSON_UNESCAPED_UNICODE));
    }
}

foreach ($providers as &$p) {
    $p['logo_path'] = $logoMap[$p['provider_id']] ?? '';
}
unset($p);

echo json_encode(['success' => true, 'providers' => $providers], JSON_UNESCAPED_UNICODE);
