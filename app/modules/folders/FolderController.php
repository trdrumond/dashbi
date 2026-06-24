<?php

require_once __DIR__ . '/../../core/Controller.php';
require_once __DIR__ . '/../../services/AuthService.php';
require_once __DIR__ . '/../../services/ReportService.php';
require_once __DIR__ . '/../../repositories/FolderRepository.php';
require_once __DIR__ . '/../../config/Config.php';

/**
 * FolderController - CRUD pastas e vínculo pasta ↔ relatórios
 */
class FolderController extends Controller
{
    /** @var AuthService */
    private $authService;

    /** @var FolderRepository */
    private $folderRepository;

    /** @var ReportService */
    private $reportService;

    public function __construct()
    {
        $this->authService = new AuthService();
        $this->folderRepository = new FolderRepository();
        $this->reportService = new ReportService();
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
     * GET /api/folders/for-dashboard - apenas pastas configuradas para o usuário ou para seus grupos,
     * com relatórios que o usuário pode ver (para Meus relatórios).
     */
    public function forDashboard(): Response
    {
        if ($r = $this->requireAuth()) {
            return $r;
        }
        $userId = (int) $this->authService->getCurrentUserId();
        $allowedReports = $this->reportService->findAllowedByUser($userId, null);
        $reportById = [];
        foreach ($allowedReports as $r) {
            $reportById[(int) $r['id']] = $r;
        }
        $allowedIds = array_keys($reportById);

        // Apenas pastas atribuídas ao usuário (user_folder) ou a algum grupo do usuário (group_folder)
        $userFolderIds = $this->folderRepository->getFolderIdsForUser($userId);
        $folders = $this->folderRepository->findActiveByIds($userFolderIds);

        $reportIdsInAnyFolder = [];
        $foldersWithReports = [];
        foreach ($folders as $folder) {
            $folderId = (int) $folder['id'];
            $reportIds = $this->folderRepository->getReportIds($folderId);
            $folderReportIds = array_intersect($reportIds, $allowedIds);
            foreach ($folderReportIds as $rid) {
                $reportIdsInAnyFolder[$rid] = true;
            }
            $reports = [];
            foreach ($folderReportIds as $rid) {
                if (isset($reportById[$rid])) {
                    $reports[] = $reportById[$rid];
                }
            }
            if (count($reports) > 0) {
                $foldersWithReports[] = [
                    'id' => (int) $folder['id'],
                    'nome' => $folder['nome'],
                    'descricao' => $folder['descricao'] ?? null,
                    'ordem' => isset($folder['ordem']) ? (int) $folder['ordem'] : null,
                    'reports' => $reports,
                ];
            }
        }
        // Relatórios que o usuário pode ver mas que não estão em nenhuma das suas pastas
        $reportsWithoutFolder = [];
        foreach ($allowedIds as $rid) {
            if (empty($reportIdsInAnyFolder[$rid])) {
                $reportsWithoutFolder[] = $reportById[$rid];
            }
        }
        return $this->success([
            'folders' => $foldersWithReports,
            'reports_without_folder' => $reportsWithoutFolder,
        ]);
    }

    /**
     * GET /api/folders
     */
    public function index(): Response
    {
        if ($r = $this->requireAdmin()) {
            return $r;
        }
        $list = $this->folderRepository->findAll();
        return $this->success($list);
    }

    /**
     * GET /api/folders/{id}
     */
    public function show(): Response
    {
        if ($r = $this->requireAdmin()) {
            return $r;
        }
        $id = (int) $this->param('id');
        $folder = $this->folderRepository->findById($id);
        if (!$folder) {
            return $this->error('Pasta não encontrada', 404);
        }
        $folder['report_ids'] = $this->folderRepository->getReportIds($id);
        return $this->success($folder);
    }

    /**
     * POST /api/folders
     */
    public function store(): Response
    {
        if ($r = $this->requireAdmin()) {
            return $r;
        }
        $data = $this->request->getBody();
        $errors = $this->validate($data, ['nome' => 'required']);
        if (!empty($errors)) {
            return $this->error('Dados inválidos', 400, $errors);
        }
        try {
            $id = $this->folderRepository->create($data);
            $folder = $this->folderRepository->findById($id);
            $folder['report_ids'] = [];
            return $this->success($folder, 'Pasta criada', 201);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), 400);
        }
    }

    /**
     * PUT /api/folders/{id}
     */
    public function update(): Response
    {
        if ($r = $this->requireAdmin()) {
            return $r;
        }
        $id = (int) $this->param('id');
        $data = $this->request->getBody();
        $folder = $this->folderRepository->findById($id);
        if (!$folder) {
            return $this->error('Pasta não encontrada', 404);
        }
        $this->folderRepository->update($id, $data);
        $folder = $this->folderRepository->findById($id);
        $folder['report_ids'] = $this->folderRepository->getReportIds($id);
        return $this->success($folder, 'Pasta atualizada');
    }

    /**
     * DELETE /api/folders/{id}
     */
    public function delete(): Response
    {
        if ($r = $this->requireAdmin()) {
            return $r;
        }
        $id = (int) $this->param('id');
        $folder = $this->folderRepository->findById($id);
        if (!$folder) {
            return $this->error('Pasta não encontrada', 404);
        }
        $this->folderRepository->delete($id);
        return $this->success([], 'Pasta removida');
    }

    /**
     * GET /api/folders/{id}/reports - relatórios da pasta (ids ou lista completa para admin)
     */
    public function reports(): Response
    {
        if ($r = $this->requireAdmin()) {
            return $r;
        }
        $id = (int) $this->param('id');
        $folder = $this->folderRepository->findById($id);
        if (!$folder) {
            return $this->error('Pasta não encontrada', 404);
        }
        $reportIds = $this->folderRepository->getReportIds($id);
        return $this->success(['report_ids' => $reportIds]);
    }

    /**
     * PUT /api/folders/{id}/reports - body: { "report_ids": [1,2,3] }
     */
    public function setReports(): Response
    {
        if ($r = $this->requireAdmin()) {
            return $r;
        }
        $id = (int) $this->param('id');
        $folder = $this->folderRepository->findById($id);
        if (!$folder) {
            return $this->error('Pasta não encontrada', 404);
        }
        $data = $this->request->getBody();
        $reportIds = $data['report_ids'] ?? [];
        if (!is_array($reportIds)) {
            $reportIds = [];
        }
        $this->folderRepository->setReports($id, $reportIds);
        return $this->success(['report_ids' => $this->folderRepository->getReportIds($id)], 'Relatórios da pasta atualizados');
    }
}
