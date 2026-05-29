<?php
/**
 * FRIENDS_LOGIC.PHP
 * Gestion des amitiés et des conversations de messages directs.
 */

require_once __DIR__ . '/../functions/core_db.php';

// ── AMITIÉS ──────────────────────────────────────────────────────────────────

/**
 * Retourne le statut de la relation entre deux utilisateurs.
 *
 * @return string 'none' | 'pending_sent' | 'pending_received' | 'accepted'
 */
function get_friendship_status(string $userId, string $targetId): string {
    if ($userId === $targetId) return 'none';

    $row = db_fetch_one(
        "SELECT requester_id, addressee_id, status
         FROM friendships
         WHERE (requester_id = ? AND addressee_id = ?)
            OR (requester_id = ? AND addressee_id = ?)
         LIMIT 1",
        [$userId, $targetId, $targetId, $userId]
    );

    if (!$row) return 'none';
    if ($row['status'] === 'accepted') return 'accepted';

    // En attente : on distingue envoyé vs reçu
    if ($row['requester_id'] === $userId) return 'pending_sent';
    return 'pending_received';
}

/**
 * Envoie une demande d'ami de $requesterId vers $addresseeId.
 * Retourne false si une relation existe déjà.
 */
function send_friend_request(string $requesterId, string $addresseeId): bool {
    if ($requesterId === $addresseeId) return false;

    $existing = db_fetch_one(
        "SELECT id FROM friendships
         WHERE (requester_id = ? AND addressee_id = ?)
            OR (requester_id = ? AND addressee_id = ?)
         LIMIT 1",
        [$requesterId, $addresseeId, $addresseeId, $requesterId]
    );
    if ($existing) return false;

    $affected = db_execute(
        "INSERT INTO friendships (requester_id, addressee_id, status, created_at)
         VALUES (?, ?, 'pending', NOW())",
        [$requesterId, $addresseeId]
    );
    return $affected > 0;
}

/**
 * Accepte une demande d'ami.
 * Seul l'addressee peut accepter (requester_id = $requesterId, addressee_id = $acceptorId).
 */
function accept_friend_request(string $requesterId, string $acceptorId): bool {
    $affected = db_execute(
        "UPDATE friendships SET status = 'accepted'
         WHERE requester_id = ? AND addressee_id = ? AND status = 'pending'",
        [$requesterId, $acceptorId]
    );
    return $affected > 0;
}

/**
 * Refuse ou supprime une relation d'amitié (dans les deux sens, peu importe le statut).
 */
function decline_or_remove_friend(string $userId, string $otherId): void {
    db_execute(
        "DELETE FROM friendships
         WHERE (requester_id = ? AND addressee_id = ?)
            OR (requester_id = ? AND addressee_id = ?)",
        [$userId, $otherId, $otherId, $userId]
    );
}

/**
 * Retourne la liste des amis acceptés d'un utilisateur.
 * Chaque entrée contient les données de base de l'autre utilisateur.
 *
 * @return array  [{id, username, avatar, role}, ...]
 */
function get_friends(string $userId): array {
    return db_fetch_all(
        "SELECT u.id, u.username, u.avatar, u.role
         FROM friendships f
         JOIN users u ON (
             (f.requester_id = ? AND u.id = f.addressee_id) OR
             (f.addressee_id = ? AND u.id = f.requester_id)
         )
         WHERE f.status = 'accepted'
         ORDER BY u.username ASC",
        [$userId, $userId]
    );
}

// ── MESSAGES DIRECTS ─────────────────────────────────────────────────────────

/**
 * Retourne les conversations DM d'un utilisateur, triées par dernier message DESC.
 * Pour chaque ami, on récupère : données utilisateur, aperçu du dernier message,
 * date du dernier message, nombre de messages non lus.
 *
 * @return array [{id, username, avatar, last_message, last_sent_at, unread_count}, ...]
 */
function get_dm_conversations(string $userId): array {
    // Récupère tous les amis acceptés avec leur dernier message et unread count
    $rows = db_fetch_all(
        "SELECT
            u.id,
            u.username,
            u.avatar,
            u.role,
            last_msg.body         AS last_body,
            last_msg.sent_at      AS last_sent_at,
            COALESCE(unread.cnt, 0) AS unread_count
         FROM friendships f
         JOIN users u ON (
             (f.requester_id = ? AND u.id = f.addressee_id) OR
             (f.addressee_id = ? AND u.id = f.requester_id)
         )
         LEFT JOIN (
             SELECT
                 CASE
                     WHEN sender_id = ? THEN receiver_id
                     ELSE sender_id
                 END AS friend_id,
                 body,
                 sent_at,
                 ROW_NUMBER() OVER (
                     PARTITION BY
                         LEAST(sender_id, receiver_id),
                         GREATEST(sender_id, receiver_id)
                     ORDER BY sent_at DESC
                 ) AS rn
             FROM direct_messages
             WHERE sender_id = ? OR receiver_id = ?
         ) last_msg ON last_msg.friend_id = u.id AND last_msg.rn = 1
         LEFT JOIN (
             SELECT sender_id, COUNT(*) AS cnt
             FROM direct_messages
             WHERE receiver_id = ? AND is_read = 0
             GROUP BY sender_id
         ) unread ON unread.sender_id = u.id
         WHERE f.status = 'accepted'
         ORDER BY last_msg.sent_at DESC, u.username ASC",
        [$userId, $userId, $userId, $userId, $userId, $userId]
    );

    // Décrypte le dernier message pour l'aperçu
    foreach ($rows as &$row) {
        if (!empty($row['last_body'])) {
            $decrypted = chat_decrypt($row['last_body']);
            // Tronque pour l'aperçu
            $row['last_message'] = mb_strlen($decrypted) > 60
                ? mb_substr($decrypted, 0, 60) . '…'
                : $decrypted;
        } else {
            $row['last_message'] = '';
        }
        unset($row['last_body']);
    }
    unset($row);

    return $rows;
}

/**
 * Retourne le nombre total de messages directs non lus pour un utilisateur.
 */
function get_unread_dm_count(string $userId): int {
    $row = db_fetch_one(
        "SELECT COUNT(*) AS cnt FROM direct_messages
         WHERE receiver_id = ? AND is_read = 0",
        [$userId]
    );
    return (int)($row['cnt'] ?? 0);
}
