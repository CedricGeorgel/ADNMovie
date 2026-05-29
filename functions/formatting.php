<?php
/**
 * FORMATTING.PHP
 * Utilitaires pour l'affichage et la manipulation de texte/données.
 */

/**
 * Sécurise une chaîne contre les failles XSS (Escape HTML).
 */
function h($string) {
    return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * Transforme une durée en minutes en format H:MM (ex: 148 -> 2h28).
 */
function format_duration($minutes) {
    if (!$minutes) return "0min";
    $hours = floor($minutes / 60);
    $mins = $minutes % 60;
    return $hours . "h" . str_pad($mins, 2, "0", STR_PAD_LEFT);
}
function generate_google_cal_link($roomName, $movie, $event) {
    $title = urlencode($roomName . " : " . $movie['title']);
    $start = date('Ymd\THis', strtotime($event['date'] . ' ' . $event['time']));
    $end = date('Ymd\THis', strtotime($event['date'] . ' ' . $event['time'] . " + " . $movie['runtime'] . " minutes"));
    $details = urlencode($movie['synopsis']);
    $location = urlencode($event['location'] ?? 'En ligne');
    
    return "https://www.google.com/calendar/render?action=TEMPLATE&text=$title&dates=$start/$end&details=$details&location=$location";
}
function chat_encrypt(string $text): string {
    $iv     = random_bytes(16);
    $cipher = openssl_encrypt($text, 'aes-256-cbc', CHAT_KEY, OPENSSL_RAW_DATA, $iv);
    return base64_encode($iv . $cipher);
}

function chat_decrypt(string $blob): string {
    $raw = base64_decode($blob, true);
    if ($raw === false || strlen($raw) < 17) return $blob;
    $iv = substr($raw, 0, 16);
    $result = openssl_decrypt(substr($raw, 16), 'aes-256-cbc', CHAT_KEY, OPENSSL_RAW_DATA, $iv);
    return $result !== false ? $result : $blob;
}
