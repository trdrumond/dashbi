<?php

/**
 * Front Controller - DashBI 2.0
 * Todas as requisições passam por aqui (rewrite para index.php)
 * PHP 7.3+
 */

require_once __DIR__ . '/../app/bootstrap.php';

// Mostrar erros não capturados em JSON (para diagnóstico)
set_exception_handler(function (Throwable $e) {
    if (class_exists('Response')) {
        (new Response())->json([
            'success' => false,
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
        ], 500)->send();
    } else {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine()]);
    }
});

// Carregar controllers
require_once __DIR__ . '/../app/modules/auth/AuthController.php';
require_once __DIR__ . '/../app/modules/tenants/TenantController.php';
require_once __DIR__ . '/../app/modules/workspaces/WorkspaceController.php';
require_once __DIR__ . '/../app/modules/reports/ReportController.php';
require_once __DIR__ . '/../app/modules/permissions/PermissionController.php';
require_once __DIR__ . '/../app/modules/users/UserController.php';
require_once __DIR__ . '/../app/modules/groups/GroupController.php';
require_once __DIR__ . '/../app/modules/folders/FolderController.php';
require_once __DIR__ . '/../app/modules/sync/SyncController.php';
require_once __DIR__ . '/../app/modules/logs/LogController.php';
require_once __DIR__ . '/../app/modules/datasets/DatasetController.php';
require_once __DIR__ . '/../app/modules/dashboard/DashboardController.php';
require_once __DIR__ . '/../app/modules/tv/TvPanelController.php';

$request = new Request();
$router = new Router();

// ---- Auth (público)
$router->post('/api/auth/login', [AuthController::class, 'login']);
$router->post('/api/auth/esqueci-senha', [AuthController::class, 'esqueciSenha']);
$router->get('/api/auth/me', [AuthController::class, 'me']);
$router->put('/api/auth/profile', [AuthController::class, 'updateProfile']);
$router->post('/api/auth/logout', [AuthController::class, 'logout']);

// ---- Tenants (admin)
$router->get('/api/tenants', [TenantController::class, 'index']);
$router->get('/api/tenants/{id}', [TenantController::class, 'show']);
$router->post('/api/tenants', [TenantController::class, 'store']);
$router->put('/api/tenants/{id}', [TenantController::class, 'update']);
$router->delete('/api/tenants/{id}', [TenantController::class, 'delete']);
$router->post('/api/tenants/{id}/test', [TenantController::class, 'testConnection']);

// ---- Workspaces
$router->get('/api/tenants/{tenantId}/workspaces', [WorkspaceController::class, 'index']);
$router->get('/api/workspaces/{id}/reports', [WorkspaceController::class, 'reports']);
$router->get('/api/workspaces/{id}/reports/all', [WorkspaceController::class, 'reportsAll']);

// ---- Dashboard (autenticado - KPIs)
$router->get('/api/dashboard/kpis', [DashboardController::class, 'kpis']);

// ---- Reports (autenticado)
$router->get('/api/reports', [ReportController::class, 'index']);
$router->get('/api/favoritos', [ReportController::class, 'favoritos']);
$router->get('/api/reports/catalog', [ReportController::class, 'catalog']);
$router->get('/api/reports/{id}/embed', [ReportController::class, 'embed']);
$router->get('/api/reports/{id}/pages', [ReportController::class, 'pages']);
$router->post('/api/reports/{id}/favorito', [ReportController::class, 'toggleFavorito']);
$router->post('/api/reports/{id}/fixar', [ReportController::class, 'toggleFixar']);
$router->put('/api/reports/log-acesso/{id}', [ReportController::class, 'closeLogAcesso']);
$router->post('/api/reports/{id}/imagem', [ReportController::class, 'uploadImage']);
$router->get('/api/reports/{id}', [ReportController::class, 'show']);
$router->put('/api/reports/{id}', [ReportController::class, 'update']);

// ---- Permissions (admin)
$router->get('/api/reports/{reportId}/permissions', [PermissionController::class, 'index']);
$router->post('/api/reports/{reportId}/permissions', [PermissionController::class, 'store']);
$router->put('/api/permissions/{id}', [PermissionController::class, 'update']);
$router->delete('/api/permissions/{id}', [PermissionController::class, 'delete']);

// ---- Users (admin)
$router->get('/api/users', [UserController::class, 'index']);
$router->get('/api/users/{id}', [UserController::class, 'show']);
$router->post('/api/users', [UserController::class, 'store']);
$router->put('/api/users/{id}', [UserController::class, 'update']);
$router->delete('/api/users/{id}', [UserController::class, 'delete']);
$router->post('/api/users/{id}/groups', [UserController::class, 'setGroups']);
$router->post('/api/users/{id}/folders', [UserController::class, 'setFolders']);

// ---- Folders (admin)
$router->get('/api/folders', [FolderController::class, 'index']);
$router->get('/api/folders/for-dashboard', [FolderController::class, 'forDashboard']);
$router->get('/api/folders/{id}', [FolderController::class, 'show']);
$router->post('/api/folders', [FolderController::class, 'store']);
$router->put('/api/folders/{id}', [FolderController::class, 'update']);
$router->delete('/api/folders/{id}', [FolderController::class, 'delete']);
$router->get('/api/folders/{id}/reports', [FolderController::class, 'reports']);
$router->put('/api/folders/{id}/reports', [FolderController::class, 'setReports']);

// ---- Groups (admin)
$router->get('/api/groups', [GroupController::class, 'index']);
$router->get('/api/groups/{id}', [GroupController::class, 'show']);
$router->get('/api/groups/{id}/users', [GroupController::class, 'users']);
$router->post('/api/groups', [GroupController::class, 'store']);
$router->put('/api/groups/{id}', [GroupController::class, 'update']);
$router->delete('/api/groups/{id}', [GroupController::class, 'delete']);
$router->post('/api/groups/{id}/folders', [GroupController::class, 'setFolders']);

// ---- Sync (admin)
$router->post('/api/sync/tenant/{tenantId}', [SyncController::class, 'tenant']);
$router->post('/api/sync/all', [SyncController::class, 'all']);

// ---- Logs (admin)
$router->get('/api/logs', [LogController::class, 'index']);
$router->get('/api/logs/metrics', [LogController::class, 'metrics']);

// ---- Datasets / Refresh (admin - controle de refresh Power BI)
$router->get('/api/datasets', [DatasetController::class, 'index']);
$router->get('/api/datasets/refreshes', [DatasetController::class, 'refreshes']);
$router->post('/api/datasets/refresh', [DatasetController::class, 'refresh']);

// ---- TV Panels (admin para cadastro, autenticado para play)
$router->get('/api/tv-panels', [TvPanelController::class, 'index']);
$router->get('/api/tv-panels/{id}', [TvPanelController::class, 'show']);
$router->post('/api/tv-panels', [TvPanelController::class, 'store']);
$router->put('/api/tv-panels/{id}', [TvPanelController::class, 'update']);
$router->delete('/api/tv-panels/{id}', [TvPanelController::class, 'delete']);
$router->put('/api/tv-panels/{id}/items', [TvPanelController::class, 'saveItems']);
$router->get('/api/tv-panels/{id}/play', [TvPanelController::class, 'playConfig']);

// Despachar
$method = $request->getMethod();
$uri = $request->getUri();

$match = $router->match($method, $uri);

if ($match === null) {
    // Raiz do sistema: redirecionar para a página de login
    if (($uri === '/' || $uri === '') && $method === 'GET') {
        header('Location: login.html', true, 302);
        exit;
    }
    (new Response())->json(['success' => false, 'message' => 'Rota não encontrada'], 404)->send();
    exit;
}

list($handler, $params) = $match;
list($class, $methodName) = $handler;

try {
    $controller = new $class();
    $controller->setRequest($request);
    $controller->setParams($params);
    $response = $controller->$methodName();
    if ($response instanceof Response) {
        $response->send();
    }
} catch (Throwable $e) {
    $code = $e instanceof RuntimeException ? 400 : 500;
    if (function_exists('error_log')) {
        error_log('DashBI 500: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    }
    if (class_exists('Response')) {
        (new Response())->json(['success' => false, 'message' => $e->getMessage()], $code)->send();
    } else {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code($code);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}
