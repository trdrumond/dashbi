<?php

require_once __DIR__ . '/../../core/Controller.php';
require_once __DIR__ . '/../../services/AuthService.php';
require_once __DIR__ . '/../../services/PermissionService.php';
require_once __DIR__ . '/../../services/TvPanelService.php';
require_once __DIR__ . '/../../config/Config.php';

/**
 * TvPanelController - CRUD e execução de Painel TV
 * PHP 7.3+
 */
class TvPanelController extends Controller
{
    /** @var AuthService */
    private $authService;

    /** @var PermissionService */
    private $permissionService;

    /** @var TvPanelService */
    private $tvPanelService;

    public function __construct()
    {
        $this->authService = new AuthService();
        $this->permissionService = new PermissionService();
        $this->tvPanelService = new TvPanelService();
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
     * GET /api/tv-panels
     */
    public function index(): Response
    {
        $onlyActive = $this->request->get('ativos') === '1';
        if ($onlyActive) {
            if ($r = $this->requireAuth()) {
                return $r;
            }
        } else {
            if ($r = $this->requireAdmin()) {
                return $r;
            }
        }
        return $this->success($this->tvPanelService->findAll($onlyActive));
    }

    /**
     * GET /api/tv-panels/{id}
     */
    public function show(): Response
    {
        if ($r = $this->requireAdmin()) {
            return $r;
        }
        $id = (int) $this->param('id');
        $panel = $this->tvPanelService->findById($id);
        if (!$panel) {
            return $this->error('Painel TV não encontrado', 404);
        }
        $panel['items'] = $this->tvPanelService->getItems($id);
        return $this->success($panel);
    }

    /**
     * POST /api/tv-panels
     */
    public function store(): Response
    {
        if ($r = $this->requireAdmin()) {
            return $r;
        }
        $body = $this->request->getBody();
        $nome = trim((string) ($body['nome'] ?? ''));
        if ($nome === '') {
            return $this->error('Nome do painel é obrigatório', 400);
        }
        $modoReproducao = (string) ($body['modo_reproducao'] ?? 'loop');
        if (!in_array($modoReproducao, ['loop', 'once'], true)) {
            $modoReproducao = 'loop';
        }
        $id = $this->tvPanelService->create([
            'nome' => $nome,
            'ativo' => isset($body['ativo']) ? (int) $body['ativo'] : 1,
            'modo_reproducao' => $modoReproducao,
            'mostrar_contador' => isset($body['mostrar_contador']) ? (int) $body['mostrar_contador'] : 1,
            'criado_por' => (int) $this->authService->getCurrentUserId(),
        ]);
        $panel = $this->tvPanelService->findById($id);
        return $this->success($panel, 'Painel TV criado', 201);
    }

    /**
     * PUT /api/tv-panels/{id}
     */
    public function update(): Response
    {
        if ($r = $this->requireAdmin()) {
            return $r;
        }
        $id = (int) $this->param('id');
        if (!$this->tvPanelService->findById($id)) {
            return $this->error('Painel TV não encontrado', 404);
        }
        $body = $this->request->getBody();
        $data = [];
        if (array_key_exists('nome', $body)) {
            $data['nome'] = trim((string) $body['nome']);
        }
        if (array_key_exists('ativo', $body)) {
            $data['ativo'] = (int) $body['ativo'];
        }
        if (array_key_exists('modo_reproducao', $body)) {
            $data['modo_reproducao'] = in_array($body['modo_reproducao'], ['loop', 'once'], true) ? $body['modo_reproducao'] : 'loop';
        }
        if (array_key_exists('mostrar_contador', $body)) {
            $data['mostrar_contador'] = (int) $body['mostrar_contador'] ? 1 : 0;
        }
        $this->tvPanelService->update($id, $data);
        $panel = $this->tvPanelService->findById($id);
        return $this->success($panel, 'Painel TV atualizado');
    }

    /**
     * DELETE /api/tv-panels/{id}
     */
    public function delete(): Response
    {
        if ($r = $this->requireAdmin()) {
            return $r;
        }
        $id = (int) $this->param('id');
        $this->tvPanelService->delete($id);
        return $this->success([], 'Painel TV removido');
    }

    /**
     * PUT /api/tv-panels/{id}/items
     */
    public function saveItems(): Response
    {
        if ($r = $this->requireAdmin()) {
            return $r;
        }
        $id = (int) $this->param('id');
        if (!$this->tvPanelService->findById($id)) {
            return $this->error('Painel TV não encontrado', 404);
        }
        $body = $this->request->getBody();
        $items = is_array($body['items'] ?? null) ? $body['items'] : [];
        $this->tvPanelService->replaceItems($id, $items);
        return $this->success($this->tvPanelService->getItems($id), 'Lista do painel atualizada');
    }

    /**
     * GET /api/tv-panels/{id}/play
     * Retorna somente itens que o usuário logado pode visualizar.
     */
    public function playConfig(): Response
    {
        if ($r = $this->requireAuth()) {
            return $r;
        }
        $id = (int) $this->param('id');
        $panel = $this->tvPanelService->findById($id);
        if (!$panel || (int) ($panel['ativo'] ?? 0) !== 1) {
            return $this->error('Painel TV não encontrado ou inativo', 404);
        }
        $userId = (int) $this->authService->getCurrentUserId();
        $items = $this->tvPanelService->getItems($id);
        $allowed = [];
        foreach ($items as $item) {
            $reportId = (int) ($item['report_id'] ?? 0);
            if ($reportId <= 0) {
                continue;
            }
            if (!$this->permissionService->canView($userId, $reportId)) {
                continue;
            }
            $allowed[] = [
                'report_id' => $reportId,
                'report_nome' => $item['report_nome'] ?? '',
                'report_imagem' => $item['report_imagem'] ?? null,
                'tempo_segundos' => (int) ($item['tempo_segundos'] ?? 30),
                'ordem' => (int) ($item['ordem'] ?? 1),
                'pagina_nome' => isset($item['pagina_nome']) && $item['pagina_nome'] !== '' ? trim((string) $item['pagina_nome']) : null,
            ];
        }
        $modoReproducao = (string) ($panel['modo_reproducao'] ?? 'loop');
        if (!in_array($modoReproducao, ['loop', 'once'], true)) {
            $modoReproducao = 'loop';
        }
        return $this->success([
            'id' => (int) $panel['id'],
            'nome' => $panel['nome'],
            'modo_reproducao' => $modoReproducao,
            'mostrar_contador' => !empty($panel['mostrar_contador']),
            'items' => $allowed,
        ]);
    }
}
