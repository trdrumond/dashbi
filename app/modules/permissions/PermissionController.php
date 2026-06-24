<?php

require_once __DIR__ . '/../../core/Controller.php';
require_once __DIR__ . '/../../services/AuthService.php';
require_once __DIR__ . '/../../services/PermissionService.php';
require_once __DIR__ . '/../../config/Config.php';

/**
 * PermissionController - CRUD de permissões por relatório
 */
class PermissionController extends Controller
{
    /** @var AuthService */
    private $authService;

    /** @var PermissionService */
    private $permissionService;

    public function __construct()
    {
        $this->authService = new AuthService();
        $this->permissionService = new PermissionService();
    }

    private function requireAdmin(): ?Response
    {
        if (!$this->authService->hasProfile(Config::PROFILE_ADMIN)) {
            return $this->error('Acesso negado', 403);
        }
        return null;
    }

    /**
     * GET /api/reports/{reportId}/permissions
     */
    public function index(): Response
    {
        if ($r = $this->requireAdmin()) {
            return $r;
        }
        $reportId = (int) $this->param('reportId');
        $list = $this->permissionService->listByReport($reportId);
        return $this->success($list);
    }

    /**
     * POST /api/reports/{reportId}/permissions
     */
    public function store(): Response
    {
        if ($r = $this->requireAdmin()) {
            return $r;
        }
        $reportId = (int) $this->param('reportId');
        $data = $this->request->getBody();
        $data['report_id'] = $reportId;

        $errors = $this->validate($data, []);
        if (!empty($data['user_id']) && !empty($data['group_id'])) {
            return $this->error('Informe apenas user_id ou group_id', 400);
        }
        if (empty($data['user_id']) && empty($data['group_id'])) {
            return $this->error('Informe user_id ou group_id', 400);
        }

        try {
            $id = $this->permissionService->create($data);
            return $this->success(['id' => $id], 'Permissão criada', 201);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), 400);
        }
    }

    /**
     * PUT /api/permissions/{id}
     */
    public function update(): Response
    {
        if ($r = $this->requireAdmin()) {
            return $r;
        }
        $id = (int) $this->param('id');
        $data = $this->request->getBody();
        $this->permissionService->update($id, $data);
        return $this->success([], 'Permissão atualizada');
    }

    /**
     * DELETE /api/permissions/{id}
     */
    public function delete(): Response
    {
        if ($r = $this->requireAdmin()) {
            return $r;
        }
        $id = (int) $this->param('id');
        $this->permissionService->delete($id);
        return $this->success([], 'Permissão removida');
    }
}
