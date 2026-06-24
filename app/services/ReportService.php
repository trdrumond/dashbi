<?php

require_once __DIR__ . '/../repositories/ReportRepository.php';
require_once __DIR__ . '/../repositories/WorkspaceRepository.php';
require_once __DIR__ . '/../repositories/UserRepository.php';
require_once __DIR__ . '/PowerBIService.php';
require_once __DIR__ . '/../repositories/TenantRepository.php';
require_once __DIR__ . '/TenantService.php';

/**
 * ReportService - relatórios e sincronização com Power BI
 * PHP 7.3+
 */
class ReportService
{
    /** @var ReportRepository */
    private $reportRepository;

    /** @var WorkspaceRepository */
    private $workspaceRepository;

    /** @var UserRepository */
    private $userRepository;

    /** @var PowerBIService */
    private $powerBIService;

    /** @var TenantRepository */
    private $tenantRepository;

    /** @var TenantService */
    private $tenantService;

    public function __construct()
    {
        $this->reportRepository = new ReportRepository();
        $this->workspaceRepository = new WorkspaceRepository();
        $this->userRepository = new UserRepository();
        $this->powerBIService = new PowerBIService();
        $this->tenantRepository = new TenantRepository();
        $this->tenantService = new TenantService();
    }

    public function findByWorkspace(int $workspaceId): array
    {
        return $this->reportRepository->findByWorkspace($workspaceId);
    }

    public function findById(int $id): ?array
    {
        return $this->reportRepository->findById($id);
    }

    public function update(int $id, array $data): bool
    {
        return $this->reportRepository->update($id, $data);
    }

    /**
     * Relatórios que o usuário pode ver (para listagem no portal)
     */
    public function findAllowedByUser(int $userId, ?int $workspaceId = null): array
    {
        return $this->reportRepository->findAllowedByUser($userId, $workspaceId);
    }

    /**
     * Todos os relatórios ativos (para admin/master na tela "Meus relatórios")
     */
    public function findAllActive(?int $workspaceId = null): array
    {
        return $this->reportRepository->findAllActive($workspaceId);
    }

    /**
     * Sincroniza workspaces e relatórios de um tenant a partir da API Power BI
     * Usa TenantService para obter o tenant com client_secret descriptografado.
     */
    public function syncTenant(int $tenantId): array
    {
        $tenant = $this->tenantService->findByIdForApi($tenantId);
        if (!$tenant) {
            throw new InvalidArgumentException('Tenant não encontrado');
        }

        $workspaceFallbackIds = $this->parseWorkspaceFallbackIds((string) ($tenant['workspace_id'] ?? ''));
        $workspaces = [];
        try {
            $workspaces = $this->powerBIService->listWorkspaces($tenant);
        } catch (RuntimeException $e) {
            $isUnauthorized = stripos($e->getMessage(), 'HTTP 401') !== false || stripos($e->getMessage(), 'Não autorizado') !== false;
            if (!$isUnauthorized || empty($workspaceFallbackIds)) {
                throw $e;
            }
            // Fallback: quando /groups retorna 401, tenta sincronizar apenas o workspace configurado no tenant.
            foreach ($workspaceFallbackIds as $wsId) {
                $workspaces[] = [
                    'id' => $wsId,
                    'name' => $tenant['nome'] ?? ('Workspace ' . $wsId),
                ];
            }
        }

        // Fallback adicional: alguns tenants retornam 200 com lista vazia em /groups.
        // Nesse caso, usa o(s) workspace_id cadastrado(s) para permitir sincronização direta.
        if (empty($workspaces) && !empty($workspaceFallbackIds)) {
            foreach ($workspaceFallbackIds as $wsId) {
                $workspaces[] = [
                    'id' => $wsId,
                    'name' => $tenant['nome'] ?? ('Workspace ' . $wsId),
                ];
            }
        }
        $synced = [
            'tenant_id' => $tenantId,
            'tenant_name' => $tenant['nome'] ?? ('Tenant ' . $tenantId),
            'workspaces' => 0,
            'reports' => 0,
            'workspace_items' => [],
        ];

        foreach ($workspaces as $ws) {
            $wsId = $ws['id'] ?? null;
            $nome = $ws['name'] ?? 'Sem nome';
            if (!$wsId) {
                continue;
            }
            $localWorkspaceId = $this->workspaceRepository->upsert($tenantId, $wsId, $nome);
            $synced['workspaces']++;

            $reports = $this->powerBIService->listReportsAsAdmin($tenant, $wsId);
            $usedAdminApi = ($reports !== null);
            if ($reports === null) {
                $reports = $this->powerBIService->listReports($tenant, $wsId);
            }
            $workspaceItem = [
                'workspace_id' => $wsId,
                'workspace_name' => $nome,
                'local_workspace_id' => $localWorkspaceId,
                'reports' => [],
            ];
            foreach ($reports as $rep) {
                $reportPowerBiId = (string) ($rep['id'] ?? '');
                $reportName = (string) ($rep['name'] ?? 'Sem nome');
                $reportEmbedUrl = $rep['embedUrl'] ?? null;
                $reportDatasetId = $rep['datasetId'] ?? null;

                $descricao = null;
                if (!empty($rep['description']) && is_string($rep['description'])) {
                    $descricao = trim($rep['description']);
                } elseif (!empty($rep['Description']) && is_string($rep['Description'])) {
                    $descricao = trim($rep['Description']);
                }
                if ($descricao === null || $descricao === '') {
                    $fullReport = $this->powerBIService->getReport($tenant, $wsId, $reportPowerBiId);
                    if ($fullReport !== null && !empty($fullReport['description']) && is_string($fullReport['description'])) {
                        $descricao = trim($fullReport['description']);
                    }
                    if ($fullReport !== null && ($descricao === null || $descricao === '') && !empty($fullReport['Description']) && is_string($fullReport['Description'])) {
                        $descricao = trim($fullReport['Description']);
                    }
                }

                $lastRefresh = null;
                if ($reportDatasetId !== null && $reportDatasetId !== '') {
                    $lastRefresh = $this->powerBIService->getDatasetLastRefresh($tenant, $reportDatasetId, $wsId);
                }

                $extra = [];
                if ($descricao !== null && $descricao !== '') {
                    $extra['descricao'] = $descricao;
                }
                if ($lastRefresh !== null && $lastRefresh !== '') {
                    $extra['ultima_atualizacao'] = $this->iso8601ToMysqlDatetime($lastRefresh);
                }
                if ($usedAdminApi) {
                    $ownerEmail = !empty($rep['modifiedBy']) ? trim((string) $rep['modifiedBy']) : (!empty($rep['createdBy']) ? trim((string) $rep['createdBy']) : null);
                    if ($ownerEmail !== null && $ownerEmail !== '') {
                        $extra['dono_texto'] = $ownerEmail;
                        $ownerUser = $this->userRepository->findByEmail($ownerEmail);
                        if ($ownerUser !== null) {
                            $extra['dono_id'] = (int) $ownerUser['id'];
                        }
                    }
                }
                $this->reportRepository->upsert(
                    $localWorkspaceId,
                    $reportPowerBiId,
                    $reportName,
                    $reportEmbedUrl,
                    $reportDatasetId,
                    $extra
                );
                $workspaceItem['reports'][] = [
                    'report_id' => $reportPowerBiId,
                    'report_name' => $reportName,
                    'dataset_id' => $reportDatasetId,
                ];
                $synced['reports']++;
            }
            $synced['workspace_items'][] = $workspaceItem;
        }

        return $synced;
    }

    /**
     * Aceita um ou mais IDs de workspace separados por vírgula, ponto e vírgula ou quebra de linha.
     * Ex.: "id1,id2" ou "id1; id2"
     */
    private function parseWorkspaceFallbackIds(string $raw): array
    {
        $raw = trim($raw);
        if ($raw === '') {
            return [];
        }

        $parts = preg_split('/[\s,;]+/', $raw);
        if (!is_array($parts)) {
            return [];
        }

        $ids = [];
        foreach ($parts as $p) {
            $id = trim((string) $p);
            if ($id !== '') {
                $ids[$id] = true;
            }
        }

        return array_keys($ids);
    }

    /**
     * Sincroniza todos os tenants ativos
     */
    public function syncAll(): array
    {
        $tenants = $this->tenantRepository->findAll(true);
        $total = ['workspaces' => 0, 'reports' => 0, 'tenants' => []];
        foreach ($tenants as $t) {
            $r = $this->syncTenant((int) $t['id']);
            $total['workspaces'] += $r['workspaces'];
            $total['reports'] += $r['reports'];
            $total['tenants'][] = $r;
        }
        return $total;
    }

    /**
     * Converte data em ISO 8601 (ex.: 2026-01-20T11:16:12.503Z) para formato MySQL DATETIME (Y-m-d H:i:s).
     */
    private function iso8601ToMysqlDatetime(string $iso8601): ?string
    {
        $iso8601 = trim($iso8601);
        if ($iso8601 === '') {
            return null;
        }
        try {
            $dt = new DateTime($iso8601);
            return $dt->format('Y-m-d H:i:s');
        } catch (Exception $e) {
            return null;
        }
    }
}
