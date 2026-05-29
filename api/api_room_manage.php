<?php
require_once 'auth_helpers.php'; 
start_persistent_session();
ini_set('display_errors', 0);
error_reporting(E_ALL);

require_once __DIR__ . '/../config/settings.php';
require_once __DIR__ . '/../functions/core_db.php';
require_once __DIR__ . '/../functions/utils.php';
require_once __DIR__ . '/../functions/rooms_logic.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Non connecté']);
    exit;
}

$action = $_POST['action'] ?? '';
$userId = $_SESSION['user_id'];
$roomId = $_POST['room_id'] ?? '';

function touch_room_activity(string $roomId): void {
    db_execute('UPDATE rooms SET last_activity_at = NOW() WHERE id = ?', [$roomId]);
}

if ($action === 'create') {
    $name = $_POST['name'] ?? 'Ma Session';

    $isPublic = !empty($_POST['is_public']) && $_POST['is_public'] === '1';
    $roomId = create_room($name, $userId, $isPublic);
    echo json_encode(['success' => (bool)$roomId, 'room_id' => $roomId]);
    exit;
}

$room = db_fetch_one('SELECT * FROM rooms WHERE id = ?', [$roomId]);

if (!$room) {
    echo json_encode(['success' => false, 'message' => 'Room introuvable']);
    exit;
}

$isHost = ($room['host_id'] === $userId);

switch ($action) {

    case 'join':
        $result = add_user_to_room($roomId, $userId);
        if ($result) touch_room_activity($roomId);
        echo json_encode(['success' => (bool)$result, 'message' => $result ? null : 'Accès refusé ou utilisateur banni.']);
        break;

    case 'remove_proposal':
        if (!$isHost) { echo json_encode(['success' => false, 'message' => 'Accès refusé']); break; }
        $movieId = (int)($_POST['movie_id'] ?? 0);
        if (!$movieId) { echo json_encode(['success' => false, 'message' => 'Film invalide']); break; }
        db_execute('DELETE FROM room_proposals WHERE room_id = ? AND movie_id = ?', [$roomId, $movieId]);
        touch_room_activity($roomId);
        echo json_encode(['success' => true]);
        break;

    case 'propose_movie':
        $movieId = (int)($_POST['movie_id'] ?? 0);
        $movie   = get_movie_smart($movieId);
        if (!$movie) { echo json_encode(['success' => false, 'message' => 'Film introuvable']); break; }
        $added = propose_movie_to_session($roomId, $movieId, $userId);
        if ($added) touch_room_activity($roomId);
        echo json_encode(['success' => $added, 'message' => $added ? null : 'Déjà proposé']);
        break;

    case 'toggle_vote':
        $movieId = (int)($_POST['movie_id'] ?? 0);
        $voted   = toggle_movie_vote($roomId, $movieId, $userId);
        touch_room_activity($roomId);
        echo json_encode(['success' => true, 'voted' => $voted]);
        break;

    case 'kick':
    case 'ban':
        if (!$isHost) { echo json_encode(['success' => false, 'message' => 'Accès refusé']); exit; }
        $targetId = $_POST['target_id'] ?? '';
        if ($action === 'ban') ban_user($roomId, $targetId);
        else leave_room($roomId, $targetId);
        echo json_encode(['success' => true]);
        break;

    case 'update_description':
        if (!$isHost) { echo json_encode(['success' => false, 'message' => 'Accès refusé']); break; }
        $desc = trim($_POST['description'] ?? '');
        db_execute('UPDATE rooms SET description = ? WHERE id = ?', [$desc ?: null, $roomId]);
        echo json_encode(['success' => true]);
        break;

    case 'close':
        if (!$isHost) { echo json_encode(['success' => false, 'message' => 'Accès refusé']); exit; }
        try {
            db_begin();
            db_execute('DELETE FROM rooms WHERE id = ?', [$roomId]);
            db_commit();
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            db_rollback();
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    case 'create_event':
        if (!$isHost) { echo json_encode(['success' => false, 'message' => 'Accès refusé']); exit; }
        try {
            $movieId = (int)($_POST['movie_id'] ?? 0);
            $eventId = 'EVT-' . strtoupper(uniqid());
            db_begin();
            db_execute(
                'INSERT INTO room_events (id, room_id, movie_id, event_date, event_time, location, created_by, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, NOW())',
                [$eventId, $roomId, $movieId ?: null, $_POST['date'] ?? null, $_POST['time'] ?? null, $_POST['location'] ?? 'Non défini', $userId]
            );
            db_execute('INSERT INTO event_attendees (event_id, user_id, joined_at) VALUES (?, ?, NOW())', [$eventId, $userId]);
            db_commit();
            touch_room_activity($roomId);
            echo json_encode(['success' => true, 'event_id' => $eventId]);
        } catch (Exception $e) {
            db_rollback();
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    case 'toggle_attendance':
        $eventId   = $_POST['event_id'] ?? '';
        $event     = db_fetch_one('SELECT id FROM room_events WHERE id = ?', [$eventId]);
        if (!$event) { echo json_encode(['success' => false, 'message' => 'Événement introuvable']); break; }
        $attending = db_fetch_one('SELECT 1 FROM event_attendees WHERE event_id = ? AND user_id = ?', [$eventId, $userId]);
        if ($attending) {
            db_execute('DELETE FROM event_attendees WHERE event_id = ? AND user_id = ?', [$eventId, $userId]);
            echo json_encode(['success' => true, 'attending' => false]);
        } else {
            db_execute('INSERT INTO event_attendees (event_id, user_id, joined_at) VALUES (?, ?, NOW())', [$eventId, $userId]);
            touch_room_activity($roomId);
            echo json_encode(['success' => true, 'attending' => true]);
        }
        break;

    case 'edit_event':
        try {
            if (!$isHost) throw new Exception('Accès refusé');
            $eventId = $_POST['event_id'] ?? '';
            if (!$eventId) throw new Exception("ID événement manquant");
            db_execute(
                'UPDATE room_events SET event_date = ?, event_time = ?, location = ? WHERE id = ? AND room_id = ?',
                [$_POST['date'] ?? null, $_POST['time'] ?? null, $_POST['location'] ?? 'Non défini', $eventId, $roomId]
            );
            touch_room_activity($roomId);
            echo json_encode(['success' => true]);
        } catch (Throwable $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    case 'delete_event':
        try {
            if (!$isHost) throw new Exception('Accès refusé');
            $eventId = $_POST['event_id'] ?? '';
            if (!$eventId) throw new Exception("ID événement manquant");
            db_execute('DELETE FROM room_events WHERE id = ? AND room_id = ?', [$eventId, $roomId]);
            echo json_encode(['success' => true]);
        } catch (Throwable $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Action inconnue']);
        break;
}