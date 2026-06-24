<?php

require_once __DIR__ . '/../../core/Controller.php';
require_once __DIR__ . '/../../services/AuthService.php';
require_once __DIR__ . '/../../services/TenantService.php';
require_once __DIR__ . '/../../config/Config.php';

/**
 * TenantController - CRUD de tenants (conexão Microsoft)
 */
class TenantController extends Controller
{
    /** @var TenantService */
    private $tenantService;

    /** @var AuthService */
    private $authService;

    public function __construct()
    {
        $this->tenantService = new TenantService();
        $this->authService = new AuthService();
    }

    private function requireMaster(): ?Response
    {
        if (!$this->authService->getCurrentUser()) {
            return $this->error('Não autenticado', 401);
        }
        if (!$this->authService->hasProfile(Config::PROFILE_MASTER)) {
            return $this->error('Acesso negado. Apenas perfil master.', 403);
        }
        return null;
    }

    /**
     * GET /api/tenants (apenas master)
     */
    public function index(): Response
    {
        if ($r = $this->requireMaster()) {
            return $r;
        }
        $list = $this->tenantService->findAll($this->request->get('ativos') !== '0');
        return $this->success($list);
    }

    /**
     * GET /api/tenants/{id}
     */
    public function show(): Response
    {
        if ($r = $this->requireMaster()) {
            return $r;
        }
        $id = (int) $this->param('id');
        $tenant = $this->tenantService->findById($id);
        if (!$tenant) {
            return $this->error('Tenant não encontrado', 404);
        }
        return $this->success($tenant);
    }

    /**
     * POST /api/tenants
     */
    public function store(): Response
    {
        if ($r = $this->requireMaster()) {
            return $r;
        }
        $data = $this->request->getBody();
        $errors = $this->validate($data, [
            'nome' => 'required',
            'tenant_id' => 'required',
            'client_id' => 'required',
            'client_secret' => 'required',
        ]);
        if (!empty($errors)) {
            return $this->error('Dados inválidos', 400, $errors);
        }
        try {
            $id = $this->tenantService->create($data);
            $tenant = $this->tenantService->findById($id);
            return $this->success($tenant, 'Tenant criado', 201);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), 400);
        }
    }

    /**
     * PUT /api/tenants/{id}
     */
    public function update(): Response
    {
        if ($r = $this->requireMaster()) {
            return $r;
        }
        $id = (int) $this->param('id');
        $data = $this->request->getBody();
        try {
            $this->tenantService->update($id, $data);
            $tenant = $this->tenantService->findById($id);
            return $this->success($tenant, 'Tenant atualizado');
        } catch (Exception $e) {
            return $this->error($e->getMessage(), 400);
        }
    }

    /**
     * POST /api/tenants/{id}/test
     */
    public function testConnection(): Response
    {
        if ($r = $this->requireMaster()) {
            return $r;
        }
        $id = (int) $this->param('id');
        $diag = $this->tenantService->testConnectionDetailed($id);
        $ok = !empty($diag['connected']);
        return $this->success($diag, $ok ? 'Conexão OK' : 'Falha na conexão');
    }

    /**
     * DELETE /api/tenants/{id}
     */
    public function delete(): Response
    {
        if ($r = $this->requireMaster()) {
            return $r;
        }
        $id = (int) $this->param('id');
        $this->tenantService->delete($id);
        return $this->success([], 'Tenant removido');
    }
}
