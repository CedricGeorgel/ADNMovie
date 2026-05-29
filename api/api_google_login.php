<?php
require_once __DIR__ . '/../config/settings.php';
require_once __DIR__ . '/../functions/core_db.php';
require_once __DIR__ . '/auth_helpers.php';
start_persistent_session(); // Session 30 jours

if (!isset($_GET['code'])) {
    $url = 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query([
        'client_id'     => GOOGLE_CLIENT_ID,
        'redirect_uri'  => GOOGLE_REDIRECT_URL,
        'response_type' => 'code',
        'scope'         => 'openid profile email',
    ]);
    header('Location: ' . $url);
    exit;
}

$ch = curl_init('https://oauth2.googleapis.com/token');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_POSTFIELDS     => [
        'code'          => $_GET['code'],
        'client_id'     => GOOGLE_CLIENT_ID,
        'client_secret' => GOOGLE_CLIENT_SECRET,
        'redirect_uri'  => GOOGLE_REDIRECT_URL,
        'grant_type'    => 'authorization_code',
    ],
]);
$tokenData = json_decode(curl_exec($ch), true);
curl_close($ch);

if (!isset($tokenData['access_token'])) {
    die("Echec token Google. reponse=" . json_encode($tokenData));
}

$ch = curl_init('https://www.googleapis.com/oauth2/v3/userinfo');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . $tokenData['access_token']],
]);
$googleUser = json_decode(curl_exec($ch), true);
curl_close($ch);

if (!isset($googleUser['sub'])) {
    die("Echec profil Google.");
}

$uid      = 'google_' . $googleUser['sub'];
$username = $googleUser['name'];
$email    = $googleUser['email'] ?? null;
$picture  = $googleUser['picture'] ?? null;

$local_avatar_path = upsert_avatar_from_url($uid, $picture);

try {
    $wasInactive = upsert_user($uid, $username, $email, $local_avatar_path, 'google');
} catch (Exception $e) {
    die("Erreur BDD : " . $e->getMessage());
}

$_SESSION['user_id'] = $uid;
if ($wasInactive) $_SESSION['signal_perdu'] = true;
set_remember_token($uid);
header('Location: ../user.php');
exit;