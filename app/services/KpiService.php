<?php

require_once __DIR__ . '/../repositories/ReportRepository.php';
require_once __DIR__ . '/LogService.php';
require_once __DIR__ . '/../repositories/FavoritoRepository.php';

/**
 * KpiService - KPIs do dashboard (relatórios, acessos, favoritos) sem depender do Power BI.
 * Todos os dados vêm do banco (reports, log_acesso_relatorio, user_relatorio_favorito).
 */
class KpiService
{
    /** @var ReportRepository */
    private $reportRepository;

    /** @var LogService */
    private $logService;

    /** @var FavoritoRepository */
    private $favoritoRepository;

    public function __construct()
    {
        $this->reportRepository = new ReportRepository();
        $this->logService = new LogService();
        $this->favoritoRepository = new FavoritoRepository();
    }

    /**
     * Retorna todos os KPIs para o usuário logado (relatórios que ele pode ver).
     * @param int $userId
     * @param string|null $dataInicio Y-m-d (padrão: 30 dias atrás)
     * @param string|null $dataFim Y-m-d (padrão: hoje)
     */
    public function getKpisForUser(int $userId, ?string $dataInicio = null, ?string $dataFim = null): array
    {
        $hoje = date('Y-m-d');
        if ($dataFim === null || $dataFim === '') {
            $dataFim = $hoje;
        }
        if ($dataInicio === null || $dataInicio === '') {
            $dataInicio = date('Y-m-d', strtotime('-30 days'));
        }

        $allowedReports = $this->reportRepository->findAllowedByUser($userId, null);
        $allowedReportIds = array_map(function ($r) {
            return (int) $r['id'];
        }, $allowedReports);
        if (count($allowedReportIds) === 0) {
            $allowedReportIds = null; // usuário sem relatórios: métricas de log vazias
        }

        $totalRelatorios = $this->reportRepository->countAllowedByUser($userId);
        $relatoriosAtualizadosUltimaSemana = $this->reportRepository->countAtualizadosRecentemente($userId, 7);
        $relatoriosMaisAcessados = $this->logService->findRelatoriosMaisAcessados(5, $dataInicio, $dataFim . ' 23:59:59', $allowedReportIds);
        $usuariosMaisAtivos = $this->logService->findUsuariosMaisAtivos(5, $dataInicio, $dataFim . ' 23:59:59', $allowedReportIds);

        $acessosHoje = $this->logService->countAcessosNoPeriodo($hoje, $hoje . ' 23:59:59', $allowedReportIds);
        $semanaInicio = date('Y-m-d', strtotime('-7 days'));
        $acessosSemana = $this->logService->countAcessosNoPeriodo($semanaInicio, $dataFim . ' 23:59:59', $allowedReportIds);
        $acessosMes = $this->logService->countAcessosNoPeriodo($dataInicio, $dataFim . ' 23:59:59', $allowedReportIds);

        $tempoMedioSeg = $this->logService->tempoMedioVisualizacao($allowedReportIds);
        $relatoriosSemAcessoRecente = $this->reportRepository->findRelatoriosSemAcessoRecente($userId, 30);
        $favoritosCount = $this->favoritoRepository->countFavoritosByUser($userId);
        $fixadosCount = $this->favoritoRepository->countFixadosByUser($userId);

        return [
            'total_relatorios' => $totalRelatorios,
            'relatorios_atualizados_ultima_semana' => $relatoriosAtualizadosUltimaSemana,
            'relatorios_mais_acessados' => $relatoriosMaisAcessados,
            'usuarios_mais_ativos' => $usuariosMaisAtivos,
            'acessos_hoje' => $acessosHoje,
            'acessos_semana' => $acessosSemana,
            'acessos_mes' => $acessosMes,
            'tempo_medio_visualizacao_segundos' => $tempoMedioSeg !== null ? round($tempoMedioSeg, 1) : null,
            'relatorios_sem_acesso_recente' => $relatoriosSemAcessoRecente,
            'favoritos_count' => $favoritosCount,
            'fixados_count' => $fixadosCount,
            'periodo' => ['data_inicio' => $dataInicio, 'data_fim' => $dataFim],
        ];
    }
}
