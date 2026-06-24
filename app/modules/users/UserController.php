<?php

require_once __DIR__ . '/../../core/Controller.php';
require_once __DIR__ . '/../../services/AuthService.php';
require_once __DIR__ . '/../../services/UserService.php';
require_once __DIR__ . '/../../config/Config.php';

/**
 * UserController - CRUD usuários (admin)
 */
class UserController extends Controller
{
    /** @var AuthService */
    private $authService;

    /** @var UserService */
    private $userService;

    public function __construct()
    {
        $this->authService = new AuthService();
        $this->userService = new UserService();
    }

    private function requireAdmin(): ?Response
    {
        if (!$this->authService->hasProfile(Config::PROFILE_ADMIN)) {
            return $this->error('Acesso negado', 403);
        }
        return null;
    }

    /**
     * GET /api/users
     */
    public function index(): Response
    {
        if ($r = $this->requireAdmin()) {
            return $r;
        }
        $ativos = $this->request->get('ativos') !== '0';
        $list = $this->userService->findAll($ativos);
        return $this->success($list);
    }

    /**
     * GET /api/users/{id}
     */
    public function show(): Response
    {
        if ($r = $this->requireAdmin()) {
            return $r;
        }
        $id = (int) $this->param('id');
        $user = $this->userService->findById($id);
        if (!$user) {
            return $this->error('Usuário não encontrado', 404);
        }
        $user['group_ids'] = $this->userService->getGroupIds($id);
        $user['folder_ids'] = $this->userService->getFolderIds($id);
        return $this->success($user);
    }

    /**
     * POST /api/users
     */
    public function store(): Response
    {
        if ($r = $this->requireAdmin()) {
            return $r;
        }
        $data = $this->request->getBody();
        $errors = $this->validate($data, [
            'nome' => 'required',
            'email' => 'required',
        ]);
        if (!empty($errors)) {
            return $this->error('Dados inválidos', 400, $errors);
        }
        try {
            $id = $this->userService->create($data);
            $user = $this->userService->findById($id);
            return $this->success($user, 'Usuário criado', 201);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), 400);
        }
    }

    /**
     * PUT /api/users/{id}
     */
    public function update(): Response
    {
        if ($r = $this->requireAdmin()) {
            return $r;
        }
        $id = (int) $this->param('id');
        $data = $this->request->getBody();
        try {
            $this->userService->update($id, $data);
            $user = $this->userService->findById($id);
            return $this->success($user, 'Usuário atualizado');
        } catch (Exception $e) {
            return $this->error($e->getMessage(), 400);
        }
    }

    /**
     * POST /api/users/{id}/groups - body: { "group_ids": [1,2,3] }
     */
    public function setGroups(): Response
    {
        if ($r = $this->requireAdmin()) {
            return $r;
        }
        $id = (int) $this->param('id');
        $data = $this->request->getBody();
        $groupIds = $data['group_ids'] ?? [];
        if (!is_array($groupIds)) {
            $groupIds = [];
        }
        $this->userService->setGroups($id, $groupIds);
        return $this->success([], 'Grupos atualizados');
    }

    /**
     * POST /api/users/{id}/folders - body: { "folder_ids": [1,2,3] }
     */
    public function setFolders(): Response
    {
        if ($r = $this->requireAdmin()) {
            return $r;
        }
        $id = (int) $this->param('id');
        $user = $this->userService->findById($id);
        if (!$user) {
            return $this->error('Usuário não encontrado', 404);
        }
        $data = $this->request->getBody();
        $folderIds = $data['folder_ids'] ?? [];
        if (!is_array($folderIds)) {
            $folderIds = [];
        }
        $this->userService->setFolders($id, $folderIds);
        return $this->success([], 'Pastas do usuário atualizadas');
    }

    /**
     * DELETE /api/users/{id}
     */
    public function delete(): Response
    {
        if ($r = $this->requireAdmin()) {
            return $r;
        }
        $id = (int) $this->param('id');
        $this->userService->delete($id);
        return $this->success([], 'Usuário removido');
    }
}
