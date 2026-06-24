<?php

/**
 * Checklist de diagnóstico Power BI (passo a passo).
 * Executar: php scripts/testar_conexao_sync.php
 * PHP 7.3+
 */

/**
 * CONFIGURE AQUI (mesma base do scripts/teste_powerbi.php)
 */
$tenantId     = "c29849bb-f3d2-4914-88a0-b95de0b7d4d7";
$clientId     = "9568b0c9-0f86-42ba-98fe-de8418175023";
$clientSecret = "Uxl8Q~oVhidxq_~taU6zf7P39unZqxbUN54JfcDl";

// Opcional (coloque se quiser testar workspace específico)
$workspaceId  = ""; // ex: "xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx"

// Compatibilidade com o restante do checklist
$TENANT_ID = $tenantId;
$CLIENT_ID = $clientId;
$CLIENT_SECRET = $clientSecret;
$WORKSPACE_ID = $workspaceId;

// Exibir token completo no output do script (use false para ocultar)
$SHOW_ACCESS_TOKEN = true;
// Exibir resposta completa do endpoint /groups
$SHOW_GROUPS_RESPONSE = true;

$baseDir = dirname(__DIR__);
require_once $baseDir . '/app/config/Config.php';
require_once $baseDir . '/app/helpers/HttpClient.php';

// Base explícita da API (igual ao estilo do script teste_powerbi.php)
$POWERBI_API_BASE = 'https://api.powerbi.com/v1.0/myorg';

$isWeb = PHP_SAPI !== 'cli';
if ($isWeb) {
    ob_start();
}

function addCheck(array &$checks, string $id, string $descricao, string $status, string $detalhe = '', string $acao = ''): void
{
    $checks[] = [
        'id' => $id,
        'descricao' => $descricao,
        'status' => $status, // OK | FALHA | AVISO
        'detalhe' => $detalhe,
        'acao' => $acao,
    ];
}

function maskSecret(string $secret): string
{
    if ($secret === '') {
        return '(vazio)';
    }
    if (strlen($secret) <= 8) {
        return str_repeat('*', strlen($secret));
    }
    return substr($secret, 0, 4) . str_repeat('*', max(0, strlen($secret) - 8)) . substr($secret, -4);
}

function parseJwtPayload(string $jwt): array
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

function isGuid(string $value): bool
{
    return (bool) preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $value);
}

function parseWorkspaceIds(string $raw): array
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
    foreach ($parts as $part) {
        $id = trim((string) $part);
        if ($id !== '') {
            $ids[$id] = true;
        }
    }
    return array_keys($ids);
}

function httpPostFormRaw(string $url, array $data): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($data),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
        CURLOPT_TIMEOUT => 30,
    ]);
    $body = curl_exec($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr = curl_error($ch);
    curl_close($ch);

    return [
        'http_code' => $httpCode,
        'body' => $body === false ? '' : (string) $body,
        'error' => $curlErr,
        'json' => json_decode($body === false ? '' : (string) $body, true),
    ];
}

function httpGetBearerRaw(string $url, string $token): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ["Authorization: Bearer {$token}"],
        CURLOPT_TIMEOUT => 30,
    ]);
    $body = curl_exec($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr = curl_error($ch);
    curl_close($ch);

    return [
        'http_code' => $httpCode,
        'body' => $body === false ? '' : (string) $body,
        'error' => $curlErr,
        'json' => json_decode($body === false ? '' : (string) $body, true),
    ];
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
    return (string) json_encode($arr, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

function statusLabel(string $status): string
{
    if ($status === 'OK') {
        return '[OK]   ';
    }
    if ($status === 'FALHA') {
        return '[FALHA]';
    }
    return '[AVISO]';
}

function normalizeText(string $text): string
{
    if ($text === '') {
        return '';
    }
    $text = preg_replace('/<br\s*\/?>/i', "\n", $text);
    $text = strip_tags((string) $text);
    $text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');
    $text = preg_replace("/\r\n|\r/", "\n", $text);
    $text = preg_replace("/\n{3,}/", "\n\n", $text);
    return trim((string) $text);
}

function printWrapped(string $label, string $text, int $baseIndent = 9, int $width = 100): void
{
    $text = normalizeText($text);
    if ($text === '') {
        return;
    }
    $indent = str_repeat(' ', $baseIndent);
    $labelPrefix = $indent . $label . ': ';
    $wrapped = wordwrap($text, max(40, $width - strlen($labelPrefix)), "\n");
    $lines = explode("\n", $wrapped);
    foreach ($lines as $i => $line) {
        if ($i === 0) {
            echo $labelPrefix . $line . "\n";
        } else {
            echo str_repeat(' ', strlen($labelPrefix)) . $line . "\n";
        }
    }
}

function printCheckBlock(array $c): void
{
    $det = normalizeText((string) ($c['detalhe'] ?? ''));
    $acao = normalizeText((string) ($c['acao'] ?? ''));
    $line = statusLabel($c['status']) . " {$c['id']}. {$c['descricao']}";
    if ($det !== '') {
        $line .= " | Detalhe: {$det}";
    }
    if ($acao !== '') {
        $line .= " | Acao: {$acao}";
    }
    echo $line . "\n";
}

$tenant = [
    'id' => 0, // evita conflito com cache de tenants reais
    'tenant_id' => trim($TENANT_ID),
    'client_id' => trim($CLIENT_ID),
    'client_secret' => trim($CLIENT_SECRET),
];

$workspaceRaw = trim($WORKSPACE_ID);
$workspaceIds = parseWorkspaceIds($workspaceRaw);
$checks = [];
$token = null;
$workspaces = [];
$workspaceNameById = [];
$targetWorkspaceIds = [];

hr('DashBI 2.0 - Checklist de diagnostico Power BI');
echo "Tenant ID    : {$tenant['tenant_id']}\n";
echo "Client ID    : {$tenant['client_id']}\n";
echo "Client Secret: " . maskSecret($tenant['client_secret']) . "\n";
echo "Workspace ID : " . (!empty($workspaceIds) ? implode(', ', $workspaceIds) : '(nao informado)') . "\n";
echo "\n";

// 1) Validação básica de preenchimento
$missing = [];
foreach (['tenant_id', 'client_id', 'client_secret'] as $field) {
    if (trim((string) $tenant[$field]) === '') {
        $missing[] = $field;
    }
}
if ($missing) {
    addCheck(
        $checks,
        '1',
        'Credenciais básicas preenchidas',
        'FALHA',
        'Campos ausentes: ' . implode(', ', $missing),
        'Preencher TENANT_ID, CLIENT_ID e CLIENT_SECRET com os valores corretos do Azure.'
    );
} else {
    addCheck($checks, '1', 'Credenciais básicas preenchidas', 'OK');
}

// 2) Obter token OAuth2 (fluxo espelhado do teste_powerbi.php)
if (!$missing) {
    try {
        $tokenUrl = sprintf(Config::MS_TOKEN_URL, $tenant['tenant_id']);
        $tokenResp = httpPostFormRaw($tokenUrl, [
            'grant_type' => 'client_credentials',
            'client_id' => $tenant['client_id'],
            'client_secret' => $tenant['client_secret'],
            'scope' => 'https://analysis.windows.net/powerbi/api/.default',
        ]);

        if ($tokenResp['http_code'] !== 200) {
            $err = '';
            if (!empty($tokenResp['error'])) {
                $err = 'cURL: ' . $tokenResp['error'];
            } elseif (is_array($tokenResp['json'])) {
                $err = (string) ($tokenResp['json']['error_description'] ?? $tokenResp['json']['error'] ?? $tokenResp['body']);
            } else {
                $err = $tokenResp['body'];
            }
            throw new RuntimeException('HTTP ' . $tokenResp['http_code'] . ' ao gerar token. ' . trim($err));
        }

        $tokenData = is_array($tokenResp['json']) ? $tokenResp['json'] : [];
        $token = (string) ($tokenData['access_token'] ?? '');
        if ($token === '') {
            throw new RuntimeException('Token não retornado pela Microsoft.');
        }

        addCheck(
            $checks,
            '2',
            'Obter token OAuth2',
            'OK',
            'Token obtido com ' . strlen($token) . ' caracteres. HTTP ' . $tokenResp['http_code']
        );
        if ($SHOW_ACCESS_TOKEN) {
            hr('Access Token obtido');
            echo $token . "\n\n";
        }
    } catch (Throwable $e) {
        addCheck(
            $checks,
            '2',
            'Obter token OAuth2',
            'FALHA',
            $e->getMessage(),
            'Validar Tenant ID, Client ID e principalmente o VALOR do Client Secret (não o Secret ID).'
        );
    }
}

// 3) Inspecionar payload do token (aud/tid/appid)
if ($token) {
    $payload = parseJwtPayload($token);
    $aud = (string) ($payload['aud'] ?? '');
    $tid = (string) ($payload['tid'] ?? '');
    $appid = (string) ($payload['appid'] ?? '');

    $audOk = stripos($aud, 'analysis.windows.net/powerbi/api') !== false;
    $tidOk = ($tid !== '' && strcasecmp($tid, $tenant['tenant_id']) === 0);
    $appOk = ($appid !== '' && strcasecmp($appid, $tenant['client_id']) === 0);

    $det = "aud={$aud} | tid(token)={$tid} vs tid(config)={$tenant['tenant_id']} | appid(token)={$appid} vs client_id(config)={$tenant['client_id']}";
    if ($audOk && $tidOk && $appOk) {
        addCheck($checks, '3', 'Token com claims esperadas (aud/tid/appid)', 'OK', $det);
    } else {
        addCheck(
            $checks,
            '3',
            'Token com claims esperadas (aud/tid/appid)',
            'AVISO',
            $det,
            'Se os valores não baterem com o app/tenant desejado, revisar credenciais e registro no Azure.'
        );
    }
}

// 3.1) Validação do(s) WORKSPACE_ID(s) informado(s)
if (!empty($workspaceIds)) {
    $invalid = [];
    $conflicts = [];
    foreach ($workspaceIds as $wid) {
        if (!isGuid($wid)) {
            $invalid[] = $wid;
        } elseif (strcasecmp($wid, $tenant['client_id']) === 0 || strcasecmp($wid, $tenant['tenant_id']) === 0) {
            $conflicts[] = $wid;
        }
    }

    if (!empty($invalid)) {
        addCheck(
            $checks,
            '3.1',
            'Formato do WORKSPACE_ID',
            'FALHA',
            'IDs inválidos: ' . implode(', ', $invalid),
            'Informar apenas GUIDs válidos do workspace no Power BI (groupId).'
        );
    } elseif (!empty($conflicts)) {
        addCheck(
            $checks,
            '3.1',
            'Formato do WORKSPACE_ID',
            'FALHA',
            'WORKSPACE_ID coincide com Tenant ID ou Client ID: ' . implode(', ', $conflicts),
            'Use o groupId da URL do workspace no app.powerbi.com (não Object ID do Entra/Azure).'
        );
    } else {
        addCheck(
            $checks,
            '3.1',
            'Formato do WORKSPACE_ID',
            'OK',
            'GUID(s) válido(s): ' . implode(', ', $workspaceIds),
            'Confirme que os GUIDs foram copiados da URL do workspace no app.powerbi.com (segmento /groups/{groupId}/...).'
        );
    }
}

// 4) Listar workspaces (/groups) - fluxo cru igual teste_powerbi.php
if ($token) {
    try {
        $groupsResp = httpGetBearerRaw($POWERBI_API_BASE . '/groups', $token);
        if ($groupsResp['http_code'] !== 200) {
            $msg = '';
            if (!empty($groupsResp['error'])) {
                $msg = 'cURL: ' . $groupsResp['error'];
            } elseif (is_array($groupsResp['json'])) {
                $msg = (string) ($groupsResp['json']['error']['message'] ?? $groupsResp['json']['error']['code'] ?? $groupsResp['body']);
            } else {
                $msg = $groupsResp['body'];
            }
            throw new RuntimeException('HTTP ' . $groupsResp['http_code'] . ' em /groups. ' . trim($msg));
        }

        $groupsJson = is_array($groupsResp['json']) ? $groupsResp['json'] : [];
        $workspaces = isset($groupsJson['value']) && is_array($groupsJson['value']) ? $groupsJson['value'] : [];
        $total = count($workspaces);
        $workspaceIdsFound = [];
        foreach ($workspaces as $ws) {
            $id = trim((string) ($ws['id'] ?? ''));
            if ($id === '') {
                continue;
            }
            $workspaceIdsFound[$id] = true;
            $workspaceNameById[$id] = (string) ($ws['name'] ?? 'sem nome');
        }

        // Se WORKSPACE_ID não foi informado manualmente, usa todos os IDs vindos do /groups.
        if (empty($workspaceIds) && !empty($workspaceIdsFound)) {
            $workspaceIds = array_keys($workspaceIdsFound);
        }
        $targetWorkspaceIds = $workspaceIds;

        if ($total > 0) {
            addCheck($checks, '4', 'Listar workspaces no Power BI', 'OK', "HTTP 200 em /groups. Total encontrado: {$total}");
        } else {
            addCheck(
                $checks,
                '4',
                'Listar workspaces no Power BI',
                'AVISO',
                'HTTP 200 em /groups, mas retornou 0 workspaces.',
                'Adicionar o app como Membro/Admin em cada workspace no app.powerbi.com.'
            );
        }

        if ($SHOW_GROUPS_RESPONSE) {
            hr('Resposta completa do endpoint /groups');
            echo prettyJson((string) ($groupsResp['body'] ?? '')) . "\n\n";
        }

        // Destaca os IDs corretos para usar como workspace_id no sistema
        if (!empty($workspaceIdsFound)) {
            hr('workspace_id sugeridos (campo id do /groups)');
            foreach (array_keys($workspaceIdsFound) as $wid) {
                $name = $workspaceNameById[$wid] ?? 'sem nome';
                echo "- {$wid} | name: {$name}\n";
            }
            echo "\n";
        }
    } catch (Throwable $e) {
        addCheck(
            $checks,
            '4',
            'Listar workspaces no Power BI',
            'FALHA',
            $e->getMessage(),
            'No admin.powerbi.com, confirmar "Permitir que service principals usem as APIs"; se restrito por grupo/app, incluir este app.'
        );
    }
}

// 4.1) Teste direto de acesso ao(s) workspace(s) via /groups/{id} (fluxo cru)
if ($token && !empty($targetWorkspaceIds)) {
    $okGroups = [];
    $failedGroups = [];

    foreach ($targetWorkspaceIds as $wid) {
        $url = $POWERBI_API_BASE . '/groups/' . $wid;
        $resp = httpGetBearerRaw($url, $token);
        $code = (int) ($resp['http_code'] ?? 0);
        if ($code >= 200 && $code < 300) {
            $json = is_array($resp['json']) ? $resp['json'] : [];
            $name = (string) ($json['name'] ?? 'sem nome');
            $okGroups[] = $wid . ' (' . $name . ')';
            $workspaceNameById[$wid] = $name;
        } else {
            if (!empty($resp['error'])) {
                $msg = 'cURL: ' . $resp['error'];
            } else {
                $json = is_array($resp['json']) ? $resp['json'] : [];
                $msg = (string) ($json['error']['message'] ?? $json['error']['code'] ?? ('HTTP ' . $code));
            }
            $name = $workspaceNameById[$wid] ?? 'sem nome';
            $failedGroups[] = $wid . ' (' . $name . '): ' . $msg;
        }
    }

    if (empty($failedGroups)) {
        addCheck(
            $checks,
            '4.1',
            'Acesso direto ao(s) workspace(s) via /groups/{id}',
            'OK',
            'Workspaces acessíveis: ' . implode(', ', $okGroups) . ' | Fonte IDs: ' . (empty($workspaceRaw) ? '/groups' : 'WORKSPACE_ID informado')
        );
    } else {
        addCheck(
            $checks,
            '4.1',
            'Acesso direto ao(s) workspace(s) via /groups/{id}',
            'FALHA',
            'Falhas: ' . implode(' | ', $failedGroups),
            'Se este passo falhar com 401/403, o problema está no acesso do Service Principal ao workspace/tenant (antes mesmo de listar relatórios).'
        );
    }
} elseif ($token) {
    addCheck(
        $checks,
        '4.1',
        'Acesso direto ao(s) workspace(s) via /groups/{id}',
        'AVISO',
        'Teste pulado: WORKSPACE_ID não informado.',
        'Informe WORKSPACE_ID para validar acesso direto ao workspace.'
    );
}

// 5) Teste por workspace(s) em /groups/{id}/reports (fluxo cru)
if ($token && !empty($targetWorkspaceIds)) {
    $totalReports = 0;
    $okWorkspaces = [];
    $failedWorkspaces = [];
    $reportsByWorkspace = [];

    foreach ($targetWorkspaceIds as $wid) {
        $url = $POWERBI_API_BASE . '/groups/' . $wid . '/reports';
        $resp = httpGetBearerRaw($url, $token);
        $code = (int) ($resp['http_code'] ?? 0);
        if ($code >= 200 && $code < 300) {
            $json = is_array($resp['json']) ? $resp['json'] : [];
            $reports = isset($json['value']) && is_array($json['value']) ? $json['value'] : [];
            $qtd = count($reports);
            $totalReports += $qtd;
            $name = $workspaceNameById[$wid] ?? 'sem nome';
            $okWorkspaces[] = $wid . ' (' . $name . ") => {$qtd} relatório(s)";

            $reportsByWorkspace[$wid] = [
                'workspace_name' => $name,
                'reports' => [],
            ];
            foreach ($reports as $rep) {
                $reportsByWorkspace[$wid]['reports'][] = [
                    'id' => (string) ($rep['id'] ?? ''),
                    'name' => (string) ($rep['name'] ?? 'sem nome'),
                ];
            }
        } else {
            if (!empty($resp['error'])) {
                $msg = 'cURL: ' . $resp['error'];
            } else {
                $json = is_array($resp['json']) ? $resp['json'] : [];
                $msg = (string) ($json['error']['message'] ?? $json['error']['code'] ?? ('HTTP ' . $code));
            }
            $name = $workspaceNameById[$wid] ?? 'sem nome';
            $failedWorkspaces[] = $wid . ' (' . $name . '): HTTP ' . $code . ' - ' . $msg;
        }
    }

    if (empty($failedWorkspaces)) {
        addCheck(
            $checks,
            '5',
            'Listar relatórios por workspace (usando IDs obtidos)',
            'OK',
            'Workspaces testados: ' . count($targetWorkspaceIds) . ' | Relatórios totais: ' . $totalReports . ' | Detalhe: ' . implode(', ', $okWorkspaces)
        );
    } else {
        $acao = 'Confirmar se cada workspace_id está correto e se o app é membro de cada workspace. Verifique o passo 4.1 para saber se a falha ocorre já no /groups/{id}.';
        addCheck(
            $checks,
            '5',
            'Listar relatórios por workspace (usando IDs obtidos)',
            'FALHA',
            'Falhas: ' . implode(' | ', $failedWorkspaces),
            $acao
        );
    }

    // Lista detalhada de relatórios para conferência final (nome + identificador)
    if (!empty($reportsByWorkspace)) {
        hr('Lista de relatórios por workspace (nome + reportId)');
        foreach ($reportsByWorkspace as $wid => $data) {
            $wsName = (string) ($data['workspace_name'] ?? 'sem nome');
            echo "Workspace: {$wsName} | workspace_id: {$wid}\n";
            $list = $data['reports'] ?? [];
            if (empty($list)) {
                echo "  - (nenhum relatório)\n";
            } else {
                foreach ($list as $item) {
                    $rid = trim((string) ($item['id'] ?? ''));
                    $rname = trim((string) ($item['name'] ?? 'sem nome'));
                    echo "  - {$rname} | report_id: {$rid}\n";
                }
            }
            echo "\n";
        }
    }
} elseif ($token) {
    addCheck(
        $checks,
        '5',
        'Listar relatórios do(s) workspace_id(s) informado(s)',
        'AVISO',
        'Teste pulado: WORKSPACE_ID não informado.',
        'Preencher $WORKSPACE_ID no script para validar acesso direto ao(s) workspace(s) esperado(s).'
    );
}

hr('Resultado do checklist');
foreach ($checks as $c) {
    printCheckBlock($c);
}
echo "\n";

$ok = 0;
$falha = 0;
$aviso = 0;
foreach ($checks as $c) {
    if ($c['status'] === 'OK') {
        $ok++;
    } elseif ($c['status'] === 'FALHA') {
        $falha++;
    } else {
        $aviso++;
    }
}

$total = count($checks);
hr('Resumo final');
echo "Total de checagens: {$total}\n";
echo "OK: {$ok} | FALHA: {$falha} | AVISO: {$aviso}\n";
if ($falha > 0) {
    echo "Status final: INVESTIGAR FALHAS\n";
} elseif ($aviso > 0) {
    echo "Status final: FUNCIONA COM ALERTAS\n";
} else {
    echo "Status final: TUDO OK\n";
}
echo "\n";

// Resumo executivo dos pontos críticos para troubleshooting rápido
$map = [];
foreach ($checks as $c) {
    $map[$c['id']] = $c;
}
hr('Resumo executivo');
foreach (['2', '3', '4', '4.1', '5'] as $idCritico) {
    if (!isset($map[$idCritico])) {
        continue;
    }
    $c = $map[$idCritico];
    echo statusLabel($c['status']) . " {$c['id']}. {$c['descricao']}\n";
    $det = normalizeText((string) ($c['detalhe'] ?? ''));
    if ($det !== '') {
        echo "  -> {$det}\n";
    }
}
echo "\n";

$acoesRecomendadas = [];
foreach ($checks as $c) {
    if (($c['status'] === 'FALHA' || $c['status'] === 'AVISO') && trim((string) $c['acao']) !== '') {
        $acoesRecomendadas[normalizeText($c['acao'])] = true;
    }
}
if (!empty($acoesRecomendadas)) {
    hr('Proximas acoes recomendadas');
    $i = 1;
    foreach (array_keys($acoesRecomendadas) as $acao) {
        printWrapped((string) $i, $acao, 2);
        $i++;
    }
    echo "\n";
}

if ($isWeb) {
    $text = ob_get_clean();
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><title>Teste de conexao Power BI</title>';
    echo '<style>body{font-family:Segoe UI,Tahoma,sans-serif;background:#f3f4f6;margin:0;padding:24px;}';
    echo '.card{max-width:1200px;margin:0 auto;background:#fff;border:1px solid #e5e7eb;border-radius:10px;padding:16px 20px;box-shadow:0 1px 3px rgba(0,0,0,.08);}';
    echo 'pre{white-space:pre-wrap;word-break:break-word;line-height:1.45;margin:0;font-family:Consolas,Monaco,monospace;font-size:13px;color:#111827;}';
    echo '</style></head><body><div class="card"><pre>';
    echo htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    echo '</pre></div></body></html>';
}
