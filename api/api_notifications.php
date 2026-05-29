<?php
/**
 * API_NOTIFICATIONS.PHP
 * GET               → comptage non-lus (polling 15s)
 * GET ?action=list  → liste complète (ouverture du panneau)
 * POST mark_one     → marque une notif comme lue
 * POST mark_all     → marque toutes comme lues
 */

require_once 'auth_helpers.php';
start_persistent_session();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../functions/core_db.php';
require_once __DIR__ . '/../functions/notifications_logic.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => true, 'count' => 0, 'notifications' => []]);
    exit;
}

$userId = $_SESSION['user_id'];

// ── POST ────────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'mark_one' && !empty($_POST['id'])) {
        db_execute(
            'UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?',
            [(int)$_POST['id'], $userId]
        );
        echo json_encode(['success' => true, 'count' => get_unread_count($userId)]);
        exit;
    }

    if ($action === 'mark_all') {
        db_execute('UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0', [$userId]);
        echo json_encode(['success' => true, 'count' => 0]);
        exit;
    }

    echo json_encode(['success' => false]);
    exit;
}

// ── GET : liste complète ─────────────────────────────────────────────────────
if (($_GET['action'] ?? '') === 'list') {
    // On ne remonte que les notifications non lues — les lues disparaissent du panneau
    $rows = db_fetch_all(
        "SELECT id, type, title, link, is_read, created_at
         FROM notifications
         WHERE user_id = ? AND is_read = 0
         ORDER BY created_at DESC
         LIMIT 20",
        [$userId]
    );

    $icons = [
        'mention' => '💬',
        'comment' => '🎬',
        'message' => '✉️',
        'report'  => '🐞',
    ];

    $now = new DateTime();
    $notifs = array_map(function ($r) use ($icons, $now) {
        $dt   = new DateTime($r['created_at']);
        $diff = $now->getTimestamp() - $dt->getTimestamp();

        if ($diff < 60)          $ago = "À l'instant";
        elseif ($diff < 3600)    $ago = floor($diff / 60) . ' min';
        elseif ($diff < 86400)   $ago = floor($diff / 3600) . 'h';
        else                     $ago = floor($diff / 86400) . 'j';

        return [
            'id'      => (int)$r['id'],
            'icon'    => $icons[$r['type']] ?? '🔔',
            'title'   => $r['title'],
            'link'    => $r['link'],
            'is_read' => (bool)$r['is_read'],
            'ago'     => $ago,
        ];
    }, $rows);

    echo json_encode([
        'success'       => true,
        'count'         => get_unread_count($userId),
        'notifications' => $notifs,
    ]);
    exit;
}

// ── GET : comptage seul (polling) ────────────────────────────────────────────
echo json_encode(['success' => true, 'count' => get_unread_count($userId)]);