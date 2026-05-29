<?php
/**
 * API_RENTA_HISTORY.PHP
 * Retourne sur les 12 derniers mois, le ratio ROI par plateforme :
 *   ROI = (films_vus × 4.99€) / prix_mensuel
 *   ROI > 1 = rentable, ROI < 1 = sous-utilisé, null = pas abonné ce mois
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

const RENTAL_PRICE = 4.99;

$userId = $_SESSION['user_id'];

// ── Plateformes souscrites + prix ─────────────────────────────
$user = db_fetch_one('SELECT user_platforms FROM users WHERE id = ?', [$userId]);
$subscribedIds = array_map('intval', json_decode($user['user_platforms'] ?? '[]', true) ?: []);

if (empty($subscribedIds)) {
    echo json_encode(['success' => true, 'labels' => [], 'datasets' => [], 'monthly_cost' => 0]);
    exit;
}

$placeholders = implode(',', array_fill(0, count($subscribedIds), '?'));
$providers = db_fetch_all(
    "SELECT pc.provider_id, pc.provider_name,
            COALESCE(upp.monthly_price, pc.monthly_price) AS price
     FROM providers_config pc
     LEFT JOIN user_platform_prices upp ON upp.provider_id = pc.provider_id AND upp.user_id = ?
     WHERE pc.provider_id IN ({$placeholders}) AND pc.type = 'svod' AND pc.is_active = 1",
    array_merge([$userId], $subscribedIds)
);

$priceMap     = [];
$monthlyTotal = 0;
foreach ($providers as $p) {
    $price = $p['price'] !== null ? (float)$p['price'] : null;
    $priceMap[(int)$p['provider_id']] = $price;
    $monthlyTotal += $price ?? 0;
}

// ── Historique des prix (pour corriger le ROI par mois) ───────
$placeholders2  = implode(',', array_fill(0, count($subscribedIds), '?'));
$priceHistoryRows = db_fetch_all(
    "SELECT provider_id, price, effective_from
     FROM user_platform_price_history
     WHERE user_id = ? AND provider_id IN ({$placeholders2})
     ORDER BY provider_id ASC, effective_from ASC",
    array_merge([$userId], $subscribedIds)
);

// Groupé par provider_id, trié ASC → on peut itérer pour trouver le prix effectif
$historyByProvider = [];
foreach ($priceHistoryRows as $h) {
    $historyByProvider[(int)$h['provider_id']][] = [
        'from'  => $h['effective_from'],  // 'YYYY-MM-DD'
        'price' => (float)$h['price'],
    ];
}

// Retourne le prix effectif pour un mois donné (format 'YYYY-MM-01').
// Cherche l'entrée la plus récente dont effective_from <= $monthStart.
// Si aucune entrée → fallback sur le prix actuel.
function priceForMonth(array $history, ?float $currentPrice, string $monthStart): ?float {
    $result = null;
    foreach ($history as $entry) {
        if ($entry['from'] <= $monthStart) {
            $result = $entry['price'];
        } else {
            break;
        }
    }
    return $result ?? $currentPrice;
}

// ── Logos ─────────────────────────────────────────────────────
$logoMap = [];
$cachePath = __DIR__ . '/../config/providers_list.json';
if (file_exists($cachePath)) {
    $cached = json_decode(file_get_contents($cachePath), true);
    foreach ($cached['providers'] ?? [] as $p) {
        $logoMap[$p['provider_id']] = $p['logo_path'] ?? '';
    }
}

// ── Ratings des 12 derniers mois ──────────────────────────────
$since = date('Y-m-01', strtotime('-11 months'));
$ratings = db_fetch_all(
    "SELECT r.movie_id, r.rated_at, m.providers_data
     FROM ratings r
     JOIN movies m ON m.tmdb_id = r.movie_id
     WHERE r.user_id = ? AND r.rated_at >= ? AND m.providers_data IS NOT NULL",
    [$userId, $since]
);

// ── Comptage par mois × plateforme ────────────────────────────
$monthlyWatched = []; // [month][provider_id] = count
for ($i = 11; $i >= 0; $i--) {
    $key = date('Y-m', strtotime("-{$i} months"));
    foreach ($subscribedIds as $pid) {
        $monthlyWatched[$key][$pid] = 0;
    }
}

foreach ($ratings as $r) {
    $month = substr($r['rated_at'], 0, 7);
    if (!isset($monthlyWatched[$month])) continue;
    $data        = json_decode($r['providers_data'], true) ?: [];
    $flatrateIds = array_column($data['flatrate'] ?? [], 'provider_id');
    foreach ($flatrateIds as $pid) {
        if (isset($monthlyWatched[$month][$pid])) {
            $monthlyWatched[$month][$pid]++;
        }
    }
}

// ── Labels ────────────────────────────────────────────────────
$monthsFr = ['Jan','Fév','Mar','Avr','Mai','Juin','Juil','Août','Sep','Oct','Nov','Déc'];
$labels   = [];
$monthKeys = array_keys($monthlyWatched);
foreach ($monthKeys as $m) {
    [$y, $mo] = explode('-', $m);
    $labels[] = $monthsFr[(int)$mo - 1] . ' ' . substr($y, 2);
}

// ── Datasets ROI par plateforme ───────────────────────────────
$palette = [
    '#A7C7E7','#B8A7E7','#E8C07A','#4ade80',
    '#f87171','#60a5fa','#fb923c','#a78bfa',
    '#34d399','#f472b6','#facc15','#38bdf8',
];
$colorIdx = 0;

$datasets = [];
foreach ($providers as $p) {
    $pid   = (int)$p['provider_id'];
    $price = $priceMap[$pid];
    $color = $palette[$colorIdx % count($palette)];

    $history = $historyByProvider[$pid] ?? [];
    $roiData = [];
    foreach ($monthKeys as $month) {
        $monthStart  = $month . '-01';
        $monthPrice  = priceForMonth($history, $price, $monthStart);
        $watched     = $monthlyWatched[$month][$pid] ?? 0;
        if ($monthPrice === null || $monthPrice <= 0) {
            $roiData[] = null;
        } else {
            $roiData[] = round(($watched * RENTAL_PRICE) - $monthPrice, 2);
        }
    }

    $datasets[] = [
        'label'                => $p['provider_name'],
        'data'                 => $roiData,
        'borderColor'          => $color,
        'backgroundColor'      => $color . '22',
        'borderWidth'          => 2,
        'stepped'              => 'after',
        'pointRadius'          => 4,
        'pointHoverRadius'     => 6,
        'pointBackgroundColor' => $color,
        'fill'                 => false,
        'spanGaps'             => false,
        'logo'                 => $logoMap[$pid] ?? '',
        'price'                => $price,
    ];
    $colorIdx++;
}

echo json_encode([
    'success'       => true,
    'labels'        => $labels,
    'datasets'      => $datasets,
    'monthly_cost'  => round($monthlyTotal, 2),
    'rental_price'  => RENTAL_PRICE,
], JSON_UNESCAPED_UNICODE);
