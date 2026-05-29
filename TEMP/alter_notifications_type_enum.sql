-- Ajoute 'new_episode' à l'ENUM type de la table notifications.
-- Sans cette migration, les INSERT de series_logic.php échouent silencieusement
-- car 'new_episode' n'est pas dans l'ENUM original ('mention','comment','message','report').
--
-- À exécuter une seule fois sur la BDD de production.

ALTER TABLE notifications
  MODIFY COLUMN type ENUM('mention','comment','message','report','new_episode') NOT NULL;
