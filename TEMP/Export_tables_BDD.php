<?php
/**
 * EXPORT_DB_STRUCTURE.PHP
 * Exporte la structure complète de la BDD (tables, colonnes, index, relations).
 * ⚠️ Supprimer après utilisation.
 */
$host   = 'caadestadnmovie.mysql.db';
$dbname = 'caadestadnmovie';
$user   = 'caadestadnmovie';
$pass   = 'EZUOAzxd7sipyaMxCmbbKtxg8IWQ';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
} catch (PDOException $e) { die('Connexion échouée : ' . $e->getMessage()); }

// Toutes les tables
$tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);

$export = [];
foreach ($tables as $table) {
    // Colonnes
    $cols = $pdo->query("SHOW FULL COLUMNS FROM `$table`")->fetchAll(PDO::FETCH_ASSOC);
    // Index
    $indexes = $pdo->query("SHOW INDEX FROM `$table`")->fetchAll(PDO::FETCH_ASSOC);
    // CREATE TABLE
    $create = $pdo->query("SHOW CREATE TABLE `$table`")->fetch(PDO::FETCH_ASSOC);

    $export[$table] = [
        'columns' => $cols,
        'indexes' => $indexes,
        'create'  => $create['Create Table'] ?? '',
        'row_count' => (int)$pdo->query("SELECT COUNT(*) FROM `$table`")->fetchColumn(),
    ];
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Structure BDD — adnmovie</title>
    <style>
        body { font-family: monospace; background: #0a0a0f; color: #e2e8f0; padding: 32px; font-size: 0.78rem; }
        h1 { color: #A7C7E7; font-size: 1.1rem; margin-bottom: 24px; letter-spacing: 2px; }
        .table-block { margin-bottom: 40px; border: 1px solid #1e2130; border-radius: 8px; overflow: hidden; }
        .table-header { background: #131320; padding: 12px 16px; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #1e2130; }
        .table-name { color: #B8A7E7; font-size: 0.9rem; font-weight: 700; }
        .row-count { color: #64748b; font-size: 0.65rem; }
        table { width: 100%; border-collapse: collapse; }
        th { background: #0f1020; padding: 8px 12px; text-align: left; color: #64748b; font-size: 0.65rem; letter-spacing: 1px; text-transform: uppercase; border-bottom: 1px solid #1e2130; }
        td { padding: 7px 12px; border-bottom: 1px solid #0f1020; vertical-align: top; }
        tr:last-child td { border-bottom: none; }
        .col-name { color: #A7C7E7; font-weight: 700; }
        .col-type { color: #E8C07A; }
        .col-null { color: #64748b; }
        .col-key  { color: #B8A7E7; }
        .col-default { color: #9ca3af; }
        .col-extra  { color: #6ee7b7; }
        .create-block { background: #080810; padding: 12px 16px; font-size: 0.65rem; color: #4a5568; white-space: pre-wrap; border-top: 1px solid #1e2130; }
        .summary { background: #0f1020; border: 1px solid #1e2130; border-radius: 8px; padding: 16px 20px; margin-bottom: 32px; }
        .summary h2 { color: #A7C7E7; font-size: 0.7rem; letter-spacing: 2px; text-transform: uppercase; margin-bottom: 12px; }
        .summary-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 8px; }
        .summary-item { display: flex; justify-content: space-between; padding: 4px 0; border-bottom: 1px solid #1e2130; }
        .summary-item span:first-child { color: #B8A7E7; }
        .summary-item span:last-child { color: #64748b; }
        .warn { color: #fbbf24; margin-top: 24px; padding: 10px 14px; border: 1px solid #78350f; border-radius: 6px; font-size: 0.7rem; }
        .export-btn { background: rgba(167,199,231,0.1); border: 1px solid rgba(167,199,231,0.3); color: #A7C7E7; padding: 8px 16px; border-radius: 6px; cursor: pointer; font-family: monospace; font-size: 0.65rem; letter-spacing: 1px; margin-bottom: 20px; }
        .index-badge { display: inline-block; padding: 1px 6px; border-radius: 3px; font-size: 0.58rem; margin-right: 4px; background: rgba(184,167,231,0.15); color: #B8A7E7; border: 1px solid rgba(184,167,231,0.2); }
        .index-badge.primary { background: rgba(110,231,183,0.1); color: #6ee7b7; border-color: rgba(110,231,183,0.2); }
        .index-badge.unique  { background: rgba(232,192,122,0.1); color: #E8C07A; border-color: rgba(232,192,122,0.2); }
    </style>
</head>
<body>

<h1>Structure BDD — adnmovie.fr</h1>
<p style="color:#64748b;font-size:0.65rem;margin-bottom:20px;">Généré le <?= date('d/m/Y H:i:s') ?> · <?= count($tables) ?> tables</p>

<button class="export-btn" onclick="exportJson()">Exporter en JSON</button>

<!-- Résumé -->
<div class="summary">
    <h2>Résumé</h2>
    <div class="summary-grid">
        <?php foreach ($tables as $t): ?>
        <div class="summary-item">
            <span><?= $t ?></span>
            <span><?= number_format($export[$t]['row_count']) ?> lignes</span>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Détail par table -->
<?php foreach ($export as $table => $info): ?>
<div class="table-block">
    <div class="table-header">
        <span class="table-name"><?= $table ?></span>
        <span class="row-count"><?= number_format($info['row_count']) ?> lignes</span>
    </div>
    <table>
        <tr>
            <th>Colonne</th>
            <th>Type</th>
            <th>Null</th>
            <th>Clé</th>
            <th>Défaut</th>
            <th>Extra</th>
        </tr>
        <?php foreach ($info['columns'] as $col): ?>
        <tr>
            <td class="col-name"><?= $col['Field'] ?></td>
            <td class="col-type"><?= $col['Type'] ?></td>
            <td class="col-null"><?= $col['Null'] ?></td>
            <td class="col-key">
                <?php if ($col['Key'] === 'PRI'): ?>
                    <span class="index-badge primary">PRI</span>
                <?php elseif ($col['Key'] === 'UNI'): ?>
                    <span class="index-badge unique">UNI</span>
                <?php elseif ($col['Key'] === 'MUL'): ?>
                    <span class="index-badge">IDX</span>
                <?php endif; ?>
            </td>
            <td class="col-default"><?= $col['Default'] ?? '—' ?></td>
            <td class="col-extra"><?= $col['Extra'] ?></td>
        </tr>
        <?php endforeach; ?>
    </table>

    <!-- Index -->
    <?php
    $idxGroups = [];
    foreach ($info['indexes'] as $idx) {
        $idxGroups[$idx['Key_name']][] = $idx['Column_name'];
    }
    if (count($idxGroups) > 1): // > 1 car PRIMARY est toujours là
    ?>
    <div style="padding:8px 16px;background:#080810;border-top:1px solid #1e2130;">
        <span style="color:#64748b;font-size:0.6rem;text-transform:uppercase;letter-spacing:1px;">Index : </span>
        <?php foreach ($idxGroups as $name => $cols): ?>
            <span class="index-badge <?= $name === 'PRIMARY' ? 'primary' : '' ?>"><?= $name ?> (<?= implode(', ', $cols) ?>)</span>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- CREATE TABLE -->
    <div class="create-block"><?= htmlspecialchars($info['create']) ?></div>
</div>
<?php endforeach; ?>

<div class="warn">⚠️ Supprime ce fichier du serveur après utilisation !</div>

<script>
const DATA = <?= json_encode($export, JSON_PRETTY_PRINT) ?>;
function exportJson() {
    const blob = new Blob([JSON.stringify(DATA, null, 2)], { type: 'application/json' });
    const a = document.createElement('a');
    a.href = URL.createObjectURL(blob);
    a.download = 'adnmovie_db_structure_<?= date('Ymd') ?>.json';
    a.click();
}
</script>
</body>
</html>