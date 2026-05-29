<?php
/**
 * NOTIFICATIONS_LOGIC.PHP
 * Fonctions de création et lecture des notifications internes.
 *
 * Schéma de la table attendu :
 *   notifications(id, user_id, type ENUM('mention','comment','message','report'),
 *                 source_id, title, link, is_read, created_at)
 *   UNIQUE KEY uniq_notif (user_id, type, source_id)
 */

require_once __DIR__ . '/../functions/core_db.php';
require_once __DIR__ . '/../functions/push_logic.php';

// ── CRÉATION ─────────────────────────────────────────────────────────────────

/**
 * Notifie un utilisateur mentionné (@pseudo) dans le chat d'une session.
 * Retourne l'user_id notifié (pour exclure de notify_room_message), ou null.
 */
function notify_mention(string $mentionedUsername, string $fromUserId, string $fromUsername, string $roomId): ?string {
    $target = db_fetch_one('SELECT id FROM users WHERE username = ?', [$mentionedUsername]);
    if (!$target || $target['id'] === $fromUserId) return null;

    db_execute(
        "INSERT INTO notifications (user_id, type, source_id, title, link, is_read, created_at)
         VALUES (?, 'mention', ?, ?, ?, 0, NOW())
         ON DUPLICATE KEY UPDATE title = VALUES(title), created_at = NOW(), is_read = 0",
        [$target['id'], $roomId, "{$fromUsername} vous a mentionné", "session.php?id={$roomId}"]
    );
    send_push_to_user($target['id'], 'ADN Movie', "{$fromUsername} vous a mentionné", "session.php?id={$roomId}");

    return $target['id'];
}

/**
 * Notifie tous les membres actifs d'une room qu'un nouveau message a été posté.
 * Utilise ON DUPLICATE KEY pour ne garder qu'une seule notif non-lue par room.
 *
 * @param string[] $excludeUserIds  IDs déjà notifiés par mention → pas de doublon
 */
function notify_room_message(string $roomId, string $senderUserId, array $excludeUserIds = []): void {
    $members = db_fetch_all(
        'SELECT user_id FROM room_members WHERE room_id = ? AND user_id != ? AND is_banned = 0',
        [$roomId, $senderUserId]
    );

    foreach ($members as $m) {
        if (in_array($m['user_id'], $excludeUserIds, true)) continue;

        // Push uniquement si aucune notif non-lue n'existe déjà pour cette room
        $alreadyUnread = db_fetch_one(
            "SELECT 1 FROM notifications WHERE user_id = ? AND type = 'message' AND source_id = ? AND is_read = 0",
            [$m['user_id'], $roomId]
        );

        db_execute(
            "INSERT INTO notifications (user_id, type, source_id, title, link, is_read, created_at)
             VALUES (?, 'message', ?, 'Nouveau message dans votre session', ?, 0, NOW())
             ON DUPLICATE KEY UPDATE created_at = NOW(), is_read = 0",
            [$m['user_id'], $roomId, "session.php?id={$roomId}"]
        );

        if (!$alreadyUnread) {
            send_push_to_user($m['user_id'], 'ADN Movie', 'Nouveau message dans votre session', "session.php?id={$roomId}");
        }
    }
}

/**
 * Convertit un ref_id (M-123, TV-123, S-123-1, E-123-1-2) en URL fiche.php.
 */
function ref_id_to_url(string $refId): string {
    if (str_starts_with($refId, 'M-'))  return 'fiche.php?id=' . substr($refId, 2);
    if (str_starts_with($refId, 'TV-')) return 'fiche.php?id=' . substr($refId, 3) . '&type=tv';
    if (str_starts_with($refId, 'S-')) {
        [$tmdb, $s] = array_pad(explode('-', substr($refId, 2), 2), 2, '0');
        return "fiche.php?id={$tmdb}&type=tv&season={$s}";
    }
    if (str_starts_with($refId, 'E-')) {
        [$tmdb, $s, $e] = array_pad(explode('-', substr($refId, 2), 3), 3, '0');
        return "fiche.php?id={$tmdb}&type=tv&season={$s}&episode={$e}";
    }
    return 'fiche.php';
}

/**
 * Retourne un titre lisible pour un ref_id donné.
 */
function ref_id_to_title(string $refId): string {
    if (str_starts_with($refId, 'M-')) {
        $row = db_fetch_one('SELECT title FROM movies WHERE tmdb_id = ?', [(int)substr($refId, 2)]);
        return $row['title'] ?? 'un film';
    }
    if (str_starts_with($refId, 'TV-')) return 'une série';
    if (str_starts_with($refId, 'S-'))  return 'une saison';
    if (str_starts_with($refId, 'E-'))  return 'un épisode';
    return 'un contenu';
}

/**
 * Détecte si un texte contient un alias @modération.
 */
function has_moderation_mention(string $text): bool {
    return (bool) preg_match('/@(moderation|mod[eé]ration|modo|mod[eé]rateur)\b/iu', $text);
}

/**
 * Notifie tous les modérateurs/admins qu'ils ont été mentionnés.
 * $sourceId   : identifiant unique de la source (refId, roomId…)
 * $link       : URL cible de la notification
 */
function notify_moderation_mention(string $fromUsername, string $sourceId, string $link): void {
    $staff = db_fetch_all("SELECT id FROM users WHERE role IN ('moderator','admin','superadmin')");
    if (empty($staff)) return;

    foreach ($staff as $member) {
        db_execute(
            "INSERT INTO notifications (user_id, type, source_id, title, link, is_read, created_at)
             VALUES (?, 'mention', ?, ?, ?, 0, NOW())
             ON DUPLICATE KEY UPDATE title = VALUES(title), created_at = NOW(), is_read = 0",
            [$member['id'], 'mod-' . $sourceId, "{$fromUsername} a mentionné la modération", $link]
        );
        send_push_to_user($member['id'], 'ADN Movie', "{$fromUsername} a mentionné la modération", $link);
    }
}

/**
 * Notifie les anciens commentateurs qu'une nouvelle analyse vient d'être postée
 * sur un film/série/saison/épisode qu'ils ont aussi commenté. Une seule notif non-lue par ref.
 */
function notify_comment(string $refId, string $commenterUserId, string $movieTitle): void {
    $previous = db_fetch_all(
        'SELECT DISTINCT user_id FROM movie_comments WHERE movie_id = ? AND user_id != ?',
        [$refId, $commenterUserId]
    );

    $link = ref_id_to_url($refId);
    foreach ($previous as $p) {
        db_execute(
            "INSERT INTO notifications (user_id, type, source_id, title, link, is_read, created_at)
             VALUES (?, 'comment', ?, ?, ?, 0, NOW())
             ON DUPLICATE KEY UPDATE title = VALUES(title), created_at = NOW(), is_read = 0",
            [$p['user_id'], $refId, "Nouvelle analyse sur « {$movieTitle} »", $link]
        );
        send_push_to_user($p['user_id'], 'ADN Movie', "Nouvelle analyse sur « {$movieTitle} »", $link);
    }
}

/**
 * Notifie tous les admins qu'un utilisateur vient de soumettre un bug report
 * ou une feature request. Une notif distincte par soumission (pas d'ON DUPLICATE).
 */
function notify_admins_report(string $reporterUsername, string $reportType, string $reportTitle, int $reportId): void {
    $admins = db_fetch_all("SELECT id FROM users WHERE role IN ('admin', 'superadmin')");
    if (empty($admins)) return;

    $typeLabel = ($reportType === 'bug') ? 'bug' : 'feature';
    $title     = "{$reporterUsername} a soumis un {$typeLabel} : {$reportTitle}";

    foreach ($admins as $admin) {
        db_execute(
            "INSERT INTO notifications (user_id, type, source_id, title, link, is_read, created_at)
             VALUES (?, 'report', ?, ?, 'admin.php', 0, NOW())",
            [$admin['id'], (string)$reportId, $title]
        );
    }
}

/**
 * Notifie admins + modérateurs qu'un contenu vient d'être signalé.
 */
function notify_mods_signal(string $reporterUsername, string $entityType, int $reportId): void {
    $staff = db_fetch_all("SELECT id FROM users WHERE role IN ('admin','superadmin','moderator')");
    if (empty($staff)) return;

    $labels = ['comment' => 'commentaire', 'content' => 'liste/critique', 'movie' => 'film'];
    $label  = $labels[$entityType] ?? $entityType;
    $title  = "{$reporterUsername} a signalé un {$label}";

    foreach ($staff as $member) {
        db_execute(
            "INSERT INTO notifications (user_id, type, source_id, title, link, is_read, created_at)
             VALUES (?, 'report', ?, ?, 'moderation.php', 0, NOW())",
            [$member['id'], (string)$reportId, $title]
        );
    }
}

// ── LECTURE & MARQUAGE ───────────────────────────────────────────────────────

/**
 * Retourne le nombre de notifications non lues pour un utilisateur.
 */
function get_unread_count(string $userId): int {
    $row = db_fetch_one(
        'SELECT COUNT(*) AS cnt FROM notifications WHERE user_id = ? AND is_read = 0',
        [$userId]
    );
    return (int)($row['cnt'] ?? 0);
}

/**
 * Marque comme lues les notifications de type message/mention liées à une room.
 */
function mark_read_for_room(string $userId, string $roomId): void {
    db_execute(
        "UPDATE notifications SET is_read = 1
         WHERE user_id = ? AND source_id = ? AND type IN ('message', 'mention') AND is_read = 0",
        [$userId, $roomId]
    );
}

/**
 * Marque comme lues les notifications de type comment liées à un film.
 */
function mark_read_for_movie(string $userId, string $refId): void {
    db_execute(
        "UPDATE notifications SET is_read = 1
         WHERE user_id = ? AND source_id = ? AND type = 'comment' AND is_read = 0",
        [$userId, $refId]
    );
}

/**
 * Marque comme lues toutes les notifications de type report (pour les admins).
 */
function mark_read_for_admin(string $userId): void {
    db_execute(
        "UPDATE notifications SET is_read = 1
         WHERE user_id = ? AND type = 'report' AND is_read = 0",
        [$userId]
    );
}

// ── AMIS & MESSAGES DIRECTS ──────────────────────────────────────────────────

/**
 * Notifie un utilisateur qu'il a reçu une demande d'ami.
 */
function notify_friend_request(string $fromUserId, string $fromUsername, string $toUserId): void {
    db_execute(
        "INSERT INTO notifications (user_id, type, source_id, title, link, is_read, created_at)
         VALUES (?, 'mention', ?, ?, ?, 0, NOW())
         ON DUPLICATE KEY UPDATE title = VALUES(title), created_at = NOW(), is_read = 0",
        [$toUserId, $fromUserId, "{$fromUsername} vous a envoyé une demande d'ami", "adn.php?id={$fromUserId}"]
    );
    send_push_to_user($toUserId, 'ADN Movie', "{$fromUsername} vous a envoyé une demande d'ami", "adn.php?id={$fromUserId}");
}

/**
 * Notifie un utilisateur que sa demande d'ami a été acceptée.
 */
function notify_friend_accepted(string $fromUserId, string $fromUsername, string $toUserId): void {
    db_execute(
        "INSERT INTO notifications (user_id, type, source_id, title, link, is_read, created_at)
         VALUES (?, 'mention', ?, ?, ?, 0, NOW())
         ON DUPLICATE KEY UPDATE title = VALUES(title), created_at = NOW(), is_read = 0",
        [$toUserId, $fromUserId, "{$fromUsername} a accepté votre demande d'ami", "chat.php?with={$fromUserId}"]
    );
    send_push_to_user($toUserId, 'ADN Movie', "{$fromUsername} a accepté votre demande d'ami", "chat.php?with={$fromUserId}");
}

/**
 * Notifie les amis du voteur si le film/série qu'il vient de noter
 * présente une résonance ADN >= 80% avec leur profil.
 *
 * @param string $voterId      Utilisateur qui vient de voter
 * @param int    $contentId    TMDB ID du film ou de la série
 * @param array  $voteScores   Scores soumis (clé = criterio, valeur = float|null)
 * @param string $type         'movie' ou 'series'
 */
function notify_friends_dna_vote(string $voterId, int $contentId, array $voteScores, string $type = 'movie'): void {
    $criteria = ['complexite','previsibilite','intensite','malaise',
                 'stylisation','dynamique','depaysement','coherence'];

    // Norme du vecteur du vote soumis
    $normVote = 0;
    foreach ($criteria as $c) { $v = (float)($voteScores[$c] ?? 0); $normVote += $v * $v; }
    if ($normVote == 0) return;

    require_once __DIR__ . '/friends_logic.php';
    $friends = get_friends($voterId);
    if (empty($friends)) return;

    $dnaTable     = $type === 'series' ? 'user_series_dna' : 'user_dna';
    $sourcePrefix = $type === 'series' ? 'fvs_' : 'fvm_'; // friend_vote_series / friend_vote_movie
    $label        = $type === 'series' ? 'une série' : 'un film';
    $link         = 'fiche.php?id=' . $contentId . ($type === 'series' ? '&type=tv' : '');

    $voterName = db_fetch_one('SELECT username FROM users WHERE id = ?', [$voterId])['username'] ?? 'Un Spécimen';

    foreach ($friends as $friend) {
        $friendId = $friend['id'];

        // L'ami doit avoir au moins 10 votes sur ce type pour avoir un ADN significatif
        $maxVotes = (int)(db_fetch_one(
            "SELECT COALESCE(MAX(vote_count), 0) AS n FROM $dnaTable WHERE user_id = ?",
            [$friendId]
        )['n'] ?? 0);
        if ($maxVotes < 10) continue;

        // ADN de l'ami
        $friendDnaRows = db_fetch_all(
            "SELECT criterio, avg_score FROM $dnaTable WHERE user_id = ?",
            [$friendId]
        );
        if (empty($friendDnaRows)) continue;
        $friendDna = array_column($friendDnaRows, 'avg_score', 'criterio');

        // Similarité cosinus entre le vote soumis et l'ADN de l'ami
        $dot = $normFriend = 0;
        foreach ($criteria as $c) {
            $a = (float)($voteScores[$c] ?? 0);
            $b = (float)($friendDna[$c] ?? 0);
            $dot       += $a * $b;
            $normFriend += $b * $b;
        }
        if ($normFriend == 0) continue;
        $cosine = $dot / (sqrt($normVote) * sqrt($normFriend));
        $pct    = round(($cosine + 1) / 2 * 100, 1);
        if ($pct < 80) continue;

        $title    = "{$voterName} a noté {$label} dont l'ADN résonne à {$pct}% avec le vôtre";
        $sourceId = $sourcePrefix . $voterId . '_' . $contentId;

        db_execute(
            "INSERT INTO notifications (user_id, type, source_id, title, link, is_read, created_at)
             VALUES (?, 'mention', ?, ?, ?, 0, NOW())
             ON DUPLICATE KEY UPDATE title = VALUES(title), created_at = NOW(), is_read = 0",
            [$friendId, $sourceId, $title, $link]
        );
        send_push_to_user($friendId, 'ADN Movie', $title, $link);
    }
}