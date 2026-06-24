<?php

//
// CRON - Sincroniza workspaces e relatórios de todos os tenants ativos
// Agendar: "*/15 * * * * php /path/to/workbi/cron/sync_workspaces.php"
//  PHP 7.3+
// 

$base = dirname(__DIR__);
require_once $base . '/app/bootstrap.php';

require_once $base . '/app/repositories/TenantRepository.php';
require_once $base . '/app/services/ReportService.php';

$tenantRepo = new TenantRepository();
$reportService = new ReportService();

$tenants = $tenantRepo->findAll(true);
$total = ['workspaces' => 0, 'reports' => 0];

foreach ($tenants as $t) {
    try {
        $r = $reportService->syncTenant((int) $t['id']);
        $total['workspaces'] += $r['workspaces'];
        $total['reports'] += $r['reports'];
    } catch (Throwable $e) {
        error_log('[sync_workspaces] Tenant ' . $t['id'] . ': ' . $e->getMessage());
    }
}

echo date('Y-m-d H:i:s') . ' Sync OK - Workspaces: ' . $total['workspaces'] . ', Reports: ' . $total['reports'] . PHP_EOL;
