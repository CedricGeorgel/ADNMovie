<?php
require_once __DIR__ . '/../config/settings.php';
require_once __DIR__ . '/../functions/core_db.php';
require_once __DIR__ . '/auth_helpers.php';
start_persistent_session(); // Session 30 jours

if (!isset($_GET['code'])) {
    $params = [
        'client_id'     => DISCORD_CLIENT_ID,
        'redirect_uri'  => DISCORD_REDIRECT_URL,
        'response_type' => 'code',
        'scope'         => 'identify',
    ];
    header('Location: https://discord.com/api/oauth2/authorize?' . http_build_query($params));
    exit;
}

$ch = curl_init('https://discord.com/api/oauth2/token');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
    'client_id'     => DISCORD_CLIENT_ID,
    'client_secret' => DISCORD_CLIENT_SECRET,
    'grant_type'    => 'authorization_code',
    'code'          => $_GET['code'],
    'redirect_uri'  => DISCORD_REDIRECT_URL,
]));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
$raw      = curl_exec($ch);
$response = json_decode($raw, true);

if (!isset($response['access_token'])) {
    curl_close($ch);
    die("Echec token Discord. reponse=" . htmlspecialchars($raw));
}

curl_setopt($ch, CURLOPT_URL, 'https://discord.com/api/users/@me');
curl_setopt($ch, CURLOPT_POST, false);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $response['access_token']]);
$discordUser = json_decode(curl_exec($ch), true);
curl_close($ch);

if (!isset($discordUser['id'])) {
    die("Echec profil Discord.");
}

$uid         = $discordUser['id'];
$username    = $discordUser['username'];
$email       = $discordUser['email'] ?? null;
$avatar_hash = $discordUser['avatar'];

$local_avatar_path = upsert_avatar_from_url(
    $uid,
    $avatar_hash
        ? 'https://cdn.discordapp.com/avatars/' . $uid . '/' . $avatar_hash . (str_starts_with($avatar_hash, 'a_') ? '.gif' : '.png')
        : null
);

try {
    $wasInactive = upsert_user($uid, $username, $email, $local_avatar_path, 'discord');
} catch (Exception $e) {
    die("Erreur BDD : " . $e->getMessage());
}

$_SESSION['user_id'] = $uid;
if ($wasInactive) $_SESSION['signal_perdu'] = true;
set_remember_token($uid);
header('Location: ../user.php');
exit;