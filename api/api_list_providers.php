<?php
session_start();
require_once __DIR__ . '/../config/settings.php';
require_once __DIR__ . '/../functions/core_db.php';

if (!isset($_SESSION['user_id'])) { die('Non connecté'); }
$me = db_fetch_one('SELECT role FROM users WHERE id = ?', [$_SESSION['user_id']]);
if (!$me || !in_array($me['role'], ['admin','moderator'])) { die('Accès refusé'); }

$rows = db_fetch_all("SELECT providers_data FROM movies WHERE providers_data IS NOT NULL AND providers_data != '[]' LIMIT 1000");
$providers = [];
foreach ($rows as $row) {
    $data = json_decode($row['providers_data'], true) ?: [];
    foreach (['flatrate','buy','rent'] as $type) {
        foreach ($data[$type] ?? [] as $p) {
            $id = $p['provider_id'];
            if (!isset($providers[$id])) {
                $providers[$id] = $p['provider_name'];
            }
        }
    }
}
ksort($providers);
header('Content-Type: text/plain; charset=utf-8');
echo "ID\tNom\n";
echo str_repeat("-", 40) . "\n";
foreach ($providers as $id => $name) {
    echo "{$id}\t{$name}\n";
}
