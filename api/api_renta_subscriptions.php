<?php
/**
 * API_RENTA_SUBSCRIPTIONS.PHP
 * Pour chaque abonnement actif de l'utilisateur :
 *   - prix mensuel (custom ou défaut)
 *   - films vus ce mois disponibles sur la plateforme
 *   - valeur équivalente en location (× 4.99€)
 *   - ratio de rentabilité
 *   - nombre de films disponibles non encore vus
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

const RENTAL_PRICE_AVG = 4.99;

$userId = $_SESSION['user_id'];

// ── Plateformes souscrites par l'user ─────────────────────────
$user = db_fetch_one('SELECT user_platforms FROM users WHERE id = ?', [$userId]);
$subscribedIds = array_map('intval', json_decode($user['user_platforms'] ?? '[]', true) ?: []);

if (empty($subscribedIds)) {
    echo json_encode(['success' => true, 'subscriptions' => [], 'rental_price_avg' => RENTAL_PRICE_AVG]);
    exit;
}

// ── Infos providers (prix, nom, logo) ─────────────────────────
$placeholders = implode(',', array_fill(0, count($subscribedIds), '?'));
$providers = db_fetch_all(
    "SELECT pc.provider_id, pc.provider_name, pc.monthly_price AS default_price,
            COALESCE(upp.monthly_price, pc.monthly_price) AS price,
            pc.cancel_url
     FROM providers_config pc
     LEFT JOIN user_platform_prices upp ON upp.provider_id = pc.provider_id AND upp.user_id = ?
     WHERE pc.provider_id IN ({$placeholders}) AND pc.type = 'svod' AND pc.is_active = 1",
    array_merge([$userId], $subscribedIds)
);

// Logo map depuis cache TMDB
$logoMap = [];
$cachePath = __DIR__ . '/../config/providers_list.json';
if (file_exists($cachePath)) {
    $cached = json_decode(file_get_contents($cachePath), true);
    foreach ($cached['providers'] ?? [] as $p) {
        $logoMap[$p['provider_id']] = $p['logo_path'] ?? '';
    }
}

// ── Films notés par l'user (tous + ce mois) ───────────────────
$startOfMonth = date('Y-m-01');
$allRatings   = db_fetch_all(
    'SELECT r.movie_id, r.rated_at, m.providers_data
     FROM ratings r
     JOIN movies m ON m.tmdb_id = r.movie_id
     WHERE r.user_id = ? AND m.providers_data IS NOT NULL',
    [$userId]
);

$ratedAllIds      = [];
$ratedThisMonthIds = [];
$providerMoviesMonth = []; // provider_id => [movie_ids vus ce mois]

foreach ($allRatings as $r) {
    $mid  = (int)$r['movie_id'];
    $data = json_decode($r['providers_data'], true) ?: [];
    $ratedAllIds[] = $mid;

    $isThisMonth = strtotime($r['rated_at']) >= strtotime($startOfMonth);
    if ($isThisMonth) {
        $ratedThisMonthIds[] = $mid;
        $flatrateIds = array_column($data['flatrate'] ?? [], 'provider_id');
        foreach ($flatrateIds as $pid) {
            $providerMoviesMonth[$pid][] = $mid;
        }
    }
}

$ratedAllIds = array_unique($ratedAllIds);

// ── Films disponibles non vus par plateforme ──────────────────
// On charge tous les films avec providers_data (limité pour perf)
$allMovies = db_fetch_all(
    'SELECT tmdb_id, providers_data FROM movies WHERE providers_data IS NOT NULL AND providers_data != "[]"'
);

$providerUnseenCount = [];
foreach ($allMovies as $m) {
    $mid  = (int)$m['tmdb_id'];
    if (in_array($mid, $ratedAllIds)) continue;
    $data = json_decode($m['providers_data'], true) ?: [];
    $flatrateIds = array_column($data['flatrate'] ?? [], 'provider_id');
    foreach ($flatrateIds as $pid) {
        $providerUnseenCount[$pid] = ($providerUnseenCount[$pid] ?? 0) + 1;
    }
}

// ── Construction du résultat ──────────────────────────────────
$result = [];
foreach ($providers as $p) {
    $pid          = (int)$p['provider_id'];
    $price        = $p['price'] !== null ? (float)$p['price'] : null;
    $watchedCount = count(array_unique($providerMoviesMonth[$pid] ?? []));
    $rentalValue  = round($watchedCount * RENTAL_PRICE_AVG, 2);
    $roi          = ($price && $price > 0) ? round($rentalValue / $price, 2) : null;

    $result[] = [
        'provider_id'    => $pid,
        'provider_name'  => $p['provider_name'],
        'logo_path'      => $logoMap[$pid] ?? '',
        'monthly_price'  => $price,
        'watched_month'  => $watchedCount,
        'rental_value'   => $rentalValue,
        'roi'            => $roi,
        'unseen_count'   => $providerUnseenCount[$pid] ?? 0,
        'cancel_url'     => $p['cancel_url'] ?? null,
    ];
}

// Tri : les moins rentables en premier (motivation à agir)
usort($result, fn($a, $b) => ($a['roi'] ?? 999) <=> ($b['roi'] ?? 999));

echo json_encode([
    'success'          => true,
    'subscriptions'    => $result,
    'rental_price_avg' => RENTAL_PRICE_AVG,
    'month'            => date('F Y'),
], JSON_UNESCAPED_UNICODE);
