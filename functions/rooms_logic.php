<?php
/**
 * FUNCTIONS/ROOMS_LOGIC.PHP
 * Cerveau de gestion des sessions (Membres, Votes, Sécurité)
 */

require_once __DIR__ . '/../functions/core_db.php';

// ---------------------------------------------------------------------------
// CRÉATION & ADMISSION
// ---------------------------------------------------------------------------

/**
 * Crée une nouvelle room et ajoute l'hôte comme membre.
 */
function create_room(string $name, string $hostId, bool $isPublic = false): string {
    $roomId = strtoupper(substr(uniqid(), -3) . '-' . rand(100, 999));

    db_begin();
    try {
        db_execute(
            'INSERT INTO rooms (id, host_id, name, status, is_public, created_at)
             VALUES (?, ?, ?, \'waiting\', ?, NOW())',
            [$roomId, $hostId, $name, $isPublic ? 1 : 0]
        );

        // L'hôte est automatiquement membre
        db_execute(
            'INSERT INTO room_members (room_id, user_id, joined_at)
             VALUES (?, ?, NOW())',
            [$roomId, $hostId]
        );

        db_commit();
    } catch (Exception $e) {
        db_rollback();
        throw $e;
    }

    return $roomId;
}

/**
 * Ajoute un utilisateur à une room (s'il n'est pas banni).
 * * @return bool  false si l'user est banni ou déjà membre
 */
function add_user_to_room(string $roomId, string $userId): bool {
    // Vérifie si banni ou déjà présent
    $member = db_fetch_one(
        'SELECT is_banned FROM room_members WHERE room_id = ? AND user_id = ?',
        [$roomId, $userId]
    );

    if ($member) {
        return $member['is_banned'] ? false : true; 
    }

    db_execute(
        'INSERT INTO room_members (room_id, user_id, is_banned, joined_at)
         VALUES (?, ?, 0, NOW())',
        [$roomId, $userId]
    );

    return true;
}

/**
 * Retire un utilisateur d'une room.
 */
function leave_room(string $roomId, string $userId): void {
    db_execute(
        'DELETE FROM room_members WHERE room_id = ? AND user_id = ? AND is_banned = 0',
        [$roomId, $userId]
    );
}

/**
 * Bannit un utilisateur et le retire de la room.
 */
function ban_user(string $roomId, string $userId): void {
    db_execute(
        'INSERT INTO room_members (room_id, user_id, is_banned, joined_at)
         VALUES (?, ?, 1, NOW())
         ON DUPLICATE KEY UPDATE is_banned = 1',
        [$roomId, $userId]
    );
}

// ---------------------------------------------------------------------------
// LECTURE
// ---------------------------------------------------------------------------

/**
 * Retourne toutes les rooms avec leurs membres et compteurs.
 */
function get_all_rooms(): array {
    $rooms = db_fetch_all(
        'SELECT r.*, u.username AS host_username,
                (SELECT COUNT(*) FROM room_members rm2 WHERE rm2.room_id = r.id AND rm2.is_banned = 0) AS member_count
         FROM rooms r
         LEFT JOIN users u ON u.id = r.host_id
         ORDER BY r.created_at DESC'
    );

    foreach ($rooms as &$room) {
        $room['members'] = array_column(
            db_fetch_all('SELECT user_id FROM room_members WHERE room_id = ? AND is_banned = 0', [$room['id']]),
            'user_id'
        );

        $room['banned_users'] = array_column(
            db_fetch_all('SELECT user_id FROM room_members WHERE room_id = ? AND is_banned = 1', [$room['id']]),
            'user_id'
        );
    }

    return $rooms;
}

/**
 * Retourne une room par son ID avec les listes d'utilisateurs.
 */
function get_room(string $roomId): ?array {
    $room = db_fetch_one(
        'SELECT r.*, u.username AS host_username,
                (SELECT COUNT(*) FROM room_members rm2 WHERE rm2.room_id = r.id AND rm2.is_banned = 0) AS member_count
         FROM rooms r
         LEFT JOIN users u ON u.id = r.host_id
         WHERE r.id = ?',
        [$roomId]
    );

    if (!$room) return null;

    $room['members'] = array_column(
        db_fetch_all('SELECT user_id FROM room_members WHERE room_id = ? AND is_banned = 0', [$roomId]),
        'user_id'
    );

    $room['banned_users'] = array_column(
        db_fetch_all('SELECT user_id FROM room_members WHERE room_id = ? AND is_banned = 1', [$roomId]),
        'user_id'
    );

    return $room;
}

/**
 * Retourne les sessions publiques auxquelles l'utilisateur n'appartient pas encore.
 */
function get_public_rooms(string $userId): array {
    return db_fetch_all(
        'SELECT r.*,
                (SELECT COUNT(*) FROM room_members rm2 WHERE rm2.room_id = r.id AND rm2.is_banned = 0) AS member_count
         FROM rooms r
         WHERE r.is_public = 1
           AND r.id NOT IN (
               SELECT room_id FROM room_members WHERE user_id = ? AND is_banned = 0
           )
         ORDER BY r.created_at DESC',
        [$userId]
    );
}

/**
 * Retourne les rooms d'un utilisateur avec le COMPTEUR DE MEMBRES réel.
 */
function get_user_rooms(string $userId): array {
    return db_fetch_all(
        'SELECT r.*, 
                (SELECT COUNT(*) FROM room_members rm2 WHERE rm2.room_id = r.id AND rm2.is_banned = 0) AS member_count
         FROM rooms r
         JOIN room_members rm ON rm.room_id = r.id
         WHERE rm.user_id = ? AND rm.is_banned = 0
         ORDER BY r.created_at DESC',
        [$userId]
    );
}

// ---------------------------------------------------------------------------
// FILMS & VOTES
// ---------------------------------------------------------------------------

/**
 * Propose un film dans une session de room.
 * @return bool  false si le film est déjà proposé
 */
function propose_movie_to_session(string $roomId, int $movieId, string $userId): bool {
    $existing = db_fetch_one(
        'SELECT 1 FROM room_proposals WHERE room_id = ? AND movie_id = ?',
        [$roomId, $movieId]
    );

    if ($existing) return false;

    db_execute(
        'INSERT INTO room_proposals (room_id, movie_id, proposed_by, proposed_at)
         VALUES (?, ?, ?, NOW())',
        [$roomId, $movieId, $userId]
    );

    return true;
}

/**
 * Ajoute ou retire le vote d'un utilisateur sur un film proposé.
 * @return bool  true = vote ajouté, false = vote retiré
 */
function toggle_movie_vote(string $roomId, int $movieId, string $userId): bool {
    $existing = db_fetch_one(
        'SELECT 1 FROM room_votes WHERE room_id = ? AND movie_id = ? AND user_id = ?',
        [$roomId, $movieId, $userId]
    );

    if ($existing) {
        db_execute(
            'DELETE FROM room_votes WHERE room_id = ? AND movie_id = ? AND user_id = ?',
            [$roomId, $movieId, $userId]
        );
        return false; 
    }

    db_execute(
        'INSERT INTO room_votes (room_id, movie_id, user_id, voted_at)
         VALUES (?, ?, ?, NOW())',
        [$roomId, $movieId, $userId]
    );
    return true;
}

/**
 * Retourne les films proposés dans une room avec leurs votes.
 */
function get_session_proposals(string $roomId): array {
    $proposals = db_fetch_all(
        'SELECT rp.movie_id, rp.movie_id AS id, rp.proposed_by, rp.proposed_at,
                m.title, m.poster, m.year
         FROM room_proposals rp
         LEFT JOIN movies m ON m.tmdb_id = rp.movie_id
         WHERE rp.room_id = ?
         ORDER BY rp.proposed_at ASC',
        [$roomId]
    );

    foreach ($proposals as &$p) {
        $p['votes'] = array_column(
            db_fetch_all(
                'SELECT user_id FROM room_votes
                 WHERE room_id = ? AND movie_id = ?',
                [$roomId, $p['movie_id']]
            ),
            'user_id'
        );
    }

    return $proposals;
}