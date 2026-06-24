<?php

require_once __DIR__ . '/../../core/Controller.php';
require_once __DIR__ . '/../../services/AuthService.php';
require_once __DIR__ . '/../../services/ReportService.php';
require_once __DIR__ . '/../../repositories/WorkspaceRepository.php';
require_once __DIR__ . '/../../config/Config.php';

/**
 * WorkspaceController - listagem de workspaces e relatórios
 */
class WorkspaceController extends Controller
{
    /** @var AuthService */
    private $authService;

    /** @var ReportService */
    private $reportService;

    /** @var WorkspaceRepository */
    private $workspaceRepository;

    public function __construct()
    {
        $this->authService = new AuthService();
        $this->reportService = new ReportService();
        $this->workspaceRepository = new WorkspaceRepository();
    }

    private function requireAuth(): ?Response
    {
        if (!$this->authService->getCurrentUser()) {
            return $this->error('Não autenticado', 401);
        }
        return null;
    }

    private function requireAdmin(): ?Response
    {
        if (!$this->authService->hasProfile(Config::PROFILE_ADMIN)) {
            return $this->error('Acesso negado', 403);
        }
        return null;
    }

    /**
     * GET /api/tenants/{tenantId}/workspaces
     */
    public function index(): Response
    {
        if ($r = $this->requireAuth()) {
            return $r;
        }
        $tenantId = (int) $this->param('tenantId');
        $list = $this->workspaceRepository->findByTenant($tenantId);
        return $this->success($list);
    }

    /**
     * GET /api/workspaces/{id}/reports - relatórios que o usuário pode ver
     */
    public function reports(): Response
    {
        if ($r = $this->requireAuth()) {
            return $r;
        }
        $workspaceId = (int) $this->param('id');
        $userId = $this->authService->getCurrentUserId();
        $list = $this->reportService->findAllowedByUser($userId, $workspaceId);
        return $this->success($list);
    }

    /**
     * GET /api/workspaces/{id}/reports/all - todos os relatórios do workspace (admin)
     */
    public function reportsAll(): Response
    {
        if ($r = $this->requireAdmin()) {
            return $r;
        }
        $workspaceId = (int) $this->param('id');
        $list = $this->reportService->findByWorkspace($workspaceId);
        return $this->success($list);
    }
}
