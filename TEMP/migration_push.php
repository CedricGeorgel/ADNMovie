<?php
/**
 * CONFIG/MIGRATION_PUSH.PHP
 * Crée la table push_subscriptions (idempotent).
 * Usage : php config/migration_push.php  ou  require_once depuis install.php.
 */

require_once __DIR__ . '/../functions/core_db.php';

db_execute("
    CREATE TABLE IF NOT EXISTS push_subscriptions (
        id         INT UNSIGNED     NOT NULL AUTO_INCREMENT,
        user_id    CHAR(36)         NOT NULL,
        endpoint   VARCHAR(2048)    NOT NULL,
        p256dh     VARCHAR(512)     NOT NULL,
        auth       VARCHAR(256)     NOT NULL,
        created_at DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY uniq_endpoint (endpoint(512)),
        KEY idx_user (user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

echo "push_subscriptions table OK\n";
