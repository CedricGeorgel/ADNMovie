<?php
/**
 * API_CENSORED_WORDS.PHP
 * Lecture et modification de la liste des mots censurés (modérateur+).
 * GET              → retourne la liste
 * POST action=add  → ajoute un mot
 * POST action=remove → supprime un mot
 */
require_once 'auth_helpers.php';
start_persistent_session();
require_once __DIR__ . '/../functions/core_db.php';
require_once __DIR__ . '/../functions/auth_role_helper.php';

header('Content-Type: application/json');

if (!has_role('moderator')) {
    echo json_encode(['success' => false, 'message' => 'Accès refusé.']);
    exit;
}

$jsonPath = __DIR__ . '/../config/censored_words.json';

function load_words(string $path): array {
    if (!file_exists($path)) return [];
    $data = json_decode(file_get_contents($path), true);
    return is_array($data) ? array_values(array_filter($data, 'strlen')) : [];
}

function save_words(string $path, array $words): bool {
    $words = array_values(array_unique(array_filter(array_map('trim', $words), 'strlen')));
    sort($words);
    return file_put_contents($path, json_encode($words, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) !== false;
}

// ── GET : liste ───────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    echo json_encode(['success' => true, 'words' => load_words($jsonPath)]);
    exit;
}

// ── POST ──────────────────────────────────────────────────────────────────────
$action = $_POST['action'] ?? '';
$word   = mb_strtolower(trim($_POST['word'] ?? ''));

if (empty($word)) {
    echo json_encode(['success' => false, 'message' => 'Mot manquant.']);
    exit;
}

$words = load_words($jsonPath);

if ($action === 'add') {
    if (in_array($word, $words, true)) {
        echo json_encode(['success' => false, 'message' => 'Mot déjà présent.']);
        exit;
    }
    $words[] = $word;
    save_words($jsonPath, $words);
    echo json_encode(['success' => true, 'words' => load_words($jsonPath)]);

} elseif ($action === 'remove') {
    $words = array_values(array_filter($words, fn($w) => $w !== $word));
    save_words($jsonPath, $words);
    echo json_encode(['success' => true, 'words' => load_words($jsonPath)]);

} else {
    echo json_encode(['success' => false, 'message' => 'Action inconnue.']);
}
