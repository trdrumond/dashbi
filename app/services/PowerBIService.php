<?php

require_once __DIR__ . '/../config/Config.php';
require_once __DIR__ . '/TokenService.php';
require_once __DIR__ . '/../helpers/HttpClient.php';

/**
 * PowerBIService - integração com API Power BI
 * Listagem de workspaces, relatórios e geração de embed token.
 * PHP 7.3+
 */
class PowerBIService
{
    /** @var TokenService */
    private $tokenService;

    public function __construct()
    {
        $this->tokenService = new TokenService();
    }

    /**
     * Lista workspaces do tenant (grupos no Power BI)
     * O Service Principal precisa ser membro de cada workspace no Power BI para aparecer aqui.
     * @param array $tenant registro do banco (id, tenant_id, client_id, client_secret, workspace_id)
     * @return array
     * @throws RuntimeException se a API Power BI retornar erro (ex.: acesso negado)
     */
    public function listWorkspaces(array $tenant): array
    {
        return $this->listWorkspacesWithRetry($tenant, false);
    }

    /**
     * Chamada interna com retry: em 401 invalida cache e tenta uma vez com token novo.
     */
    private function listWorkspacesWithRetry(array $tenant, bool $alreadyRetried): array
    {
        $token = $this->tokenService->getAccessToken($tenant);
        $url = Config::POWERBI_API_BASE . '/groups';
        $response = HttpClient::get($url, ["Authorization: Bearer {$token}"]);
        if ($response === null) {
            throw new RuntimeException('Falha ao conectar na API Power BI.');
        }
        $code = (int) ($response['_http_code'] ?? 0);
        if ($code === 401 && !$alreadyRetried) {
            $this->tokenService->invalidate($tenant['id']);
            return $this->listWorkspacesWithRetry($tenant, true);
        }
        $this->throwIfPowerBIError($response, 'listar workspaces');
        if (isset($response['value'])) {
            return $response['value'];
        }
        return [];
    }

    /**
     * Lança exceção se a resposta da API Power BI contiver erro.
     */
    private function throwIfPowerBIError(array $response, string $acao): void
    {
        $code = (int) ($response['_http_code'] ?? 0);
        if ($code >= 400 && isset($response['error'])) {
            $msg = $response['error']['message'] ?? $response['error']['code'] ?? 'Erro da API Power BI';
            throw new RuntimeException("Power BI ao {$acao}: {$msg}");
        }
        if ($code === 401) {
            $apiMsg = $response['error']['message'] ?? $response['error']['code'] ?? $response['message'] ?? '';
            $dica = ' O token foi obtido, mas o Power BI rejeitou o uso na API.';
            if ($apiMsg !== '') {
                $dica .= ' Resposta da API: ' . (is_string($apiMsg) ? $apiMsg : json_encode($apiMsg)) . '.';
            }
            $dica .= '<br><br>Confira: <br>1) Este sistema usa Service Principal (sem usuário conectado). No Azure, não dependa de permissões delegadas (com usuário) como Workspace.Read.All/Report.Read.All para este fluxo. '
                . '<br>2) https://admin.powerbi.com → Configurações do locatário → Configurações de desenvolvedor → "Permitir que os service principals usem as APIs do Power BI" = Ativado; se estiver "Apenas grupos de segurança específicos" ou "Aplicativos específicos", inclua o seu app (ou altere para "Toda a organização"). '
                . '<br>3) Adicione o aplicativo como membro (ou admin) em cada workspace no app.powerbi.com. '
                . '<br>4) Aguarde alguns minutos após alterações (propagação). <br><br>Consulte docs/checklist-powerbi-workspace.md (seção 6: Checklist técnico (401 na API)).';
            throw new RuntimeException("Power BI ao {$acao}: HTTP 401 (Não autorizado)." . $dica);
        }
        if ($code === 403) {
            throw new RuntimeException("Power BI ao {$acao}: Acesso negado (403). Verifique: 1) Permitir Service Principals no Admin do Power BI; 2) Adicionar o aplicativo como membro de cada workspace. Consulte docs/checklist-powerbi-workspace.md (seção 6: Checklist técnico (401 na API)).");
        }
        if ($code >= 400) {
            throw new RuntimeException("Power BI ao {$acao}: HTTP {$code}. " . ($response['error']['message'] ?? ''));
        }
    }

    /**
     * Lista relatórios de um workspace
     * @param array $tenant
     * @param string $workspaceId ID do grupo Power BI (GUID)
     */
    public function listReports(array $tenant, string $workspaceId): array
    {
        return $this->listReportsWithRetry($tenant, $workspaceId, false);
    }

    private function listReportsWithRetry(array $tenant, string $workspaceId, bool $alreadyRetried): array
    {
        $token = $this->tokenService->getAccessToken($tenant);
        $url = Config::POWERBI_API_BASE . '/groups/' . $workspaceId . '/reports';
        $response = HttpClient::get($url, ["Authorization: Bearer {$token}"]);
        if ($response === null) {
            throw new RuntimeException('Falha ao conectar na API Power BI.');
        }
        $code = (int) ($response['_http_code'] ?? 0);
        if ($code === 401 && !$alreadyRetried) {
            $this->tokenService->invalidate($tenant['id']);
            return $this->listReportsWithRetry($tenant, $workspaceId, true);
        }
        $this->throwIfPowerBIError($response, 'listar relatórios do workspace');
        if (isset($response['value'])) {
            return $response['value'];
        }
        return [];
    }

    /**
     * Obtém um relatório individual (retorna description quando disponível).
     * Útil quando a lista não inclui description (API padrão).
     */
    public function getReport(array $tenant, string $workspaceId, string $reportId): ?array
    {
        if ($reportId === '') {
            return null;
        }
        $token = $this->tokenService->getAccessToken($tenant);
        $url = Config::POWERBI_API_BASE . '/groups/' . $workspaceId . '/reports/' . $reportId;
        $response = HttpClient::get($url, ["Authorization: Bearer {$token}"]);
        if ($response === null || ($response['_http_code'] ?? 0) >= 400) {
            return null;
        }
        if (isset($response['error'])) {
            return null;
        }
        return $response;
    }

    /**
     * Lista as páginas (abas) de um relatório no Power BI.
     * GET /groups/{groupId}/reports/{reportId}/pages
     * @return array [{ name, displayName, order }, ...] ou []
     */
    public function getReportPages(array $tenant, string $workspaceId, string $reportId): array
    {
        $token = $this->tokenService->getAccessToken($tenant);
        $url = Config::POWERBI_API_BASE . '/groups/' . $workspaceId . '/reports/' . $reportId . '/pages';
        $response = HttpClient::get($url, ["Authorization: Bearer {$token}"]);
        if ($response === null) {
            return [];
        }
        $code = (int) ($response['_http_code'] ?? 0);
        if ($code === 403 || $code === 404 || $code >= 400) {
            return [];
        }
        $value = $response['value'] ?? [];
        return is_array($value) ? $value : [];
    }

    /**
     * Lista relatórios do workspace via Admin API (retorna description, modifiedBy, createdBy quando disponíveis).
     * Requer Tenant.Read.All com token delegado; com Service Principal pode funcionar sem o escopo.
     * @return array|null lista de relatórios ou null em caso de 403 (fallback para listReports)
     */
    public function listReportsAsAdmin(array $tenant, string $workspaceId): ?array
    {
        $token = $this->tokenService->getAccessToken($tenant);
        $url = Config::POWERBI_API_BASE . '/admin/groups/' . $workspaceId . '/reports';
        $response = HttpClient::get($url, ["Authorization: Bearer {$token}"]);
        if ($response === null) {
            return null;
        }
        $code = (int) ($response['_http_code'] ?? 0);
        if ($code === 403 || $code === 404) {
            return null;
        }
        $this->throwIfPowerBIError($response, 'listar relatórios (admin)');
        if (isset($response['value'])) {
            return $response['value'];
        }
        return [];
    }

    /**
     * Retorna a data/hora do último refresh concluído do dataset (para preencher ultima_atualizacao).
     * Para Service Principal (datasets em workspace) informe $groupId para usar o endpoint por group.
     * Requer escopo Dataset.Read.All ou Dataset.ReadWrite.All no app Azure.
     * @param array $tenant
     * @param string $datasetId
     * @param string|null $groupId powerbi_workspace_id; se informado, usa endpoint por workspace (evita 403)
     * @return string|null endTime em formato ISO 8601 ou null
     */
    public function getDatasetLastRefresh(array $tenant, string $datasetId, ?string $groupId = null): ?string
    {
        if ($datasetId === '') {
            return null;
        }
        $token = $this->tokenService->getAccessToken($tenant);
        $url = ($groupId !== null && $groupId !== '')
            ? Config::POWERBI_API_BASE . '/groups/' . $groupId . '/datasets/' . $datasetId . '/refreshes?$top=10'
            : Config::POWERBI_API_BASE . '/datasets/' . $datasetId . '/refreshes?$top=10';
        $response = HttpClient::get($url, ["Authorization: Bearer {$token}"]);
        if ($response === null) {
            return null;
        }
        $code = (int) ($response['_http_code'] ?? 0);
        if ($code === 403 || $code === 404) {
            return null;
        }
        if ($code >= 400) {
            return null;
        }
        $value = $response['value'] ?? [];
        if (!is_array($value) || empty($value)) {
            return null;
        }
        $best = null;
        foreach ($value as $entry) {
            $status = isset($entry['status']) ? (string) $entry['status'] : '';
            $endTime = isset($entry['endTime']) ? trim((string) $entry['endTime']) : '';
            if ($status === 'Completed' && $endTime !== '') {
                if ($best === null || strcmp($endTime, $best) > 0) {
                    $best = $endTime;
                }
            }
        }
        return $best;
    }

    /**
     * Retorna o histórico de refresh do dataset (status, datas, erros).
     * Para Service Principal use o endpoint por workspace (group).
     * Requer Dataset.Read.All ou Dataset.ReadWrite.All.
     * @param array $tenant
     * @param string $groupId powerbi_workspace_id (workspace no Power BI)
     * @param string $datasetId
     * @param int $top Máximo de entradas (padrão 20)
     * @return array Lista de entradas com startTime, endTime, status, serviceExceptionJson
     */
    public function getDatasetRefreshHistory(array $tenant, string $groupId, string $datasetId, int $top = 20): array
    {
        if ($groupId === '' || $datasetId === '') {
            return [];
        }
        $token = $this->tokenService->getAccessToken($tenant);
        $url = Config::POWERBI_API_BASE . '/groups/' . $groupId . '/datasets/' . $datasetId . '/refreshes?$top=' . max(1, min(60, $top));
        $response = HttpClient::get($url, ["Authorization: Bearer {$token}"]);
        if ($response === null) {
            return [];
        }
        $code = (int) ($response['_http_code'] ?? 0);
        if ($code === 403 || $code === 404 || $code >= 400) {
            return [];
        }
        $value = $response['value'] ?? [];
        return is_array($value) ? $value : [];
    }

    /**
     * Dispara um refresh do dataset no Power BI (endpoint por workspace para Service Principal).
     * Requer Dataset.ReadWrite.All.
     * @param array $tenant
     * @param string $groupId powerbi_workspace_id (workspace no Power BI)
     * @param string $datasetId
     * @return array ['success' => bool, 'request_id' => string|null, 'message' => string]
     */
    public function triggerDatasetRefresh(array $tenant, string $groupId, string $datasetId): array
    {
        if ($groupId === '' || $datasetId === '') {
            return ['success' => false, 'request_id' => null, 'message' => 'Workspace (group) e dataset são obrigatórios.'];
        }
        $token = $this->tokenService->getAccessToken($tenant);
        $url = Config::POWERBI_API_BASE . '/groups/' . $groupId . '/datasets/' . $datasetId . '/refreshes';
        $body = ['notifyOption' => 'NoNotification'];
        $response = HttpClient::postJson($url, ["Authorization: Bearer {$token}"], $body);
        if ($response === null) {
            return ['success' => false, 'request_id' => null, 'message' => 'Falha ao conectar na API Power BI.'];
        }
        $code = (int) ($response['_http_code'] ?? 0);
        if ($code === 202) {
            $requestId = $response['requestId'] ?? null;
            return ['success' => true, 'request_id' => $requestId, 'message' => 'Refresh disparado.'];
        }
        $error = $response['error'] ?? [];
        $msg = isset($error['message']) ? $error['message'] : 'Erro ao disparar refresh (HTTP ' . $code . ').';
        return ['success' => false, 'request_id' => null, 'message' => $msg];
    }

    /**
     * Gera embed token para um relatório (Service Principal)
     * Com RLS: informe datasetId, userPrincipal (email ou empresa_id) e rlsRole para EffectiveIdentity.
     * @param array $tenant
     * @param string $workspaceId powerbi_workspace_id (groupId)
     * @param string $reportId powerbi_report_id
     * @param string|null $datasetId ID do dataset (obrigatório se usar RLS)
     * @param string|null $userPrincipal Para RLS: email do usuário ou empresa_id (deve bater com DAX USERPRINCIPALNAME() ou CUSTOMDATA())
     * @param string|null $rlsRole Nome da role no dataset (ex.: RLS_ROLE)
     * @param bool $allowSaveAs Permitir "Salvar como" no embed (conforme permissão do usuário)
     * @return array|null [embedUrl, accessToken, expiration] ou null em caso de erro
     */
    public function getEmbedToken(
        array $tenant,
        string $workspaceId,
        string $reportId,
        ?string $datasetId = null,
        ?string $userPrincipal = null,
        ?string $rlsRole = null,
        bool $allowSaveAs = false
    ): ?array {
        $token = $this->tokenService->getAccessToken($tenant);
        $url = Config::POWERBI_API_BASE . '/groups/' . $workspaceId . '/reports/' . $reportId . '/GenerateToken';

        $body = ['accessLevel' => 'View', 'allowSaveAs' => $allowSaveAs];

        if ($datasetId !== null && $datasetId !== '' && $userPrincipal !== null && $userPrincipal !== '' && $rlsRole !== null && $rlsRole !== '') {
            $body['identities'] = [[
                'username' => $userPrincipal,
                'roles' => [$rlsRole],
                'datasets' => [$datasetId],
            ]];
        }

        $response = HttpClient::postJson($url, ["Authorization: Bearer {$token}"], $body);

        if ($response === null) {
            return null;
        }

        $code = (int) ($response['_http_code'] ?? 0);
        if ($code === 401) {
            $this->tokenService->invalidate($tenant['id']);
            throw new RuntimeException('Token Power BI expirado. Tente novamente.');
        }
        if ($code >= 400) {
            $msg = $response['error']['message'] ?? $response['error']['code'] ?? $response['message'] ?? "HTTP {$code}";
            throw new RuntimeException('Falha ao gerar embed token: ' . (is_string($msg) ? $msg : json_encode($msg)));
        }

        // A API GenerateToken retorna apenas token, tokenId e expiration (não retorna embedUrl)
        if (!empty($response['token'])) {
            return [
                'accessToken' => $response['token'],
                'expiration' => $response['expiration'] ?? null,
            ];
        }

        return null;
    }

    /**
     * Testa conexão com o tenant (obtém token)
     */
    public function testConnection(array $tenant): bool
    {
        try {
            $this->tokenService->getAccessToken($tenant);
            return true;
        } catch (Exception $e) {
            return false;
        }
    }
}
