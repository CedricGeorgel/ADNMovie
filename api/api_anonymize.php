<?php
/**
 * API_ANONYMIZE.PHP
 * Anonymise un compte :
 * - Transfère les données statistiques sous un ghostId
 * - Anonymise le contenu visible (commentaires, messages)
 * - Supprime le user réel (CASCADE sur le reste)
 * - Crée un profil fantôme inaccessible
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

$userId  = $_SESSION['user_id'];
$ghostId = 'GHOST_' . strtoupper(substr(md5(uniqid('', true)), 0, 8));
$pdo     = getPDO();

// ── Politique par table ───────────────────────────────────────────────────────
$tablePolicy = [
    'ratings'                 => 'transfer',
    'user_dna'                => 'transfer',
    'user_dna_recent'         => 'ignore',
    'recommendation_feedback' => 'ignore',
    'user_badges'             => 'ignore',
    'room_members'            => 'ignore',
    'event_attendees'         => 'ignore',
    'movie_comments'          => 'anonymize',
    'room_messages'           => 'anonymize',
    'rooms'                   => 'ignore',
    'system_management'       => 'ignore',
    'notifications'           => 'ignore',
];

$textColumns = [
    'movie_comments' => ['content' => '[Commentaire supprimé]'],
    'room_messages'  => ['message' => '[Message supprimé]'],
];

try {
    db_begin();

    foreach ($tablePolicy as $table => $policy) {
        if ($policy === 'ignore') continue;

        $rows = db_fetch_all(
            "SELECT * FROM `$table` WHERE user_id = ?",
            [$userId]
        );

        if (empty($rows)) continue;

        if ($policy === 'transfer') {
            foreach ($rows as $row) {
                $row['user_id'] = $ghostId;
                $cols    = array_keys($row);
                $holders = array_fill(0, count($cols), '?');
                $sql = "INSERT IGNORE INTO `$table` ("
                     . implode(', ', array_map(fn($c) => "`$c`", $cols))
                     . ") VALUES (" . implode(', ', $holders) . ")";
                db_execute($sql, array_values($row));
            }
        }

        if ($policy === 'anonymize') {
            // Remplace user_id par ghostId + efface le contenu textuel
            $sets   = ['user_id = ?'];
            $params = [$ghostId];
            foreach (($textColumns[$table] ?? []) as $col => $replacement) {
                $sets[]   = "`$col` = ?";
                $params[] = $replacement;
            }
            $params[] = $userId;
            db_execute(
                "UPDATE `$table` SET " . implode(', ', $sets) . " WHERE user_id = ?",
                $params
            );
        }
    }

    // ── Suppression du user réel (CASCADE sur FK) ─────────────────────────
    db_execute('DELETE FROM users WHERE id = ?', [$userId]);

    // ── Création du profil fantôme ────────────────────────────────────────
    // is_anonymized = 1 bloquera l'accès profil côté PHP
    db_execute(
        'INSERT INTO users (id, username, email, avatar, is_anonymized, created_at, last_login)
         VALUES (?, ?, NULL, NULL, 1, NOW(), NOW())',
        [$ghostId, 'Utilisateur supprimé #' . substr($ghostId, -4)]
    );

    db_commit();

} catch (Exception $e) {
    db_rollback();
    echo json_encode(['success' => false, 'message' => 'Erreur : ' . $e->getMessage()]);
    exit;
}

logout();
echo json_encode(['success' => true]);