<?php
require_once 'auth_helpers.php';
start_persistent_session();
header('Content-Type: application/json');
require_once __DIR__ . '/../functions/core_db.php';
require_once __DIR__ . '/../functions/utils.php';
require_once __DIR__ . '/../functions/moderation.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Session expirée.']);
    exit;
}

$userId   = $_SESSION['user_id'];
$movieId  = trim($_POST['movie_id']  ?? '');
$rawContent = trim($_POST['content']  ?? '');
$parentId = (int)($_POST['parent_id'] ?? 0) ?: null;

// Censure
$censorResult    = censor_content($rawContent);
$content         = $censorResult['content'];
$wasCensored     = $censorResult['was_censored'];
$originalContent = $wasCensored ? $censorResult['original'] : null;

if ($movieId === '' || empty($content)) {
    echo json_encode(['success' => false, 'message' => 'Données invalides.']);
    exit;
}

// Valide que le parent existe bien dans le même film (profondeur illimitée)
if ($parentId) {
    $parentRow = db_fetch_one(
        'SELECT id FROM movie_comments WHERE id = ? AND movie_id = ?',
        [$parentId, $movieId]
    );
    if (!$parentRow) $parentId = null;
}

try {
    db_execute(
        'INSERT INTO movie_comments (movie_id, user_id, content, parent_id, is_censored, original_content, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())',
        [$movieId, $userId, $content, $parentId, $wasCensored ? 1 : 0, $originalContent]
    );

    // Notifications (non bloquantes)
    try {
        require_once __DIR__ . '/../functions/notifications_logic.php';
        $movieTitle = ref_id_to_title($movieId);
        notify_comment($movieId, $userId, $movieTitle);

        if (has_moderation_mention($content)) {
            $sender = db_fetch_one('SELECT username FROM users WHERE id = ?', [$userId]);
            notify_moderation_mention(
                $sender['username'] ?? 'Quelqu\'un',
                $movieId,
                ref_id_to_url($movieId)
            );
        }
    } catch (Exception $ne) {
        error_log('comment notifications failed: ' . $ne->getMessage());
    }

    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Erreur BDD : ' . $e->getMessage()]);
}