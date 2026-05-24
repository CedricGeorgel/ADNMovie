<?php
/**
 * CORE_DB.PHP - Fonctions d'accès à la base de données
 */

require_once __DIR__ . '/../config/settings.php';

// ─────────────────────────────────────────────────────────────────────────────
// HELPERS GÉNÉRIQUES
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Exécute une requête SELECT et retourne toutes les lignes.
 *
 * @param string $sql    Requête SQL avec placeholders
 * @param array  $params Paramètres liés
 * @return array
 */
function db_fetch_all(string $sql, array $params = []): array {
    $stmt = getPDO()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

/**
 * Exécute une requête SELECT et retourne une seule ligne.
 *
 * @param string $sql
 * @param array  $params
 * @return array|null
 */
function db_fetch_one(string $sql, array $params = []): ?array {
    $stmt = getPDO()->prepare($sql);
    $stmt->execute($params);
    $row = $stmt->fetch();
    return $row ?: null;
}

/**
 * Exécute une requête INSERT / UPDATE / DELETE.
 * Retourne le nombre de lignes affectées.
 *
 * @param string $sql
 * @param array  $params
 * @return int
 */
function db_execute(string $sql, array $params = []): int {
    $stmt = getPDO()->prepare($sql);
    $stmt->execute($params);
    return $stmt->rowCount();
}

/**
 * Retourne le dernier ID auto-incrémenté inséré.
 * Utile si on ajoute un jour une table avec un INT AUTO_INCREMENT.
 *
 * @return string
 */
function db_last_id(): string {
    return getPDO()->lastInsertId();
}

// ─────────────────────────────────────────────────────────────────────────────
// HELPERS TRANSACTIONS
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Démarre une transaction.
 * Remplace le pattern "lire JSON → modifier → sauvegarder"
 * qui n'était pas atomique.
 */
function db_begin(): void {
    getPDO()->beginTransaction();
}

/**
 * Valide la transaction courante.
 */
function db_commit(): void {
    getPDO()->commit();
}

/**
 * Annule la transaction courante en cas d'erreur.
 */
function db_rollback(): void {
    if (getPDO()->inTransaction()) {
        getPDO()->rollBack();
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// CRÉATION DE TABLES (si elles n'existent pas)
// ─────────────────────────────────────────────────────────────────────────────

// Création de la table user_series_follow si elle n'existe pas
try {
    getPDO()->exec("
        CREATE TABLE IF NOT EXISTS user_series_follow (
            user_id      VARCHAR(64) NOT NULL,
            series_id    INT UNSIGNED NOT NULL,
            is_ended     BOOLEAN NOT NULL DEFAULT 0,
            followed_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            ended_at     TIMESTAMP NULL DEFAULT NULL,
            PRIMARY KEY (user_id, series_id),
            INDEX idx_series_ended (series_id, is_ended)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
} catch (Throwable $e) {
    // Log l'erreur si nécessaire, mais ne bloque pas le script
    error_log("Failed to create user_series_follow table: " . $e->getMessage());
}

// Création de la table series_episodes si elle n'existe pas
try {
    getPDO()->exec("
        CREATE TABLE IF NOT EXISTS series_episodes (
            series_id       INT UNSIGNED NOT NULL,
            season_number   SMALLINT UNSIGNED NOT NULL,
            episode_number  SMALLINT UNSIGNED NOT NULL,
            air_date        DATE NULL DEFAULT NULL,
            name            VARCHAR(255) NULL DEFAULT NULL,
            overview        TEXT NULL DEFAULT NULL,
            PRIMARY KEY (series_id, season_number, episode_number),
            INDEX idx_air_date (air_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
} catch (Throwable $e) {
    error_log("Failed to create series_episodes table: " . $e->getMessage());
}
