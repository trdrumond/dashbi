<?php

require_once __DIR__ . '/../../core/Controller.php';
require_once __DIR__ . '/../../services/AuthService.php';
require_once __DIR__ . '/../../services/PermissionService.php';
require_once __DIR__ . '/../../services/PowerBIService.php';
require_once __DIR__ . '/../../services/ReportService.php';
require_once __DIR__ . '/../../services/LogService.php';
require_once __DIR__ . '/../../services/TenantService.php';
require_once __DIR__ . '/../../repositories/FavoritoRepository.php';
require_once __DIR__ . '/../../config/Config.php';

/**
 * ReportController - visualização e embed token
 * Regra: sempre validar permissão interna antes de gerar token
 */
class ReportController extends Controller
{
    /** @var AuthService */
    private $authService;

    /** @var PermissionService */
    private $permissionService;

    /** @var ReportService */
    private $reportService;

    /** @var PowerBIService */
    private $powerBIService;

    /** @var LogService */
    private $logService;

    /** @var TenantService */
    private $tenantService;

    /** @var FavoritoRepository */
    private $favoritoRepository;

    public function __construct()
    {
        $this->authService = new AuthService();
        $this->permissionService = new PermissionService();
        $this->reportService = new ReportService();
        $this->powerBIService = new PowerBIService();
        $this->logService = new LogService();
        $this->tenantService = new TenantService();
        $this->favoritoRepository = new FavoritoRepository();
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
        if ($r = $this->requireAuth()) {
            return $r;
        }
        if (!$this->authService->hasProfile(Config::PROFILE_ADMIN)) {
            return $this->error('Acesso negado', 403);
        }
        return null;
    }

    /**
     * GET /api/reports - relatórios que o usuário pode ver (apenas por permissão, sem hierarquia).
     */
    public function index(): Response
    {
        if ($r = $this->requireAuth()) {
            return $r;
        }
        $userId = $this->authService->getCurrentUserId();
        $workspaceId = $this->request->get('workspace_id') ? (int) $this->request->get('workspace_id') : null;
        $list = $this->reportService->findAllowedByUser($userId, $workspaceId);
        return $this->success($list);
    }

    /**
     * GET /api/favoritos - lista favoritos e fixados do usuário logado.
     */
    public function favoritos(): Response
    {
        if ($r = $this->requireAuth()) {
            return $r;
        }
        $userId = $this->authService->getCurrentUserId();
        $data = $this->favoritoRepository->getByUser($userId);
        return $this->success($data);
    }

    /**
     * POST /api/reports/{id}/favorito - toggle favorito (body: { favorito: true|false }).
     */
    public function toggleFavorito(): Response
    {
        if ($r = $this->requireAuth()) {
            return $r;
        }
        $reportId = (int) $this->param('id');
        $userId = $this->authService->getCurrentUserId();
        if (!$this->permissionService->canView($userId, $reportId)) {
            return $this->error('Acesso negado a este relatório', 403);
        }
        $body = $this->request->getBody();
        $add = isset($body['favorito']) ? (bool) $body['favorito'] : true;
        $this->favoritoRepository->setFavorito($userId, $reportId, $add);
        $data = $this->favoritoRepository->getByUser($userId);
        return $this->success($data);
    }

    /**
     * POST /api/reports/{id}/fixar - fixar ou desfixar na dashboard (body: { fixado: true|false, ordem?: number }).
     */
    public function toggleFixar(): Response
    {
        if ($r = $this->requireAuth()) {
            return $r;
        }
        $reportId = (int) $this->param('id');
        $userId = $this->authService->getCurrentUserId();
        if (!$this->permissionService->canView($userId, $reportId)) {
            return $this->error('Acesso negado a este relatório', 403);
        }
        $body = $this->request->getBody();
        $fixado = isset($body['fixado']) ? (bool) $body['fixado'] : true;
        $ordem = isset($body['ordem']) ? (int) $body['ordem'] : $this->favoritoRepository->getProximaOrdemFixado($userId);
        $this->favoritoRepository->setFixado($userId, $reportId, $fixado, $ordem);
        $data = $this->favoritoRepository->getByUser($userId);
        return $this->success($data);
    }

    /**
     * GET /api/reports/catalog - catálogo completo de relatórios ativos (admin/master).
     */
    public function catalog(): Response
    {
        if ($r = $this->requireAdmin()) {
            return $r;
        }
        return $this->success($this->reportService->findAllActive(null));
    }

    /**
     * GET /api/reports/{id}/embed - gera config para embed (embedUrl + token)
     * Permissão sempre validada internamente.
     */
    public function embed(): Response
    {
        if ($r = $this->requireAuth()) {
            return $r;
        }

        $reportId = (int) $this->param('id');
        $userId = $this->authService->getCurrentUserId();

        if (!$this->permissionService->canView($userId, $reportId)) {
            $this->logService->log($userId, $reportId, 'deny', $this->request->getClientIp());
            return $this->error('Acesso negado a este relatório', 403);
        }

        $report = $this->reportService->findById($reportId);
        if (!$report || empty($report['powerbi_workspace_id']) || empty($report['powerbi_report_id'])) {
            return $this->error('Relatório não encontrado ou não configurado', 404);
        }

        $tenant = $this->tenantService->findByIdForApi($report['tenant_id']);
        if (!$tenant) {
            return $this->error('Conexão com o workspace não disponível', 503);
        }

        // Effective identity (RLS) só é enviada quando o relatório tem rls_role configurado no banco.
        // Datasets sem RLS no Power BI rejeitam o token com "shouldn't have effective identity".
        $user = $this->authService->getCurrentUser();
        $datasetId = !empty($report['dataset_id']) ? trim($report['dataset_id']) : null;
        $rlsRole = !empty($report['rls_role']) ? trim($report['rls_role']) : null;
        $userPrincipal = null;
        if ($datasetId && $rlsRole) {
            if (!empty($user['empresa_id'])) {
                $userPrincipal = (string) $user['empresa_id'];
            } elseif (!empty($user['email'])) {
                $userPrincipal = (string) $user['email'];
            }
        }

        $permission = $this->permissionService->getEffectivePermission($userId, $reportId);
        $mostrarAbas = $permission && !empty($permission['mostrar_abas']);
        $mostrarFiltros = $permission && !empty($permission['mostrar_filtros']);

        try {
            $embed = $this->powerBIService->getEmbedToken(
                $tenant,
                $report['powerbi_workspace_id'],
                $report['powerbi_report_id'],
                $datasetId,
                $userPrincipal,
                $userPrincipal !== null ? $rlsRole : null,
                false
            );
        } catch (RuntimeException $e) {
            return $this->error($e->getMessage(), 503);
        }

        if (!$embed) {
            return $this->error('Não foi possível gerar o token de visualização', 503);
        }

        $this->logService->log($userId, $reportId, 'view', $this->request->getClientIp());

        $logAcessoId = $this->logService->startLogAcessoRelatorio(
            $userId,
            $reportId,
            $this->request->getClientIp()
        );

        // A API GenerateToken não retorna embedUrl; usar do banco ou montar a URL padrão
        $embedUrl = !empty($report['embed_url']) ? trim($report['embed_url']) : null;
        if (empty($embedUrl)) {
            $embedUrl = 'https://app.powerbi.com/reportEmbed?reportId=' . rawurlencode($report['powerbi_report_id']) . '&groupId=' . rawurlencode($report['powerbi_workspace_id']);
        }

        $config = [
            'embedUrl' => $embedUrl,
            'accessToken' => $embed['accessToken'],
            'reportId' => $report['powerbi_report_id'],
            'reportName' => $report['nome'],
            'paginaRestrita' => $permission['pagina_restrita'] ?? [],
            'filtroFixo' => $permission['filtro_fixo'] ?? [],
            'mostrarAbas' => $mostrarAbas,
            'mostrarFiltros' => $mostrarFiltros,
            'logAcessoId' => $logAcessoId,
        ];

        return $this->success($config);
    }

    /**
     * PUT /api/reports/log-acesso/{id} - fecha o log de acesso com tempo de visualização (e opcionalmente filtros).
     */
    public function closeLogAcesso(): Response
    {
        if ($r = $this->requireAuth()) {
            return $r;
        }
        $logId = (int) $this->param('id');
        $userId = $this->authService->getCurrentUserId();
        $log = $this->logService->findLogAcessoRelatorioById($logId);
        if (!$log || (int) $log['usuario_id'] !== $userId) {
            return $this->error('Registro de acesso não encontrado ou não pertence ao usuário', 404);
        }
        $body = $this->request->getBody();
        $tempo = isset($body['tempo_visualizacao']) ? (int) $body['tempo_visualizacao'] : null;
        $filtros = $body['filtros_utilizados'] ?? null;
        $this->logService->endLogAcessoRelatorio($logId, $userId, $tempo, $filtros);
        return $this->success(['ok' => true]);
    }

    /**
     * GET /api/reports/{id} - um relatório (admin, para edição).
     */
    public function show(): Response
    {
        if ($r = $this->requireAdmin()) {
            return $r;
        }
        $id = (int) $this->param('id');
        $report = $this->reportService->findById($id);
        if (!$report) {
            return $this->error('Relatório não encontrado', 404);
        }
        return $this->success($report);
    }

    /**
     * GET /api/reports/{id}/pages - lista páginas (abas) do relatório no Power BI (admin).
     */
    public function pages(): Response
    {
        if ($r = $this->requireAdmin()) {
            return $r;
        }
        $id = (int) $this->param('id');
        $report = $this->reportService->findById($id);
        if (!$report || empty($report['powerbi_workspace_id']) || empty($report['powerbi_report_id'])) {
            return $this->error('Relatório não encontrado ou não configurado', 404);
        }
        $tenant = $this->tenantService->findByIdForApi($report['tenant_id']);
        if (!$tenant) {
            return $this->error('Conexão com o workspace não disponível', 503);
        }
        $list = $this->powerBIService->getReportPages(
            $tenant,
            $report['powerbi_workspace_id'],
            $report['powerbi_report_id']
        );
        return $this->success($list);
    }

    /**
     * PUT /api/reports/{id} - atualiza relatório (nome, imagem).
     */
    public function update(): Response
    {
        if ($r = $this->requireAdmin()) {
            return $r;
        }
        $id = (int) $this->param('id');
        $report = $this->reportService->findById($id);
        if (!$report) {
            return $this->error('Relatório não encontrado', 404);
        }
        $body = $this->request->getBody();
        $data = [];
        if (array_key_exists('nome', $body)) {
            $data['nome'] = trim((string) $body['nome']);
        }
        if (array_key_exists('dono_texto', $body)) {
            $data['dono_texto'] = $body['dono_texto'] === '' || $body['dono_texto'] === null ? null : trim((string) $body['dono_texto']);
        }
        if (array_key_exists('imagem', $body)) {
            $data['imagem'] = $body['imagem'] === '' || $body['imagem'] === null ? null : trim((string) $body['imagem']);
        }
        if (empty($data)) {
            return $this->success($this->reportService->findById($id));
        }
        $this->reportService->update($id, $data);
        return $this->success($this->reportService->findById($id));
    }

    /**
     * POST /api/reports/{id}/imagem - upload de imagem do relatório.
     * Salva em public/img/reports/{id}.ext e atualiza report.imagem.
     */
    public function uploadImage(): Response
    {
        if ($r = $this->requireAdmin()) {
            return $r;
        }
        $id = (int) $this->param('id');
        $report = $this->reportService->findById($id);
        if (!$report) {
            return $this->error('Relatório não encontrado', 404);
        }
        $file = $_FILES['imagem'] ?? $_FILES['file'] ?? null;
        if (!$file || ($file['error'] !== UPLOAD_ERR_OK)) {
            return $this->error('Nenhum arquivo enviado ou erro no upload', 400);
        }
        $allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        if (!in_array($mime, $allowed, true)) {
            return $this->error('Apenas imagens (JPG, PNG, GIF, WebP) são permitidas', 400);
        }
        $ext = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
        ];
        $ext = $ext[$mime] ?? 'png';
        $dir = __DIR__ . '/../../../public/img/reports';
        if (!is_dir($dir)) {
            if (!@mkdir($dir, 0755, true)) {
                return $this->error('Não foi possível criar o diretório de imagens', 500);
            }
        }
        $filename = $id . '.' . $ext;
        $path = $dir . '/' . $filename;
        if (!move_uploaded_file($file['tmp_name'], $path)) {
            return $this->error('Falha ao salvar a imagem', 500);
        }
        $relativePath = 'img/reports/' . $filename;
        $this->reportService->update($id, ['imagem' => $relativePath]);
        return $this->success([
            'imagem' => $relativePath,
            'report' => $this->reportService->findById($id),
        ]);
    }
}
