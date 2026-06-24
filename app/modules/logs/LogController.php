<?php

require_once __DIR__ . '/../../core/Controller.php';
require_once __DIR__ . '/../../services/AuthService.php';
require_once __DIR__ . '/../../services/LogService.php';
require_once __DIR__ . '/../../config/Config.php';

/**
 * LogController - auditoria (admin)
 */
class LogController extends Controller
{
    /** @var AuthService */
    private $authService;

    /** @var LogService */
    private $logService;

    public function __construct()
    {
        $this->authService = new AuthService();
        $this->logService = new LogService();
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
     * GET /api/logs (apenas master)
     */
    public function index(): Response
    {
        if ($r = $this->requireMaster()) {
            return $r;
        }
        $limit = min(500, (int) ($this->request->get('limit') ?: 100));
        $list = $this->logService->findRecent($limit);
        return $this->success($list);
    }

    /**
     * GET /api/logs/metrics - relatórios mais acessados, usuários ativos, horários de pico (apenas master).
     * Query: data_inicio (Y-m-d), data_fim (Y-m-d), limit (default 20).
     */
    public function metrics(): Response
    {
        if ($r = $this->requireMaster()) {
            return $r;
        }
        $dataInicio = $this->request->get('data_inicio') ? trim((string) $this->request->get('data_inicio')) : null;
        $dataFim = $this->request->get('data_fim') ? trim((string) $this->request->get('data_fim')) : null;
        $limit = min(100, (int) ($this->request->get('limit') ?: 20));
        $relatorios = $this->logService->findRelatoriosMaisAcessados($limit, $dataInicio, $dataFim);
        $usuarios = $this->logService->findUsuariosMaisAtivos($limit, $dataInicio, $dataFim);
        $porHora = $this->logService->findAcessosPorHora($dataInicio, $dataFim);
        return $this->success([
            'relatorios_mais_acessados' => $relatorios,
            'usuarios_mais_ativos' => $usuarios,
            'acessos_por_hora' => $porHora,
        ]);
    }
}
