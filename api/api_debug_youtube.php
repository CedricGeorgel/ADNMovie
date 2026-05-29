<?php
session_start();
require_once __DIR__ . '/../config/settings.php';
require_once __DIR__ . '/../functions/core_db.php';

if (!isset($_SESSION['user_id'])) { die('Non connecté'); }
$me = db_fetch_one('SELECT role FROM users WHERE id = ?', [$_SESSION['user_id']]);
if (!$me || $me['role'] !== 'admin') { die('Accès refusé'); }

$title = $_GET['title'] ?? 'Tuesday';
$year  = (int)($_GET['year'] ?? 0);

header('Content-Type: text/plain; charset=utf-8');

echo "=== DEBUG YOUTUBE API ===\n\n";
echo "Titre  : {$title}\n";
echo "Année  : {$year}\n";
echo "API_KEY définie : " . (defined('API_KEY') ? 'OUI' : 'NON') . "\n";
echo "API_KEY vide    : " . (defined('API_KEY') && empty(API_KEY) ? 'OUI' : 'NON') . "\n\n";

if (!defined('API_KEY') || empty(API_KEY)) {
    die("ERREUR : API_KEY non définie ou vide\n");
}

$q   = urlencode($title . ($year ? " {$year}" : ''));
$url = "https://www.googleapis.com/youtube/v3/search?part=snippet&q={$q}"
     . "&type=video&videoType=movie&maxResults=3&key=" . API_KEY;

echo "URL appelée :\n{$url}\n\n";

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 10,
    CURLOPT_USERAGENT      => 'Moovie-App/2.0',
    CURLOPT_HTTPHEADER     => ['Referer: https://adnmovie.fr/'],
]);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error    = curl_error($ch);
curl_close($ch);

echo "HTTP Code : {$httpCode}\n";
if ($error) echo "cURL error : {$error}\n";
echo "\nRéponse brute :\n";
$data = json_decode($response, true);
echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";

if (!empty($data['items'])) {
    echo "=== RÉSULTATS ===\n";
    foreach ($data['items'] as $i => $item) {
        $vid   = $item['id']['videoId'] ?? '(pas un video)';
        $titreV = $item['snippet']['title'] ?? '';
        echo "#{$i} → {$vid} | {$titreV}\n";
    }
} else {
    echo "=== AUCUN RÉSULTAT (ou erreur API) ===\n";
    if (isset($data['error'])) {
        echo "Erreur API : " . $data['error']['message'] . "\n";
        echo "Code       : " . $data['error']['code'] . "\n";
    }
}

// Vérif BDD
echo "\n=== CACHE EN BDD (movies) ===\n";
$row = db_fetch_one("SELECT tmdb_id, title, youtube_id FROM movies WHERE title LIKE ? LIMIT 5", ["%{$title}%"]);
if ($row) {
    echo "Film trouvé : {$row['title']} (tmdb_id={$row['tmdb_id']})\n";
    echo "youtube_id  : " . var_export($row['youtube_id'], true) . "\n";
} else {
    echo "Aucun film trouvé avec ce titre en BDD\n";
}
