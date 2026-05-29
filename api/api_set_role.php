<?php
/**
 * API_SET_ROLE.PHP
 * Modifie le rôle d'un utilisateur (admin uniquement).
 * POST : target_id, role (user|moderator|admin)
 */
require_once 'auth_helpers.php';
start_persistent_session();
require_once __DIR__ . '/../functions/core_db.php';
require_once __DIR__ . '/../functions/auth_role_helper.php';

header('Content-Type: application/json');

if (!has_role('admin')) {
    echo json_encode(['success' => false, 'message' => 'Accès refusé.']);
    exit;
}

$targetId = $_POST['target_id'] ?? '';
$newRole  = $_POST['role']      ?? '';

$allowedRoles = ['user', 'moderator', 'admin'];
if (!$targetId || !in_array($newRole, $allowedRoles, true)) {
    echo json_encode(['success' => false, 'message' => 'Paramètres invalides.']);
    exit;
}

$target = db_fetch_one('SELECT id, role FROM users WHERE id = ?', [$targetId]);
if (!$target) {
    echo json_encode(['success' => false, 'message' => 'Utilisateur introuvable.']);
    exit;
}

if ($target['role'] === 'superadmin') {
    echo json_encode(['success' => false, 'message' => 'Le rôle superadmin ne peut pas être modifié ici.']);
    exit;
}

db_execute('UPDATE users SET role = ? WHERE id = ?', [$newRole, $targetId]);

echo json_encode([
    'success' => true,
    'message' => 'Rôle mis à jour.',
    'role'    => $newRole,
]);
