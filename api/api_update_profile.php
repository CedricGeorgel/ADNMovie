<?php
/**
 * API_UPDATE_PROFILE.PHP
 * Mise à jour du pseudo, de la description narrative et de l'avatar.
 */
require_once 'auth_helpers.php'; 
start_persistent_session();
require_once __DIR__ . '/../config/settings.php';
require_once __DIR__ . '/../functions/core_db.php';
require_once __DIR__ . '/../functions/achievements_logic.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Non connecté']);
    exit;
}

$userId         = $_SESSION['user_id'];
$newUsername    = trim($_POST['username'] ?? '');
$newDescription = trim($_POST['description'] ?? '');
$avatarB64      = $_POST['avatar_base64'] ?? '';

if (empty($newUsername)) {
    echo json_encode(['success' => false, 'message' => 'Le pseudo ne peut pas être vide.']);
    exit;
}

// Auto-migration des colonnes couleurs de fond
try {
    $cols = array_column(getPDO()->query("SHOW COLUMNS FROM users")->fetchAll(\PDO::FETCH_ASSOC), 'Field');
    if (!in_array('banner_color1', $cols)) getPDO()->exec("ALTER TABLE users ADD COLUMN banner_color1 VARCHAR(7) NULL DEFAULT NULL");
    if (!in_array('banner_color2', $cols)) getPDO()->exec("ALTER TABLE users ADD COLUMN banner_color2 VARCHAR(7) NULL DEFAULT NULL");
} catch (\Throwable $ignored) {}

// Vérifie que le user existe
$user = db_fetch_one('SELECT id, avatar FROM users WHERE id = ?', [$userId]);
if (!$user) {
    echo json_encode(['success' => false, 'message' => 'Profil introuvable.']);
    exit;
}

// ── Helper : redimensionne et centre-crop en JPEG via GD ──────
function resize_image_jpeg(string $data, int $w, int $h, int $quality = 85): ?string {
    $src = @imagecreatefromstring($data);
    if (!$src) return null;

    $sw = imagesx($src);
    $sh = imagesy($src);

    // Centre-crop au ratio cible
    if ($sw / $sh > $w / $h) {
        $ch = $sh; $cw = (int)($sh * $w / $h);
        $cx = (int)(($sw - $cw) / 2); $cy = 0;
    } else {
        $cw = $sw; $ch = (int)($sw * $h / $w);
        $cx = 0; $cy = (int)(($sh - $ch) / 2);
    }

    $dst = imagecreatetruecolor($w, $h);
    imagecopyresampled($dst, $src, 0, 0, $cx, $cy, $w, $h, $cw, $ch);

    ob_start();
    imagejpeg($dst, null, $quality);
    $jpeg = ob_get_clean();

    imagedestroy($src);
    imagedestroy($dst);
    return $jpeg ?: null;
}

function decode_base64_image(string $b64): ?string {
    $parts = explode(',', $b64);
    if (count($parts) !== 2) return null;
    $data = base64_decode($parts[1], true);
    if ($data === false || strlen($data) > 8 * 1024 * 1024) return null;
    return $data;
}

// ── 1. Traitement de l'avatar ─────────────────────────────────
$avatarUrl = $user['avatar'];
$avatarB64 = $_POST['avatar_base64'] ?? '';

if (!empty($avatarB64)) {
    $raw = decode_base64_image($avatarB64);
    if (!$raw) {
        echo json_encode(['success' => false, 'message' => 'Avatar invalide ou trop lourd (max 8 Mo).']);
        exit;
    }
    $jpeg = resize_image_jpeg($raw, 256, 256);
    if (!$jpeg) {
        echo json_encode(['success' => false, 'message' => 'Format d\'image non reconnu.']);
        exit;
    }
    $avatarDir = __DIR__ . '/../assets/avatars/';
    if (!is_dir($avatarDir)) mkdir($avatarDir, 0755, true);
    $filename  = $userId . '.jpg';
    file_put_contents($avatarDir . $filename, $jpeg);
    $avatarUrl = 'assets/avatars/' . $filename . '?v=' . time();
}

// ── 1b. Couleurs de fond (gradient) ──────────────────────────
$bannerColor1 = $_POST['banner_color1'] ?? null;
$bannerColor2 = $_POST['banner_color2'] ?? null;

function is_valid_hex(string $c): bool {
    return (bool) preg_match('/^#[0-9a-fA-F]{6}$/', $c);
}
if ($bannerColor1 !== null && !is_valid_hex($bannerColor1)) $bannerColor1 = null;
if ($bannerColor2 !== null && !is_valid_hex($bannerColor2)) $bannerColor2 = null;

// ── 2. Mise à jour en BDD ─────────────────────────────────────
$setCols  = 'username = ?, avatar = ?, description = ?, is_description_modified = 1';
$params   = [$newUsername, $avatarUrl, $newDescription];
if ($bannerColor1 !== null) { $setCols .= ', banner_color1 = ?'; $params[] = $bannerColor1; }
if ($bannerColor2 !== null) { $setCols .= ', banner_color2 = ?'; $params[] = $bannerColor2; }
$params[] = $userId;

try {
    db_execute("UPDATE users SET $setCols WHERE id = ?", $params);
} catch (\Throwable $e) {
    echo json_encode(['success' => false, 'message' => 'Erreur BDD : ' . $e->getMessage()]);
    exit;
}

// ── 3. Vérification des badges ────────────────────────────────
$newBadges = check_and_award_achievements($userId);

// Retourne tous les badges pour le front
$badges = db_fetch_all(
    'SELECT badge_id FROM user_badges WHERE user_id = ?',
    [$userId]
);

echo json_encode([
    'success'        => true,
    'username'       => $newUsername,
    'description'    => $newDescription,
    'avatar'         => $avatarUrl,
    'banner_color1'  => $bannerColor1,
    'banner_color2'  => $bannerColor2,
    'badges'         => array_column($badges, 'badge_id'),
    'new_badges'     => $newBadges,
]);