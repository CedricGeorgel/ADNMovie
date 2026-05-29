<?php
/**
 * API_RENTA_RECOMMEND.PHP
 * Recommande les meilleurs abonnements selon l'ADN utilisateur.
 * Score = similarité cosinus entre user_dna et movie_dna des films dispo sur la plateforme.
 *
 * Retourne :
 *   - best_fit      : meilleur abonnement non souscrit (ou meilleur global)
 *   - best_value    : meilleur rapport qualité/prix non souscrit
 *   - user_platforms_scores : score de chaque plateforme souscrite (pour comparaison)
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

$userId = $_SESSION['user_id'];

// ── ADN utilisateur ───────────────────────────────────────────
$userDnaRows = db_fetch_all('SELECT criterio, avg_score FROM user_dna WHERE user_id = ?', [$userId]);
if (empty($userDnaRows)) {
    echo json_encode(['success' => false, 'message' => 'ADN insuffisant — notez plus de films.']);
    exit;
}
$userDna = array_column($userDnaRows, 'avg_score', 'criterio');

// ── Plateformes souscrites ────────────────────────────────────
$user = db_fetch_one('SELECT user_platforms FROM users WHERE id = ?', [$userId]);
$subscribedIds = array_map('intval', json_decode($user['user_platforms'] ?? '[]', true) ?: []);

// ── Tous les providers SVOD actifs ───────────────────────────
$allProviders = db_fetch_all(
    "SELECT pc.provider_id, pc.provider_name, pc.monthly_price AS default_price,
            COALESCE(upp.monthly_price, pc.monthly_price) AS price
     FROM providers_config pc
     LEFT JOIN user_platform_prices upp ON upp.provider_id = pc.provider_id AND upp.user_id = ?
     WHERE pc.type = 'svod' AND pc.is_active = 1",
    [$userId]
);

// ── ADN de chaque film par plateforme ─────────────────────────
$movieDnaAll = db_fetch_all('SELECT movie_id, criterio, avg_score FROM movie_dna');
$movieDnaMap = [];
foreach ($movieDnaAll as $row) {
    $movieDnaMap[$row['movie_id']][$row['criterio']] = (float)$row['avg_score'];
}

$moviesWithProviders = db_fetch_all(
    'SELECT tmdb_id, providers_data FROM movies WHERE providers_data IS NOT NULL AND providers_data != "[]"'
);

// ── Construction du vecteur ADN moyen par plateforme ──────────
// provider_id => [criterio => [sum, count]]
$providerDnaAcc = [];
$providerFilmCount = [];

foreach ($moviesWithProviders as $m) {
    $mid = (int)$m['tmdb_id'];
    if (!isset($movieDnaMap[$mid])) continue;
    $data = json_decode($m['providers_data'], true) ?: [];
    $flatrateIds = array_column($data['flatrate'] ?? [], 'provider_id');
    foreach ($flatrateIds as $pid) {
        $providerFilmCount[$pid] = ($providerFilmCount[$pid] ?? 0) + 1;
        foreach ($movieDnaMap[$mid] as $criterio => $score) {
            $providerDnaAcc[$pid][$criterio][0] = ($providerDnaAcc[$pid][$criterio][0] ?? 0) + $score;
            $providerDnaAcc[$pid][$criterio][1] = ($providerDnaAcc[$pid][$criterio][1] ?? 0) + 1;
        }
    }
}

// Vecteur ADN moyen par plateforme
$providerAvgDna = [];
foreach ($providerDnaAcc as $pid => $criterios) {
    foreach ($criterios as $criterio => [$sum, $cnt]) {
        $providerAvgDna[$pid][$criterio] = $sum / $cnt;
    }
}

// ── Couverture directionnelle user → plateforme ───────────────
// Score = fraction du goût utilisateur effectivement couverte.
// Pour chaque critère : si user et plateforme pointent dans la
// même direction, on comptabilise min(|user|, |plateforme|).
// Directions opposées → contribution nulle (pas de pénalité).
// Résultat : une niche comme Crunchyroll ne peut pas scorer
// haut si l'utilisateur a des goûts variés hors-animé.
function coverage_score(array $user, array $platformAvg): float {
    $magnitude = 0.0;
    foreach ($user as $v) { $magnitude += abs($v); }
    if ($magnitude < 0.001) return 0.0;

    $aligned = 0.0;
    foreach ($user as $k => $uv) {
        if (abs($uv) < 0.001) continue;
        $pv = $platformAvg[$k] ?? 0.0;
        if ($uv > 0 && $pv > 0)       $aligned += min($uv, $pv);
        elseif ($uv < 0 && $pv < 0)   $aligned += min(abs($uv), abs($pv));
    }
    return $aligned / $magnitude;
}

// Logo map
$logoMap = [];
$cachePath = __DIR__ . '/../config/providers_list.json';
if (file_exists($cachePath)) {
    $cached = json_decode(file_get_contents($cachePath), true);
    foreach ($cached['providers'] ?? [] as $p) {
        $logoMap[$p['provider_id']] = $p['logo_path'] ?? '';
    }
}

// ── Résultats ─────────────────────────────────────────────────
const MIN_FILMS      = 5;   // catalogue minimal pour être candidat
const MIN_MATCH_VALUE = 0.30; // seuil minimum pour best_value

$scored = [];
foreach ($allProviders as $p) {
    $pid      = (int)$p['provider_id'];
    $count    = $providerFilmCount[$pid] ?? 0;
    $coverage = isset($providerAvgDna[$pid])
        ? coverage_score($userDna, $providerAvgDna[$pid])
        : 0.0;
    $price    = $p['price'] !== null ? (float)$p['price'] : null;

    // value_score quadratique : score²/prix — seuil 30% requis
    $valueScore = ($price && $price > 0 && $coverage >= MIN_MATCH_VALUE)
        ? round(($coverage * $coverage) / $price * 100, 3)
        : 0;

    $scored[] = [
        'provider_id'   => $pid,
        'provider_name' => $p['provider_name'],
        'logo_path'     => $logoMap[$pid] ?? '',
        'monthly_price' => $price,
        'match_score'   => round($coverage * 100, 1),
        'film_count'    => $count,
        'subscribed'    => in_array($pid, $subscribedIds),
        'value_score'   => $valueScore,
    ];
}

// Best fit non souscrit (meilleur match_score, catalogue suffisant)
$notSubscribed = array_filter($scored, fn($p) => !$p['subscribed'] && $p['film_count'] >= MIN_FILMS);
usort($notSubscribed, fn($a, $b) => $b['match_score'] <=> $a['match_score']);
$bestFit = array_values($notSubscribed)[0] ?? null;

// Best value : score quadratique, seuil 30%, prix connu
$notSubscribedValue = array_filter($notSubscribed,
    fn($p) => $p['monthly_price'] !== null && $p['match_score'] >= (MIN_MATCH_VALUE * 100)
);
usort($notSubscribedValue, fn($a, $b) => $b['value_score'] <=> $a['value_score']);
$bestValue = array_values($notSubscribedValue)[0] ?? null;

// Score des plateformes souscrites (pour affichage comparatif)
$ownedScores = array_values(array_filter($scored, fn($p) => $p['subscribed']));
usort($ownedScores, fn($a, $b) => $b['match_score'] <=> $a['match_score']);

echo json_encode([
    'success'      => true,
    'best_fit'     => $bestFit,
    'best_value'   => $bestValue,
    'owned_scores' => $ownedScores,
], JSON_UNESCAPED_UNICODE);
