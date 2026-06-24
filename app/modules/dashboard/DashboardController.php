<?php

require_once __DIR__ . '/../../core/Controller.php';
require_once __DIR__ . '/../../services/AuthService.php';
require_once __DIR__ . '/../../services/KpiService.php';

/**
 * DashboardController - KPIs e resumo para o dashboard do usuário
 */
class DashboardController extends Controller
{
    /** @var AuthService */
    private $authService;

    /** @var KpiService */
    private $kpiService;

    public function __construct()
    {
        $this->authService = new AuthService();
        $this->kpiService = new KpiService();
    }

    /**
     * GET /api/dashboard/kpis - KPIs do usuário logado (relatórios, acessos, favoritos)
     * Query: data_inicio (Y-m-d), data_fim (Y-m-d) - opcional, padrão últimos 30 dias
     */
    public function kpis(): Response
    {
        if (!$this->authService->getCurrentUser()) {
            return $this->error('Não autenticado', 401);
        }
        $userId = $this->authService->getCurrentUserId();
        $dataInicio = $this->request->get('data_inicio') ? trim((string) $this->request->get('data_inicio')) : null;
        $dataFim = $this->request->get('data_fim') ? trim((string) $this->request->get('data_fim')) : null;
        $data = $this->kpiService->getKpisForUser($userId, $dataInicio, $dataFim);
        return $this->success($data);
    }
}
