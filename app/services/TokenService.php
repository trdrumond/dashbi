<?php

require_once __DIR__ . '/../config/Config.php';
require_once __DIR__ . '/../helpers/HttpClient.php';
require_once __DIR__ . '/AzureAuthService.php';

/**
 * TokenService - Cache de Access Token Azure AD (OAuth2 Client Credentials)
 * Delega obtenção do token ao AzureAuthService e cacheia em arquivo.
 * PHP 7.3+
 */
class TokenService
{
    /** @var string */
    private $cachePath;

    /** @var AzureAuthService */
    private $azureAuth;

    public function __construct(?AzureAuthService $azureAuth = null)
    {
        $this->cachePath = Config::TOKEN_CACHE_PATH;
        $this->azureAuth = $azureAuth ?? new AzureAuthService();
        if (!is_dir($this->cachePath)) {
            mkdir($this->cachePath, 0755, true);
        }
    }

    /**
     * Retorna access_token válido para o tenant (cache ou nova requisição)
     * @param array $tenant [id, tenant_id, client_id, client_secret]
     * @return string access_token
     * @throws RuntimeException
     */
    public function getAccessToken(array $tenant): string
    {
        $cacheFile = $this->cachePath . 'token_' . $tenant['id'] . '.json';

        if (file_exists($cacheFile)) {
            $cached = json_decode(file_get_contents($cacheFile), true);
            if (!empty($cached['access_token']) && !empty($cached['expires_at']) && $cached['expires_at'] > time()) {
                return $cached['access_token'];
            }
        }

        return $this->requestNewToken($tenant, $cacheFile);
    }

    /**
     * Solicita novo token via AzureAuthService e grava no cache
     */
    private function requestNewToken(array $tenant, string $cacheFile): string
    {
        $result = $this->azureAuth->getAccessToken($tenant);
        $margin = 60;
        $configPath = defined('ROOT_PATH') ? (ROOT_PATH . 'config/azure.php') : (dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'config/azure.php');
        if (file_exists($configPath)) {
            $azure = require $configPath;
            $margin = (int) ($azure['token_cache_margin_seconds'] ?? 60);
        }
        $expiresAt = time() + $result['expires_in'] - $margin;

        file_put_contents($cacheFile, json_encode([
            'access_token' => $result['access_token'],
            'expires_at' => $expiresAt,
        ]));

        return $result['access_token'];
    }

    /**
     * Invalida cache de um tenant (útil após troca de client_secret)
     */
    public function invalidate(int $tenantId): void
    {
        $file = $this->cachePath . 'token_' . $tenantId . '.json';
        if (file_exists($file)) {
            unlink($file);
        }
    }
}
