<?php

require_once __DIR__ . '/../../core/Controller.php';
require_once __DIR__ . '/../../services/AuthService.php';
require_once __DIR__ . '/../../services/ReportService.php';
require_once __DIR__ . '/../../config/Config.php';

/**
 * SyncController - sincronização de workspaces/relatórios (admin)
 */
class SyncController extends Controller
{
    /** @var AuthService */
    private $authService;

    /** @var ReportService */
    private $reportService;

    public function __construct()
    {
        $this->authService = new AuthService();
        $this->reportService = new ReportService();
    }

    private function requireMaster(): ?Response
    {
        if (!$this->authService->getCurrentUser()) {
            return $this->error('Não autenticado', 401);
        }
        if (!$this->authService->hasProfile(Config::PROFILE_MASTER)) {
            return $this->error('Acesso negado. Apenas perfil master.', 403);
        }
        return null;
    }

    /**
     * POST /api/sync/tenant/{tenantId} (apenas master)
     */
    public function tenant(): Response
    {
        if ($r = $this->requireMaster()) {
            return $r;
        }
        $tenantId = (int) $this->param('tenantId');
        try {
            $result = $this->reportService->syncTenant($tenantId);
            return $this->success($result, 'Sincronização concluída');
        } catch (Exception $e) {
            return $this->error($e->getMessage(), 400);
        }
    }

    /**
     * POST /api/sync/all
     */
    public function all(): Response
    {
        if ($r = $this->requireMaster()) {
            return $r;
        }
        try {
            $result = $this->reportService->syncAll();
            return $this->success($result, 'Sincronização de todos os tenants concluída');
        } catch (Exception $e) {
            return $this->error($e->getMessage(), 400);
        }
    }
}
