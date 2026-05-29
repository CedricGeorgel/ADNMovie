<?php
/**
 * API_CONTENT_SAVE.PHP
 * Crée ou met à jour un contenu utilisateur (liste ou critique).
 *
 * POST :
 *   id         (int, optionnel) — si présent : édition
 *   type       'list'|'critique'
 *   title      string
 *   body       string (texte libre, peut être vide)
 *   is_public  0|1
 *   items      JSON array [{movie_id, position, note}]  (optionnel)
 */
require_once 'auth_helpers.php';
start_persistent_session();
ini_set('display_errors', 0);
require_once __DIR__ . '/../functions/core_db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Non authentifié']); exit;
}

$userId = $_SESSION['user_id'];

// ── Validation des champs obligatoires ───────────────────────────────────────
$type  = $_POST['type']  ?? '';
$title = trim($_POST['title'] ?? '');
$body  = trim($_POST['body']  ?? '');
$isPublic = isset($_POST['is_public']) ? (int)(bool)$_POST['is_public'] : 1;

if (!in_array($type, ['list', 'critique', 'guide'], true) || $title === '') {
    echo json_encode(['success' => false, 'message' => 'Données invalides']); exit;
}

$items = [];
if (!empty($_POST['items'])) {
    $decoded = json_decode($_POST['items'], true);
    if (is_array($decoded)) $items = $decoded;
}

$contentId = isset($_POST['id']) ? (int)$_POST['id'] : 0;

// ── Édition ───────────────────────────────────────────────────────────────────
if ($contentId) {
    $existing = db_fetch_one('SELECT id, user_id FROM user_content WHERE id = ?', [$contentId]);
    if (!$existing || $existing['user_id'] !== $userId) {
        echo json_encode(['success' => false, 'message' => 'Non autorisé']); exit;
    }

    db_execute(
        'UPDATE user_content SET type=?, title=?, body=?, is_public=? WHERE id=?',
        [$type, $title, $body, $isPublic, $contentId]
    );

    // Resynchro des items
    db_execute('DELETE FROM user_content_items WHERE content_id = ?', [$contentId]);

// ── Création ──────────────────────────────────────────────────────────────────
} else {
    $slug = _generate_slug($title, $userId);

    db_execute(
        'INSERT INTO user_content (slug, user_id, type, title, body, is_public) VALUES (?,?,?,?,?,?)',
        [$slug, $userId, $type, $title, $body, $isPublic]
    );
    $contentId = db_last_id();
}

// ── Items ─────────────────────────────────────────────────────────────────────
foreach ($items as $item) {
    $movieId  = (int)($item['movie_id']  ?? 0);
    $position = (int)($item['position']  ?? 0);
    $note     = trim($item['note'] ?? '');
    if (!$movieId) continue;

    db_execute(
        'INSERT INTO user_content_items (content_id, movie_id, position, note) VALUES (?,?,?,?)',
        [$contentId, $movieId, $position, $note ?: null]
    );
}

$row = db_fetch_one('SELECT slug FROM user_content WHERE id = ?', [$contentId]);
echo json_encode(['success' => true, 'slug' => $row['slug'], 'id' => $contentId]);

// ── Helpers ───────────────────────────────────────────────────────────────────
function _generate_slug(string $title, string $userId): string {
    $slug = mb_strtolower($title, 'UTF-8');
    $slug = preg_replace('/[éèêë]/u', 'e', $slug);
    $slug = preg_replace('/[àâä]/u',  'a', $slug);
    $slug = preg_replace('/[ùûü]/u',  'u', $slug);
    $slug = preg_replace('/[ôö]/u',   'o', $slug);
    $slug = preg_replace('/[îï]/u',   'i', $slug);
    $slug = preg_replace('/ç/u',      'c', $slug);
    $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
    $slug = trim($slug, '-');
    $slug = substr($slug, 0, 80);

    // Suffixe court si collision
    $base = $slug;
    $i = 2;
    while (db_fetch_one('SELECT id FROM user_content WHERE slug = ?', [$slug])) {
        $slug = $base . '-' . $i++;
    }
    return $slug;
}
