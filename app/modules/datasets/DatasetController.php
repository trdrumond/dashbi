<?php

require_once __DIR__ . '/../../core/Controller.php';
require_once __DIR__ . '/../../services/AuthService.php';
require_once __DIR__ . '/../../config/Config.php';
require_once __DIR__ . '/../../repositories/ReportRepository.php';
require_once __DIR__ . '/../../repositories/TenantRepository.php';
require_once __DIR__ . '/../../services/PowerBIService.php';

/**
 * DatasetController - controle de refresh de datasets Power BI (admin)
 */
class DatasetController extends Controller
{
    /** @var AuthService */
    private $authService;

    /** @var ReportRepository */
    private $reportRepository;

    /** @var TenantRepository */
    private $tenantRepository;

    /** @var PowerBIService */
    private $powerBIService;

    public function __construct()
    {
        $this->authService = new AuthService();
        $this->reportRepository = new ReportRepository();
        $this->tenantRepository = new TenantRepository();
        $this->powerBIService = new PowerBIService();
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
     * GET /api/datasets - lista datasets (a partir dos relatórios) com workspace e tenant
     */
    public function index(): Response
    {
        if ($r = $this->requireMaster()) {
            return $r;
        }
        $list = $this->reportRepository->getDatasetsWithWorkspace();
        return $this->success($list);
    }

    /**
     * GET /api/datasets/refreshes?dataset_id=...&tenant_id=... (& group_id opcional) - histórico de refresh do dataset
     */
    public function refreshes(): Response
    {
        if ($r = $this->requireMaster()) {
            return $r;
        }
        $datasetId = trim((string) $this->request->get('dataset_id'));
        $tenantId = (int) $this->request->get('tenant_id');
        $groupId = trim((string) $this->request->get('group_id'));
        if ($datasetId === '' || $tenantId <= 0) {
            return $this->error('dataset_id e tenant_id são obrigatórios.', 400);
        }
        if ($groupId === '') {
            $groupId = $this->reportRepository->getGroupIdByDatasetAndTenant($datasetId, $tenantId);
            if ($groupId === null || $groupId === '') {
                return $this->error('Dataset não encontrado para este tenant ou group_id não disponível.', 404);
            }
        }
        $tenant = $this->tenantRepository->findById($tenantId);
        if (!$tenant) {
            return $this->error('Tenant não encontrado.', 404);
        }
        $top = min(60, max(1, (int) ($this->request->get('top') ?: 20)));
        $history = $this->powerBIService->getDatasetRefreshHistory($tenant, $groupId, $datasetId, $top);
        return $this->success($history);
    }

    /**
     * POST /api/datasets/refresh - dispara refresh do dataset (body: dataset_id, tenant_id; group_id opcional)
     */
    public function refresh(): Response
    {
        if ($r = $this->requireMaster()) {
            return $r;
        }
        $body = $this->request->getBody();
        $datasetId = isset($body['dataset_id']) ? trim((string) $body['dataset_id']) : '';
        $tenantId = isset($body['tenant_id']) ? (int) $body['tenant_id'] : 0;
        $groupId = isset($body['group_id']) ? trim((string) $body['group_id']) : '';
        if ($datasetId === '' || $tenantId <= 0) {
            return $this->error('dataset_id e tenant_id são obrigatórios no body.', 400);
        }
        if ($groupId === '') {
            $groupId = $this->reportRepository->getGroupIdByDatasetAndTenant($datasetId, $tenantId);
            if ($groupId === null || $groupId === '') {
                return $this->error('Dataset não encontrado para este tenant ou group_id não disponível.', 404);
            }
        }
        $tenant = $this->tenantRepository->findById($tenantId);
        if (!$tenant) {
            return $this->error('Tenant não encontrado.', 404);
        }
        $result = $this->powerBIService->triggerDatasetRefresh($tenant, $groupId, $datasetId);
        if ($result['success']) {
            return $this->success([
                'message' => $result['message'],
                'request_id' => $result['request_id'],
            ]);
        }
        return $this->error($result['message'], 400);
    }
}
