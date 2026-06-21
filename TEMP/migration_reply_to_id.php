<?php
/**
 * TEMP/migration_reply_to_id.php
 * Ajoute reply_to_id à room_messages si absent.
 * À exécuter une seule fois puis supprimer.
 */
require_once __DIR__ . '/../functions/core_db.php';

$exists = db_fetch_one(
    "SELECT 1 FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME   = 'room_messages'
       AND COLUMN_NAME  = 'reply_to_id'"
);

if ($exists) {
    echo "✓ Colonne reply_to_id déjà présente.\n";
} else {
    getPDO()->exec("ALTER TABLE room_messages ADD COLUMN reply_to_id INT NULL DEFAULT NULL");
    echo "✓ Colonne reply_to_id ajoutée.\n";
}
