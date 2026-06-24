<?php

require_once __DIR__ . '/../repositories/LogRepository.php';

/**
 * LogService - auditoria de acesso
 * PHP 7.3+
 */
class LogService
{
    /** @var LogRepository */
    private $logRepository;

    public function __construct()
    {
        $this->logRepository = new LogRepository();
    }

    public function log(int $userId, int $reportId, string $acao, string $ip): void
    {
        $this->logRepository->log($userId, $reportId, $acao, $ip);
    }

    public function findRecent(int $limit = 100): array
    {
        return $this->logRepository->findRecent($limit);
    }

    public function countByReport(int $reportId): int
    {
        return $this->logRepository->countByReport($reportId);
    }

    /**
     * Inicia registro de acesso ao relatório (auditoria completa). Retorna ID para fechar depois.
     */
    public function startLogAcessoRelatorio(int $userId, int $reportId, ?string $ip): int
    {
        return $this->logRepository->createLogAcessoRelatorio($userId, $reportId, $ip);
    }

    /**
     * Fecha o log de acesso com tempo de visualização e opcionalmente filtros.
     */
    public function endLogAcessoRelatorio(int $logId, int $userId, ?int $tempoSegundos, $filtrosUtilizados = null): bool
    {
        return $this->logRepository->updateLogAcessoRelatorio($logId, $userId, $tempoSegundos, $filtrosUtilizados);
    }

    public function findLogAcessoRelatorioById(int $id): ?array
    {
        return $this->logRepository->findLogAcessoRelatorioById($id);
    }

    public function findRelatoriosMaisAcessados(int $limit = 20, ?string $dataInicio = null, ?string $dataFim = null, ?array $allowedReportIds = null): array
    {
        return $this->logRepository->findRelatoriosMaisAcessados($limit, $dataInicio, $dataFim, $allowedReportIds);
    }

    public function findUsuariosMaisAtivos(int $limit = 20, ?string $dataInicio = null, ?string $dataFim = null, ?array $allowedReportIds = null): array
    {
        return $this->logRepository->findUsuariosMaisAtivos($limit, $dataInicio, $dataFim, $allowedReportIds);
    }

    public function findAcessosPorHora(?string $dataInicio = null, ?string $dataFim = null, ?array $allowedReportIds = null): array
    {
        return $this->logRepository->findAcessosPorHora($dataInicio, $dataFim, $allowedReportIds);
    }

    public function countAcessosNoPeriodo(?string $dataInicio = null, ?string $dataFim = null, ?array $allowedReportIds = null): int
    {
        return $this->logRepository->countAcessosNoPeriodo($dataInicio, $dataFim, $allowedReportIds);
    }

    public function tempoMedioVisualizacao(?array $allowedReportIds = null): ?float
    {
        return $this->logRepository->tempoMedioVisualizacao($allowedReportIds);
    }
}
