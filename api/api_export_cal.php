<?php
/**
 * API_EXPORT_CAL.PHP
 * Export d'un événement vers Google Calendar ou fichier .ics
 */
require_once 'auth_helpers.php';
start_persistent_session();
require_once __DIR__ . '/../config/settings.php';
require_once __DIR__ . '/../functions/core_db.php';
require_once __DIR__ . '/../functions/utils.php';

$eventId = $_GET['id']   ?? '';
$type    = $_GET['type'] ?? 'google';

if (!$eventId) die("ID événement manquant.");

$event = db_fetch_one(
    'SELECT e.*, m.title AS movie_title, m.poster AS movie_poster, m.synopsis AS movie_synopsis
     FROM room_events e
     LEFT JOIN movies m ON m.tmdb_id = e.movie_id
     WHERE e.id = ?',
    [$eventId]
);

if (!$event) die("Événement introuvable.");

// ── Formatage des dates ───────────────────────────────────────
$ts        = strtotime(($event['event_date'] ?? date('Y-m-d')) . ' ' . ($event['event_time'] ?? '00:00'));
$startTime = date('Ymd\THis', $ts);
$endTime   = date('Ymd\THis', $ts + 150 * 60);

// ── Contenu ───────────────────────────────────────────────────
$movieTitle = $event['movie_title'] ?? 'Séance';
$location   = $event['location'] ?? 'Non défini';
$roomId     = $event['room_id'] ?? '';
$synopsis   = $event['movie_synopsis'] ?? '';

$title = 'Soirée ciné : ' . $movieTitle;

$eventUrl = $roomId
    ? 'https://adnmovie.fr/session?id=' . $roomId
    : 'https://adnmovie.fr';

// Description — \n littéral pour ICS, \n réel pour Google Calendar
$descLines = [
    'Séance ciné organisée via ADNmovie.',
    'Film : ' . $movieTitle,
];
if ($synopsis) {
    $descLines[] = '';
    // Tronque le synopsis à 300 chars pour ne pas surcharger
    $descLines[] = mb_strlen($synopsis) > 300
        ? mb_substr($synopsis, 0, 297) . '...'
        : $synopsis;
}
$descLines[] = '';
$descLines[] = 'Lien : ' . $eventUrl;

// Google Calendar utilise %0A pour les sauts de ligne dans l'URL
$descGoogle = implode("\n", $descLines);
// ICS : sauts de ligne échappés en \n littéral
$descIcs    = implode("\\n", $descLines);

if ($type === 'google') {
    $url  = "https://www.google.com/calendar/render?action=TEMPLATE";
    $url .= "&text="     . rawurlencode($title);
    $url .= "&dates="    . $startTime . "/" . $endTime;
    $url .= "&details="  . rawurlencode($descGoogle);
    $url .= "&location=" . rawurlencode($location);
    header("Location: " . $url);
} else {
    header('Content-Type: text/calendar; charset=utf-8');
    header('Content-Disposition: attachment; filename="adnmovie_seance.ics"');
    echo "BEGIN:VCALENDAR\r\n";
    echo "VERSION:2.0\r\n";
    echo "PRODID:-//ADNmovie//NONSGML v1.0//EN\r\n";
    echo "BEGIN:VEVENT\r\n";
    echo "UID:"         . $eventId . "@adnmovie.fr\r\n";
    echo "DTSTAMP:"     . date('Ymd\THis\Z') . "\r\n";
    echo "DTSTART:"     . $startTime . "\r\n";
    echo "DTEND:"       . $endTime . "\r\n";
    echo "SUMMARY:"     . $title . "\r\n";
    echo "LOCATION:"    . $location . "\r\n";
    echo "DESCRIPTION:" . $descIcs . "\r\n";
    echo "END:VEVENT\r\n";
    echo "END:VCALENDAR\r\n";
}
exit;
