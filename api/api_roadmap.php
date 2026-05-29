<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_log("api_roadmap called, POST: " . json_encode($_POST));
require_once 'auth_helpers.php';
start_persistent_session();
require_once __DIR__ . '/../config/settings.php';
require_once __DIR__ . '/../functions/core_db.php';
require_once __DIR__ . '/../functions/auth_role_helper.php';

header('Content-Type: application/json');

// ── Lecture du body (FormData OU JSON raw) ────────────────────
$body   = !empty($_POST) ? $_POST : (json_decode(file_get_contents('php://input'), true) ?? []);
$action = $body['action'] ?? '';

// ── Accès admin uniquement ────────────────────────────────────
if (!has_role('admin')) {
    echo json_encode(['success' => false, 'message' => 'Accès refusé']);
    exit;
}

// ── Mise à jour d'un champ (status / priority / type) ─────────
if ($action === 'update_management') {
    $id    = (int)($body['id']    ?? 0);
    $field = $body['field'] ?? '';
    $value = $body['value'] ?? '';

    $allowedFields = ['status', 'priority', 'type'];
    if (!$id || !in_array($field, $allowedFields)) {
        echo json_encode(['success' => false, 'message' => 'Paramètres invalides.']);
        exit;
    }

    db_execute("UPDATE system_management SET $field = ? WHERE id = ?", [$value, $id]);
    echo json_encode(['success' => true]);
    exit;
}

// ── Édition titre / description ───────────────────────────────
if ($action === 'edit_text') {
    $id    = (int)($body['id']         ?? 0);
    $title = trim($body['title']       ?? '');
    $desc  = trim($body['description'] ?? '');

    if (!$id || empty($title)) {
        echo json_encode(['success' => false, 'message' => 'Données invalides.']);
        exit;
    }

    db_execute(
        "UPDATE system_management SET title = ?, description = ? WHERE id = ?",
        [$title, $desc, $id]
    );
    echo json_encode(['success' => true]);
    exit;
}

// ── Suppression d'un item ─────────────────────────────────────
if ($action === 'delete_management') {
    $id = (int)($body['id'] ?? 0);
    if (!$id) {
        echo json_encode(['success' => false, 'message' => 'id requis.']);
        exit;
    }
    db_execute("DELETE FROM system_management WHERE id = ?", [$id]);
    echo json_encode(['success' => true]);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Action inconnue']);