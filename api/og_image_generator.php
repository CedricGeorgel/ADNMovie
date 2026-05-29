<?php
/**
 * /api/og_image_generator.php
 * Génère une image Open Graph 1200×630 pour un événement ADNMovie.
 * Utilise uniquement l'extension GD (native PHP).
 *
 * Paramètre GET : id (event id)
 */

require_once __DIR__ . '/../functions/utils.php';

define('OG_W', 1200);
define('OG_H', 630);

// ── Récupération de l'événement ──────────────────────────────────────
$eventId = $_GET['id'] ?? null;
if (!$eventId) { http_response_code(400); exit('Missing id'); }

$event = db_fetch_one(
    'SELECT e.*, m.title AS movie_title, m.poster AS movie_poster
     FROM room_events e
     LEFT JOIN movies m ON m.tmdb_id = e.movie_id
     WHERE e.id = ?',
    [$eventId]
);
if (!$event) { http_response_code(404); exit('Event not found'); }

// ── Cache ─────────────────────────────────────────────────────────────
$cacheDir  = __DIR__ . '/../cache/og/';
$cacheFile = $cacheDir . 'event_' . preg_replace('/[^a-zA-Z0-9_-]/', '', $eventId) . '.png';
if (!is_dir($cacheDir)) mkdir($cacheDir, 0755, true);

$cacheValid = file_exists($cacheFile)
    && filemtime($cacheFile) >= strtotime($event['updated_at'] ?? $event['created_at'] ?? '0');

if ($cacheValid) {
    header('Content-Type: image/png');
    header('Cache-Control: public, max-age=3600');
    readfile($cacheFile);
    exit;
}

// ── Helper : charge le poster depuis le disque local ─────────────────
// $url contient un chemin relatif type "assets/movie_posters/123.jpg"
function load_remote_image(string $url) {
    $localPath = __DIR__ . '/../' . ltrim($url, '/');
    if (!file_exists($localPath)) return false;
    return @imagecreatefromstring(file_get_contents($localPath));
}

// ── Helper : flou gaussien répété ────────────────────────────────────
function blur_image($img, int $passes = 10) {
    for ($i = 0; $i < $passes; $i++) imagefilter($img, IMG_FILTER_GAUSSIAN_BLUR);
    return $img;
}

// ── Helper : texte avec wrap automatique ─────────────────────────────
function draw_wrapped_text($canvas, int $size, int $x, int $y, $color, string $font, string $text, int $maxW): int {
    $words = explode(' ', $text);
    $line  = '';
    $lineH = (int)($size * 1.25);
    foreach ($words as $word) {
        $test = $line === '' ? $word : "$line $word";
        $box  = imagettfbbox($size, 0, $font, $test);
        if (abs($box[4] - $box[0]) > $maxW && $line !== '') {
            imagettftext($canvas, $size, 0, $x, $y, $color, $font, $line);
            $y   += $lineH;
            $line = $word;
        } else {
            $line = $test;
        }
    }
    if ($line !== '') { imagettftext($canvas, $size, 0, $x, $y, $color, $font, $line); $y += $lineH; }
    return $y;
}

// ── Helper : rectangle à coins arrondis ──────────────────────────────
function filled_rounded_rect($img, int $x1, int $y1, int $x2, int $y2, int $r, $color): void {
    imagefilledrectangle($img, $x1 + $r, $y1,      $x2 - $r, $y2,      $color);
    imagefilledrectangle($img, $x1,      $y1 + $r, $x2,      $y2 - $r, $color);
    imagefilledellipse($img, $x1 + $r, $y1 + $r, $r * 2, $r * 2, $color);
    imagefilledellipse($img, $x2 - $r, $y1 + $r, $r * 2, $r * 2, $color);
    imagefilledellipse($img, $x1 + $r, $y2 - $r, $r * 2, $r * 2, $color);
    imagefilledellipse($img, $x2 - $r, $y2 - $r, $r * 2, $r * 2, $color);
}

// ── Polices ───────────────────────────────────────────────────────────
function find_font(array $candidates): string {
    foreach ($candidates as $p) { if (file_exists($p)) return $p; }
    return '';
}
$fontBold = find_font([
    __DIR__ . '/../assets/fonts/Inter-Bold.ttf',
    __DIR__ . '/../assets/fonts/inter-bold.ttf',
    '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf',
    '/usr/share/fonts/dejavu-sans-fonts/DejaVuSans-Bold.ttf',
    '/usr/share/fonts/truetype/liberation/LiberationSans-Bold.ttf',
    '/usr/share/fonts/liberation-sans/LiberationSans-Bold.ttf',
]);
$fontRegular = find_font([
    __DIR__ . '/../assets/fonts/Inter-Regular.ttf',
    __DIR__ . '/../assets/fonts/inter-regular.ttf',
    '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
    '/usr/share/fonts/dejavu-sans-fonts/DejaVuSans.ttf',
    '/usr/share/fonts/truetype/liberation/LiberationSans-Regular.ttf',
    '/usr/share/fonts/liberation-sans/LiberationSans-Regular.ttf',
]);
$useTTF = ($fontBold !== '' && $fontRegular !== '');

// ── Canvas ────────────────────────────────────────────────────────────
$canvas = imagecreatetruecolor(OG_W, OG_H);
imagealphablending($canvas, true);
imagesavealpha($canvas, true);

// Couleurs
$cBg        = imagecolorallocate($canvas, 11,  12,  18);
$cWhite     = imagecolorallocate($canvas, 255, 255, 255);
$cBlue      = imagecolorallocate($canvas, 100, 181, 246);
$cBadgeBg   = imagecolorallocate($canvas, 28,  35,  54);
$cBadgeTxt  = imagecolorallocate($canvas, 170, 205, 255);
$cSubtle    = imagecolorallocate($canvas, 160, 170, 190);

imagefill($canvas, 0, 0, $cBg);

// ── Fond : poster flouté cover ────────────────────────────────────────
$posterUrl = $event['movie_poster'] ?? '';
$posterSrcShared = false; // on garde une copie pour le poster net

if ($posterUrl) {
    $bgSrc = load_remote_image($posterUrl);
    if ($bgSrc) {
        $posterSrcShared = $bgSrc; // réutilisé plus bas pour le poster net

        $sw = imagesx($bgSrc); $sh = imagesy($bgSrc);
        $ratio = max(OG_W / $sw, OG_H / $sh);
        $nw = (int)round($sw * $ratio); $nh = (int)round($sh * $ratio);
        $ox = (int)round((OG_W - $nw) / 2); $oy = (int)round((OG_H - $nh) / 2);

        $bgBuf = imagecreatetruecolor(OG_W, OG_H);
        imagecopyresampled($bgBuf, $bgSrc, $ox, $oy, 0, 0, $nw, $nh, $sw, $sh);

        blur_image($bgBuf, 105);
        imagefilter($bgBuf, IMG_FILTER_BRIGHTNESS, -55);
        imagefilter($bgBuf, IMG_FILTER_CONTRAST,   -20);

        imagecopy($canvas, $bgBuf, 0, 0, 0, 0, OG_W, OG_H);
        imagedestroy($bgBuf);
        // NE PAS détruire $posterSrcShared ici — on s'en sert pour le poster net
    }
}

// Overlay dégradé gauche→droite (zone texte plus lisible)
// On simule un dégradé en dessinant des colonnes semi-transparentes
$gradW = OG_W;
for ($i = 0; $i < $gradW; $i++) {
    // Plus sombre au centre-droit qu'à gauche (le poster est à gauche)
    $alpha = (int)(60 + 55 * ($i / $gradW)); // 60→115 sur l'échelle GD (0=opaque,127=transparent)
    $alpha = min(127, $alpha);
    $c = imagecolorallocatealpha($canvas, 8, 9, 16, $alpha);
    imageline($canvas, $i, 0, $i, OG_H, $c);
    imagecolordeallocate($canvas, $c);
}

// ── Poster net à gauche ───────────────────────────────────────────────
$pX = 70; $pY = 90;
$pW = 270; $pH = 400;
$pR = 14;  // border-radius

if ($posterSrcShared) {
    $src = $posterSrcShared;
    $sw = imagesx($src); $sh = imagesy($src);

    $r  = max($pW / $sw, $pH / $sh);
    $nw = (int)round($sw * $r); $nh = (int)round($sh * $r);
    $sx = (int)round(($nw - $pW) / 2); $sy = (int)round(($nh - $pH) / 2);

    $resized = imagecreatetruecolor($nw, $nh);
    imagecopyresampled($resized, $src, 0, 0, 0, 0, $nw, $nh, $sw, $sh);
    imagedestroy($src);

    // Masque arrondi → image clippée
    $clipped = imagecreatetruecolor($pW, $pH);
    imagealphablending($clipped, false);
    imagesavealpha($clipped, true);
    $trans = imagecolorallocatealpha($clipped, 0, 0, 0, 127);
    imagefill($clipped, 0, 0, $trans);

    $mask = imagecreatetruecolor($pW, $pH);
    $mW   = imagecolorallocate($mask, 255, 255, 255);
    $mB   = imagecolorallocate($mask, 0, 0, 0);
    imagefill($mask, 0, 0, $mB);
    filled_rounded_rect($mask, 0, 0, $pW, $pH, $pR, $mW);

    for ($py = 0; $py < $pH; $py++) {
        for ($px = 0; $px < $pW; $px++) {
            if (imagecolorat($mask, $px, $py) > 0) {
                imagesetpixel($clipped, $px, $py, imagecolorat($resized, $sx + $px, $sy + $py));
            }
        }
    }
    imagedestroy($mask);
    imagedestroy($resized);

    // Ombre portée
    $shadow = imagecolorallocatealpha($canvas, 0, 0, 0, 85);
    filled_rounded_rect($canvas, $pX + 8, $pY + 10, $pX + $pW + 8, $pY + $pH + 10, $pR, $shadow);

    // Coller le poster
    imagealphablending($canvas, true);
    imagecopy($canvas, $clipped, $pX, $pY, 0, 0, $pW, $pH);
    imagedestroy($clipped);
}

// ── Zone texte ────────────────────────────────────────────────────────
$tX    = $pX + $pW + 65;
$tMaxW = OG_W - $tX - 55;
$tY    = 155;

if ($useTTF) {

    // Badge PROJECTION
    $badgeLabel = 'PROJECTION';
    $bSize      = 15;
    $bBox       = imagettfbbox($bSize, 0, $fontBold, $badgeLabel);
    $bW         = abs($bBox[4] - $bBox[0]) + 30;
    $bH         = 32;
    filled_rounded_rect($canvas, $tX, $tY, $tX + $bW, $tY + $bH, 8, $cBadgeBg);
    imagettftext($canvas, $bSize, 0, $tX + 15, $tY + 22, $cBadgeTxt, $fontBold, $badgeLabel);
    $tY += $bH + 24;

    // Titre
    $title      = $event['movie_title'] ?? 'Séance';
    $titleSize  = 58;
    // Réduction auto si trop long
    while ($titleSize > 30) {
        $box = imagettfbbox($titleSize, 0, $fontBold, $title);
        if (abs($box[4] - $box[0]) <= $tMaxW) break;
        $titleSize -= 3;
    }
    $tY = draw_wrapped_text($canvas, $titleSize, $tX, $tY + $titleSize, $cWhite, $fontBold, $title, $tMaxW);
    $tY += 14;

    // Date / heure
    if ($event['event_date']) {
        $dateStr = date('d/m/Y', strtotime($event['event_date'])) . '  ·  ' . ($event['event_time'] ?? '');
        imagettftext($canvas, 26, 0, $tX, $tY, $cBlue, $fontBold, $dateStr);
        $tY += 44;
    }

    // Lieu
    $location = $event['location'] ?? '';
    if ($location) {
        $lSize = 20;
        // Tronquer si trop long
        $lText = $location;
        while (true) {
            $box = imagettfbbox($lSize, 0, $fontRegular, $lText);
            if (abs($box[4] - $box[0]) <= $tMaxW - 28) break;
            $lText = mb_substr($lText, 0, -1);
        }
        if ($lText !== $location) $lText .= '…';

        // Petite puce ronde bleue
        $dotR = 5;
        imagefilledellipse($canvas, $tX + $dotR, $tY - 7, $dotR * 2, $dotR * 2, $cBlue);
        imagettftext($canvas, $lSize, 0, $tX + $dotR * 2 + 10, $tY, $cSubtle, $fontRegular, $lText);
    }

} else {
    // Fallback bitmap GD (sans TTF)
    imagestring($canvas, 4, $tX, $tY,       'PROJECTION',                       $cBadgeTxt);
    imagestring($canvas, 5, $tX, $tY + 30,  $event['movie_title'] ?? 'Séance',  $cWhite);
    $dateStr = $event['event_date']
        ? date('d/m/Y', strtotime($event['event_date'])) . ' ' . ($event['event_time'] ?? '')
        : '';
    imagestring($canvas, 4, $tX, $tY + 65,  $dateStr,                           $cBlue);
    imagestring($canvas, 3, $tX, $tY + 95,  $event['location'] ?? '',           $cSubtle);
}

// ── Branding bas droit ────────────────────────────────────────────────
if ($useTTF) {
    $brand     = 'ADN Movie';
    $brandSize = 17;
    $brandBox  = imagettfbbox($brandSize, 0, $fontBold, $brand);
    $brandW    = abs($brandBox[4] - $brandBox[0]);
    $brandCol  = imagecolorallocatealpha($canvas, 100, 181, 246, 60);
    imagettftext($canvas, $brandSize, 0, OG_W - $brandW - 38, OG_H - 34, $brandCol, $fontBold, $brand);
}

// ── Ligne de séparation verticale poster / texte ──────────────────────
$sepX   = $tX - 32;
$sepCol = imagecolorallocatealpha($canvas, 100, 181, 246, 90);
for ($i = 0; $i < 2; $i++) {
    imageline($canvas, $sepX + $i, 110, $sepX + $i, OG_H - 110, $sepCol);
}

// ── Écriture cache + envoi ────────────────────────────────────────────
imagepng($canvas, $cacheFile, 7);
imagedestroy($canvas);

header('Content-Type: image/png');
header('Cache-Control: public, max-age=3600');
readfile($cacheFile);
exit;