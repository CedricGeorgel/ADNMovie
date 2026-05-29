<?php
require_once __DIR__ . '/../functions/utils.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit; }
require_role('admin');

$message   = trim($_POST['message'] ?? '');
$color     = $_POST['color'] ?? 'blue';
$is_active = isset($_POST['is_active']) ? 1 : 0;

$allowed_colors = ['blue', 'green', 'red', 'yellow', 'orange', 'purple'];
if (!in_array($color, $allowed_colors)) $color = 'blue';

db_execute("
    INSERT INTO site_banner (id, message, color, is_active)
    VALUES (1, ?, ?, ?)
    ON DUPLICATE KEY UPDATE message = VALUES(message), color = VALUES(color), is_active = VALUES(is_active)
", [$message, $color, $is_active]);

header('Location: ../admin.php?banner_saved=1');
exit;
