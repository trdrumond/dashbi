<?php

/**
 * CONFIGURE AQUI
 */
$tenantId     = "c29849bb-f3d2-4914-88a0-b95de0b7d4d7";
$clientId     = "9568b0c9-0f86-42ba-98fe-de8418175023";
$clientSecret = "Uxl8Q~oVhidxq_~taU6zf7P39unZqxbUN54JfcDl";

// Opcional (coloque se quiser testar workspace específico)
$workspaceId  = ""; // ex: "xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx"

$isWeb = PHP_SAPI !== 'cli';
if ($isWeb) {
    ob_start();
}

function hr(string $title = ''): void
{
    echo str_repeat('=', 78) . "\n";
    if ($title !== '') {
        echo $title . "\n";
        echo str_repeat('-', 78) . "\n";
    }
}

function prettyJson(string $raw): string
{
    $arr = json_decode($raw, true);
    if (!is_array($arr)) {
        return trim($raw);
    }
    return json_encode($arr, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

function showStep(string $id, string $title): void
{
    echo "\n[{$id}] {$title}\n";
    echo str_repeat('-', 78) . "\n";
}

function showResult(bool $ok, string $message, int $httpCode, string $body = ''): void
{
    echo ($ok ? 'OK    : ' : 'FALHA : ') . $message . "\n";
    echo "HTTP  : {$httpCode}\n";
    if (trim($body) !== '') {
        echo "BODY  :\n" . prettyJson($body) . "\n";
    }
}

hr('INICIANDO TESTE POWER BI');
echo "Tenant ID    : {$tenantId}\n";
echo "Client ID    : {$clientId}\n";
echo "Workspace ID : " . ($workspaceId !== '' ? $workspaceId : '(não informado)') . "\n";

/**
 * 1. GERAR ACCESS TOKEN
 */
showStep('1', 'Gerar Access Token');

$tokenUrl = "https://login.microsoftonline.com/$tenantId/oauth2/v2.0/token";

$postData = http_build_query([
    "grant_type"    => "client_credentials",
    "client_id"     => $clientId,
    "client_secret" => $clientSecret,
    "scope"         => "https://analysis.windows.net/powerbi/api/.default"
]);

$ch = curl_init($tokenUrl);
curl_setopt_array($ch, [
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $postData,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER     => [
        "Content-Type: application/x-www-form-urlencoded"
    ]
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($httpCode !== 200) {
    $msg = $curlError !== '' ? "Erro cURL: {$curlError}" : 'Erro ao gerar token.';
    showResult(false, $msg, $httpCode, (string) $response);
    if ($isWeb) {
        $output = ob_get_clean();
        header('Content-Type: text/html; charset=utf-8');
        echo '<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><title>Teste Power BI</title>';
        echo '<style>body{font-family:Segoe UI,Tahoma,sans-serif;background:#f3f4f6;margin:0;padding:24px}';
        echo '.card{max-width:1180px;margin:0 auto;background:#fff;border:1px solid #e5e7eb;border-radius:10px;padding:18px 20px;box-shadow:0 1px 3px rgba(0,0,0,.08)}';
        echo 'pre{margin:0;white-space:pre-wrap;word-break:break-word;font-family:Consolas,Monaco,monospace;font-size:13px;line-height:1.45;color:#111827}</style></head><body><div class="card"><pre>';
        echo htmlspecialchars($output, ENT_QUOTES, 'UTF-8');
        echo '</pre></div></body></html>';
    }
    exit;
}

$data = json_decode($response, true);

if (!isset($data["access_token"])) {
    showResult(false, 'Token não retornado pela Microsoft.', $httpCode, (string) $response);
    if ($isWeb) {
        $output = ob_get_clean();
        header('Content-Type: text/html; charset=utf-8');
        echo '<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><title>Teste Power BI</title>';
        echo '<style>body{font-family:Segoe UI,Tahoma,sans-serif;background:#f3f4f6;margin:0;padding:24px}';
        echo '.card{max-width:1180px;margin:0 auto;background:#fff;border:1px solid #e5e7eb;border-radius:10px;padding:18px 20px;box-shadow:0 1px 3px rgba(0,0,0,.08)}';
        echo 'pre{margin:0;white-space:pre-wrap;word-break:break-word;font-family:Consolas,Monaco,monospace;font-size:13px;line-height:1.45;color:#111827}</style></head><body><div class="card"><pre>';
        echo htmlspecialchars($output, ENT_QUOTES, 'UTF-8');
        echo '</pre></div></body></html>';
    }
    exit;
}

$accessToken = $data["access_token"];
showResult(true, 'Access Token gerado com sucesso.', $httpCode);


/**
 * 2. TESTAR LISTAGEM DE WORKSPACES
 */
showStep('2', 'Testar GET /groups');

$ch = curl_init("https://api.powerbi.com/v1.0/myorg/groups");
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER     => [
        "Authorization: Bearer $accessToken"
    ]
]);

$groupsResponse = curl_exec($ch);
$groupsHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$groupsCurlError = curl_error($ch);
curl_close($ch);

if ($groupsHttpCode === 200) {
    showResult(true, 'Permissão OK. Endpoint /groups acessível.', $groupsHttpCode, (string) $groupsResponse);
} else {
    $msg = $groupsCurlError !== '' ? "Erro cURL: {$groupsCurlError}" : 'Erro ao acessar /groups.';
    showResult(false, $msg, $groupsHttpCode, (string) $groupsResponse);
}


/**
 * 3. TESTAR WORKSPACE ESPECÍFICO (opcional)
 */
if (!empty($workspaceId)) {
    showStep('3', 'Testar workspace específico');

    $url = "https://api.powerbi.com/v1.0/myorg/groups/$workspaceId";

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => [
            "Authorization: Bearer $accessToken"
        ]
    ]);

    $workspaceResponse = curl_exec($ch);
    $workspaceHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $workspaceCurlError = curl_error($ch);
    curl_close($ch);

    if ($workspaceHttpCode === 200) {
        showResult(true, 'Workspace acessível.', $workspaceHttpCode, (string) $workspaceResponse);
    } else {
        $msg = $workspaceCurlError !== '' ? "Erro cURL: {$workspaceCurlError}" : 'Erro ao acessar workspace.';
        showResult(false, $msg, $workspaceHttpCode, (string) $workspaceResponse);
    }
} else {
    showStep('3', 'Testar workspace específico');
    echo "AVISO : teste pulado (workspaceId não informado)\n";
}

hr('FIM DO TESTE');

if ($isWeb) {
    $output = ob_get_clean();
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><title>Teste Power BI</title>';
    echo '<style>body{font-family:Segoe UI,Tahoma,sans-serif;background:#f3f4f6;margin:0;padding:24px}';
    echo '.card{max-width:1180px;margin:0 auto;background:#fff;border:1px solid #e5e7eb;border-radius:10px;padding:18px 20px;box-shadow:0 1px 3px rgba(0,0,0,.08)}';
    echo 'pre{margin:0;white-space:pre-wrap;word-break:break-word;font-family:Consolas,Monaco,monospace;font-size:13px;line-height:1.45;color:#111827}</style></head><body><div class="card"><pre>';
    echo htmlspecialchars($output, ENT_QUOTES, 'UTF-8');
    echo '</pre></div></body></html>';
}
