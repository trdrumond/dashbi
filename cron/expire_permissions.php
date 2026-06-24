<?php

/**
 * CRON - Remove permissões vencidas (data_fim < hoje)
 * Agendar: 0 2 * * * php /path/to/workbi/cron/expire_permissions.php
 * PHP 7.3+
 */

$base = dirname(__DIR__);
require_once $base . '/app/bootstrap.php';

require_once $base . '/app/config/Database.php';

$db = Database::getConnection();
$stmt = $db->prepare('DELETE FROM permissions WHERE data_fim IS NOT NULL AND data_fim < CURDATE()');
$stmt->execute();
$deleted = $stmt->rowCount();

echo date('Y-m-d H:i:s') . " Permissões expiradas removidas: {$deleted}" . PHP_EOL;
