<?php
/**
 * CORE_DB.PHP
 * Remplace core_json.php — toutes les interactions passent par PDO.
 * 
 * Les autres fichiers qui faisaient :
 *   require 'functions/core_json.php';
 *   $data = load_json($filepath);
 *   save_json($filepath, $data);
 * 
 * Devront être mis à jour pour utiliser getPDO() directement.
 * Ce fichier expose uniquement des helpers génériques bas niveau.
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