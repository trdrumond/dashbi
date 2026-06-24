<?php

require_once __DIR__ . '/../../core/Controller.php';
require_once __DIR__ . '/../../services/AuthService.php';
require_once __DIR__ . '/../../services/GroupService.php';
require_once __DIR__ . '/../../config/Config.php';

/**
 * GroupController - CRUD grupos
 */
class GroupController extends Controller
{
    /** @var AuthService */
    private $authService;

    /** @var GroupService */
    private $groupService;

    public function __construct()
    {
        $this->authService = new AuthService();
        $this->groupService = new GroupService();
    }

    private function requireAdmin(): ?Response
    {
        if (!$this->authService->hasProfile(Config::PROFILE_ADMIN)) {
            return $this->error('Acesso negado', 403);
        }
        return null;
    }

    /**
     * GET /api/groups
     */
    public function index(): Response
    {
        if ($r = $this->requireAdmin()) {
            return $r;
        }
        $list = $this->groupService->findAll();
        return $this->success($list);
    }

    /**
     * GET /api/groups/{id}
     */
    public function show(): Response
    {
        if ($r = $this->requireAdmin()) {
            return $r;
        }
        $id = (int) $this->param('id');
        $group = $this->groupService->findById($id);
        if (!$group) {
            return $this->error('Grupo não encontrado', 404);
        }
        $group['folder_ids'] = $this->groupService->getFolderIds($id);
        return $this->success($group);
    }

    /**
     * POST /api/groups
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
            $id = $this->groupService->create($data);
            $group = $this->groupService->findById($id);
            return $this->success($group, 'Grupo criado', 201);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), 400);
        }
    }

    /**
     * PUT /api/groups/{id}
     */
    public function update(): Response
    {
        if ($r = $this->requireAdmin()) {
            return $r;
        }
        $id = (int) $this->param('id');
        $data = $this->request->getBody();
        $this->groupService->update($id, $data);
        $group = $this->groupService->findById($id);
        return $this->success($group, 'Grupo atualizado');
    }

    /**
     * DELETE /api/groups/{id}
     */
    public function delete(): Response
    {
        if ($r = $this->requireAdmin()) {
            return $r;
        }
        $id = (int) $this->param('id');
        $this->groupService->delete($id);
        return $this->success([], 'Grupo removido');
    }

    /**
     * GET /api/groups/{id}/users - usuários do grupo
     */
    public function users(): Response
    {
        if ($r = $this->requireAdmin()) {
            return $r;
        }
        $id = (int) $this->param('id');
        $userIds = $this->groupService->getUserIds($id);
        return $this->success(['user_ids' => $userIds]);
    }

    /**
     * POST /api/groups/{id}/folders - body: { "folder_ids": [1,2,3] }
     */
    public function setFolders(): Response
    {
        if ($r = $this->requireAdmin()) {
            return $r;
        }
        $id = (int) $this->param('id');
        $group = $this->groupService->findById($id);
        if (!$group) {
            return $this->error('Grupo não encontrado', 404);
        }
        $data = $this->request->getBody();
        $folderIds = $data['folder_ids'] ?? [];
        if (!is_array($folderIds)) {
            $folderIds = [];
        }
        $this->groupService->setFolders($id, $folderIds);
        return $this->success([], 'Pastas do grupo atualizadas');
    }
}
