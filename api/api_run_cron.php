<?php
/**
 * API_RUN_CRON.PHP
 * Déclenche manuellement le cron master depuis l'interface admin.
 */
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../config/settings.php';
require_once __DIR__ . '/../functions/core_db.php';

if (!isset($_SESSION['user_id'])) { echo json_encode(['success'=>false,'message'=>'Non connecté']); exit; }
$me = db_fetch_one('SELECT role FROM users WHERE id = ?', [$_SESSION['user_id']]);
if (!$me || !in_array($me['role'], ['admin','moderator'])) { echo json_encode(['success'=>false,'message'=>'Accès refusé']); exit; }

$cronPath = __DIR__ . '/../config/cron_master.php';
if (!file_exists($cronPath)) { echo json_encode(['success'=>false,'message'=>'cron_master.php introuvable']); exit; }

try {
    ob_start();
    require $cronPath;
    ob_end_clean();
    echo json_encode(['success'=>true, 'message'=>'Exécution terminée']);
} catch (\Throwable $e) {
    ob_end_clean();
    echo json_encode(['success'=>false, 'message'=>$e->getMessage()]);
}
