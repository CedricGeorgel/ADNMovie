<?php
/**
 * API_PREVIEW_MD.PHP
 * Retourne le rendu HTML d'un texte markdown (render_md).
 * Utilisé pour la prévisualisation live dans content-edit.php.
 */
require_once __DIR__ . '/../api/auth_helpers.php';
start_persistent_session();
require_once __DIR__ . '/../functions/utils.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['html' => '']); exit;
}

$raw = $_POST['body'] ?? '';
echo json_encode(['html' => render_md($raw)]);
