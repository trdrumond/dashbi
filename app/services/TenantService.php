<?php

require_once __DIR__ . '/../repositories/TenantRepository.php';
require_once __DIR__ . '/../config/Config.php';
require_once __DIR__ . '/../helpers/CryptoHelper.php';
require_once __DIR__ . '/TokenService.php';
require_once __DIR__ . '/PowerBIService.php';

/**
 * TenantService - regras para tenants (conexão Microsoft)
 * client_secret criptografado no banco
 * PHP 7.3+
 */
class TenantService
{
    /** @var TenantRepository */
    private $tenantRepository;

    /** @var PowerBIService */
    private $powerBIService;

    /** @var TokenService */
    private $tokenService;

    public function __construct()
    {
        $this->tenantRepository = new TenantRepository();
        $this->powerBIService = new PowerBIService();
        $this->tokenService = new TokenService();
    }

    public function findAll(bool $ativosOnly = true): array
    {
        $list = $this->tenantRepository->findAll($ativosOnly);
        foreach ($list as &$t) {
            unset($t['client_secret']);
        }
        return $list;
    }

    public function findById(int $id): ?array
    {
        $t = $this->tenantRepository->findById($id);
        if (!$t) {
            return null;
        }
        if (!empty($t['client_secret'])) {
            try {
                $t['client_secret'] = CryptoHelper::decrypt($t['client_secret'], Config::JWT_SECRET);
            } catch (Exception $e) {
                $t['client_secret'] = '';
            }
        }
        return $t;
    }

    /**
     * Para uso interno (TokenService, PowerBI) - retorna tenant com client_secret descriptografado
     */
    public function findByIdForApi(int $id): ?array
    {
        return $this->findById($id);
    }

    public function create(array $data): int
    {
        $secret = trim((string) ($data['client_secret'] ?? ''));
        $data['client_secret'] = CryptoHelper::encrypt($secret, Config::JWT_SECRET);
        return $this->tenantRepository->create($data);
    }

    public function update(int $id, array $data): bool
    {
        if (isset($data['client_secret'])) {
            $secret = trim((string) $data['client_secret']);
            if ($secret !== '') {
                $data['client_secret'] = CryptoHelper::encrypt($secret, Config::JWT_SECRET);
            } else {
                unset($data['client_secret']);
            }
        }
        $ok = $this->tenantRepository->update($id, $data);
        if ($ok) {
            $this->tokenService->invalidate($id);
        }
        return $ok;
    }

    public function testConnection(int $id): bool
    {
        $diag = $this->testConnectionDetailed($id);
        return !empty($diag['connected']);
    }

    /**
     * Teste equivalente ao script de diagnóstico:
     * 1) obter token
     * 2) acessar /groups
     */
    public function testConnectionDetailed(int $id): array
    {
        $this->tokenService->invalidate($id);
        $tenant = $this->findByIdForApi($id);
        if (!$tenant) {
            return [
                'connected' => false,
                'token_ok' => false,
                'groups_ok' => false,
                'message' => 'Tenant não encontrado',
            ];
        }

        try {
            $token = $this->tokenService->getAccessToken($tenant);
            $claims = $this->parseJwtPayload($token);
            $tokenAppId = (string) ($claims['appid'] ?? '');
            $tokenTid = (string) ($claims['tid'] ?? '');

            $workspaces = $this->powerBIService->listWorkspaces($tenant);
            return [
                'connected' => true,
                'token_ok' => true,
                'groups_ok' => true,
                'workspace_count' => count($workspaces),
                'token_appid' => $tokenAppId,
                'token_tid' => $tokenTid,
                'configured_client_id' => (string) ($tenant['client_id'] ?? ''),
                'configured_tenant_id' => (string) ($tenant['tenant_id'] ?? ''),
                'message' => 'Conexão OK (token + /groups)',
            ];
        } catch (Throwable $e) {
            return [
                'connected' => false,
                'token_ok' => false,
                'groups_ok' => false,
                'configured_client_id' => (string) ($tenant['client_id'] ?? ''),
                'configured_tenant_id' => (string) ($tenant['tenant_id'] ?? ''),
                'message' => $e->getMessage(),
            ];
        }
    }

    public function delete(int $id): bool
    {
        $this->tokenService->invalidate($id);
        return $this->tenantRepository->delete($id);
    }

    private function parseJwtPayload(string $jwt): array
    {
        $parts = explode('.', $jwt);
        if (count($parts) < 2) {
            return [];
        }
        $payload = $parts[1];
        $payload .= str_repeat('=', (4 - strlen($payload) % 4) % 4);
        $json = base64_decode(strtr($payload, '-_', '+/'));
        $decoded = json_decode($json ?: '', true);
        return is_array($decoded) ? $decoded : [];
    }
}
