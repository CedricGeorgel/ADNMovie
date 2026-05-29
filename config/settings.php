<?php
/**
 * SETTINGS.PHP — Configuration publique (peut être commité).
 * Les secrets (clés API, mots de passe) sont dans config/secrets.php.
 */

ini_set('display_errors', '1');
ini_set('log_errors', '1');
error_reporting(E_ALL);

define('SITE_NAME',            'ADNMovie');
define('SITE_URL',             'https://adnmovie.fr');
define('LEGAL_CONTACT_EMAIL',  'contact@adnmoviel.fr');
define('TMDB_API_KEY',         'fbf87fd27d52806d13a3839b801a4e7f');

$secretsFile = __DIR__ . '/secrets.php';
if (!file_exists($secretsFile)) {
    die('Fichier secrets.php manquant. Copier secrets.example.php et remplir les valeurs.');
}
require_once $secretsFile;

/**
 * Retourne une connexion PDO singleton.
 */
function getPDO(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $pdo = new PDO(
            'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
            DB_USER,
            DB_PASS,
            [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
            ]
        );
    }
    return $pdo;
}
