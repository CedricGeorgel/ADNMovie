<?php
ini_set('display_errors', 0);
header('Content-Type: application/json');

require_once __DIR__ . '/../functions/utils.php';
require_once __DIR__ . '/../functions/core_db.php';
require_once __DIR__ . '/../functions/formatting.php';

$roomId = $_GET['room_id'] ?? null;
$lastId = (int)($_GET['last_id'] ?? 0);

if (!$roomId) {
    echo json_encode(['success' => false, 'messages' => []]);
    exit;
}

try {
    $rows = db_fetch_all(
        "SELECT m.id, m.user_id, m.message, m.created_at, m.reply_to_id,
                u.username, u.avatar, u.role,
                ru.username AS reply_username,
                rm.message  AS reply_message
         FROM room_messages m
         JOIN users u ON m.user_id = u.id
         LEFT JOIN room_messages rm ON rm.id = m.reply_to_id
         LEFT JOIN users ru ON ru.id = rm.user_id
         WHERE m.room_id = ? AND m.id > ?
         ORDER BY m.id ASC",
        [$roomId, $lastId]
    );

    $messages = [];
    foreach ($rows as $row) {
        $text = chat_decrypt($row['message']);

        // Résolution @pseudo → @[pseudo:userId]
        $text = preg_replace_callback('/@(\w+)/', function($m) {
            $user = db_fetch_one('SELECT id FROM users WHERE username = ?', [$m[1]]);
            return $user
                ? '@[' . $m[1] . ':' . $user['id'] . ']'
                : '@' . $m[1];
        }, $text);

        // //tv-id → carte série (avant //id et /tv-)
        $text = preg_replace_callback('/(^|[\s])\/\/tv-(\d+)/', function($m) {
            $row = db_fetch_one('SELECT title, poster, year FROM series WHERE tmdb_id = ?', [(int)$m[2]]);
            if (!$row) return $m[1] . '//tv-' . $m[2];
            return $m[1] . '//[tv-' . $m[2] . '|' . rawurlencode($row['title']) . '|' . rawurlencode($row['poster'] ?? '') . '|' . ($row['year'] ?? '') . ']';
        }, $text);

        // //id → carte film
        $text = preg_replace_callback('/(^|[\s])\/\/(\d+)/', function($m) {
            $row = db_fetch_one('SELECT title, poster, year FROM movies WHERE tmdb_id = ?', [(int)$m[2]]);
            if (!$row) return $m[1] . '//' . $m[2];
            return $m[1] . '//[' . $m[2] . '|' . rawurlencode($row['title']) . '|' . rawurlencode($row['poster'] ?? '') . '|' . ($row['year'] ?? '') . ']';
        }, $text);

        // /tv-id → lien série (avant /id)
        $text = preg_replace_callback('/(^|[\s])\/tv-(\d+)/', function($m) {
            $serie = db_fetch_one('SELECT title FROM series WHERE tmdb_id = ?', [(int)$m[2]]);
            return $m[1] . ($serie ? '/[tv-' . $m[2] . ':' . $serie['title'] . ']' : '/tv-' . $m[2]);
        }, $text);

        // /id → lien film
        $text = preg_replace_callback('/(^|[\s])\/(\d+)/', function($m) {
            $movie = db_fetch_one('SELECT title FROM movies WHERE tmdb_id = ?', [(int)$m[2]]);
            return $m[1] . ($movie ? '/[' . $m[2] . ':' . $movie['title'] . ']' : '/' . $m[2]);
        }, $text);

        $replyPreview = null;
        if ($row['reply_to_id'] && $row['reply_message']) {
            $replyText = chat_decrypt($row['reply_message']);
            $replyPreview = [
                'id'       => (int)$row['reply_to_id'],
                'username' => $row['reply_username'] ?? '?',
                'snippet'  => mb_substr(strip_tags($replyText), 0, 80),
            ];
        }

        $messages[] = [
            'id'           => $row['id'],
            'username'     => $row['username'],
            'avatar'       => $row['avatar'] ?? 'assets/default-avatar.png',
            'text'         => $text,
            'time'         => date('H:i', strtotime($row['created_at'])),
            'user_id'      => $row['user_id'],
            'role'         => $row['role'] ?? 'user',
            'reply_to_id'  => $row['reply_to_id'] ? (int)$row['reply_to_id'] : null,
            'reply_preview'=> $replyPreview,
        ];
    }

    echo json_encode(['success' => true, 'messages' => $messages]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage(), 'messages' => []]);
}