<?php
/**
 * COMPONENTS/EVENT-CARD.PHP
 */
function renderEventCard($event, $currentUserId) {
    if (!$event) return;

    // Compatibilité : attendees peut venir du JSON (array) ou de la BDD
    // Depuis la BDD, on n'a pas d'array attendees — on fait une requête légère
    if (isset($event['attendees']) && is_array($event['attendees'])) {
        $attendeeCount = count($event['attendees']);
        $isAttending   = in_array($currentUserId, $event['attendees']);
    } else {
        // Nouvelle structure BDD : on compte via SQL
        $countRow      = db_fetch_one(
            'SELECT COUNT(*) AS cnt FROM event_attendees WHERE event_id = ?',
            [$event['id']]
        );
        $attendeeCount = $countRow ? (int)$countRow['cnt'] : 0;
        $attending     = db_fetch_one(
            'SELECT 1 FROM event_attendees WHERE event_id = ? AND user_id = ?',
            [$event['id'], $currentUserId]
        );
        $isAttending = (bool)$attending;
    }

    // Compatibilité champs date : event_date (BDD) ou date (JSON)
    $date     = $event['event_date'] ?? $event['date'] ?? '';
    $time     = $event['event_time'] ?? $event['time'] ?? '';
    $poster   = $event['movie_poster'] ?? '';
    $title    = $event['movie_title']  ?? 'Séance';
    $location = $event['location']     ?? '';
    $eventId  = $event['id']           ?? '';
    ?>
    <div class="event-row-item">
        <div class="event-row-blur" style="background-image: url('<?= h($poster) ?>');"></div>

        <div class="event-mini-poster hide-mobile">
            <img src="<?= h($poster) ?>" alt="Poster">
        </div>

        <div class="event-row-content">
            <div class="event-row-info-group">
                <h4 class="event-row-title"><?= h($title) ?></h4>
                <div class="event-row-sub-details">
                    <div class="event-time-pill">
                        <span class="pill-date"><?= $date ? date('d/m', strtotime($date)) : '' ?></span>
                        <span class="pill-time"><?= h($time) ?></span>
                    </div>
                    <span class="event-location-tag">📍 <?= h($location) ?></span>
                </div>
            </div>

            <div class="event-row-actions">
                <div class="attendees-badge" title="Participants">
                    <strong><?= $attendeeCount ?></strong> <i class="fa-solid fa-users"></i>
                </div>
                <div class="action-buttons">
                    <button onclick="toggleEventAttendance('<?= h($eventId) ?>')"
                            class="btn-base-icon <?= $isAttending ? 'active' : '' ?>">
                        <?= $isAttending ? '✓' : '+' ?>
                    </button>
                    <a href="event.php?id=<?= h($eventId) ?>" class="btn-goto-arrow">→</a>
                </div>
            </div>
        </div>
    </div>
    <?php
}