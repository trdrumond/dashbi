<?php

require_once __DIR__ . '/../config/Config.php';
require_once __DIR__ . '/../helpers/HttpClient.php';

/**
 * AzureAuthService - OAuth2 Client Credentials (Service Principal)
 * Gera Access Token para uso na API Power BI. Não autentica usuário final.
 * PHP 7.3+
 */
class AzureAuthService
{
    /**
     * Obtém access_token do Azure AD (client_credentials)
     * @param array $tenant [tenant_id, client_id, client_secret]
     * @return array ['access_token' => string, 'expires_in' => int]
     * @throws RuntimeException
     */
    public function getAccessToken(array $tenant): array
    {
        $tenantId = trim((string) ($tenant['tenant_id'] ?? ''));
        $clientId = trim((string) ($tenant['client_id'] ?? ''));
        $clientSecret = trim((string) ($tenant['client_secret'] ?? ''));

        if ($clientSecret === '') {
            throw new RuntimeException(
                'Client Secret está vazio. Edite o tenant e informe o Valor do secret (não o Secret ID) em Certificados e segredos do Azure. Consulte docs/checklist-powerbi-workspace.md (seção 7).'
            );
        }

        $url = sprintf(Config::MS_TOKEN_URL, $tenantId);
        $body = [
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'grant_type' => 'client_credentials',
            'scope' => 'https://analysis.windows.net/powerbi/api/.default',
        ];

        $response = HttpClient::post($url, [], $body);

        if (empty($response['access_token'])) {
            $error = $response['error_description'] ?? $response['error'] ?? 'Resposta inválida da Microsoft';
            $hint = '';
            if (stripos($error, 'AADSTS7000215') !== false || stripos($error, 'Invalid client secret') !== false) {
                $hint = ' Use o VALOR do secret (não o ID). No Azure: Registro de aplicativo → Certificados e segredos → ao criar o secret, copie o campo "Valor".';
            }
            throw new RuntimeException('Falha ao obter token Azure AD: ' . $error . $hint);
        }

        return [
            'access_token' => $response['access_token'],
            'expires_in' => (int) ($response['expires_in'] ?? 3600),
        ];
    }
}
