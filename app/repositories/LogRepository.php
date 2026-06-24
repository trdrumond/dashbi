<?php

require_once __DIR__ . '/../config/Database.php';

/**
 * LogRepository - access_logs para auditoria
 * PHP 7.3+
 */
class LogRepository
{
    /** @var PDO */
    private $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    public function log(int $userId, int $reportId, string $acao, string $ip): void
    {
        $stmt = $this->db->prepare(
            'INSERT INTO access_logs (user_id, report_id, acao, ip, created_at) VALUES (?, ?, ?, ?, NOW())'
        );
        $stmt->execute([$userId, $reportId, $acao, $ip]);
    }

    public function findRecent(int $limit = 100): array
    {
        $stmt = $this->db->prepare(
            'SELECT l.*, u.nome as user_nome, r.nome as report_nome
             FROM access_logs l
             LEFT JOIN users u ON l.user_id = u.id
             LEFT JOIN reports r ON l.report_id = r.id
             ORDER BY l.created_at DESC LIMIT ?'
        );
        $stmt->execute([$limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function countByReport(int $reportId): int
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM access_logs WHERE report_id = ?');
        $stmt->execute([$reportId]);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Cria registro em log_acesso_relatorio (abertura da visualização). Retorna o ID.
     */
    public function createLogAcessoRelatorio(int $userId, int $reportId, ?string $ip): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO log_acesso_relatorio (usuario_id, relatorio_id, data_acesso, ip) VALUES (?, ?, NOW(), ?)'
        );
        $stmt->execute([$userId, $reportId, $ip]);
        return (int) $this->db->lastInsertId();
    }

    /**
     * Atualiza tempo e filtros do log (fechamento da visualização). Só atualiza se for do usuário.
     */
    public function updateLogAcessoRelatorio(int $logId, int $userId, ?int $tempoSegundos, $filtrosUtilizados): bool
    {
        $filtrosJson = $filtrosUtilizados !== null ? (is_string($filtrosUtilizados) ? $filtrosUtilizados : json_encode($filtrosUtilizados)) : null;
        $stmt = $this->db->prepare(
            'UPDATE log_acesso_relatorio SET tempo_visualizacao = ?, filtros_utilizados = ? WHERE id = ? AND usuario_id = ?'
        );
        $stmt->execute([$tempoSegundos, $filtrosJson, $logId, $userId]);
        return $stmt->rowCount() > 0;
    }

    /**
     * Busca um log por ID (para validar dono).
     */
    public function findLogAcessoRelatorioById(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM log_acesso_relatorio WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Relatórios mais acessados (por quantidade de acessos).
     * @param array|null $allowedReportIds Se array vazio, retorna []. Se null, não filtra por relatório.
     */
    public function findRelatoriosMaisAcessados(int $limit = 20, ?string $dataInicio = null, ?string $dataFim = null, ?array $allowedReportIds = null): array
    {
        if ($allowedReportIds !== null && count($allowedReportIds) === 0) {
            return [];
        }
        $sql = 'SELECT l.relatorio_id, r.nome AS report_nome, COUNT(*) AS total_acessos,
                SUM(COALESCE(l.tempo_visualizacao, 0)) AS total_segundos
                FROM log_acesso_relatorio l
                JOIN reports r ON r.id = l.relatorio_id
                WHERE 1=1';
        $params = [];
        if ($allowedReportIds !== null) {
            $placeholders = implode(',', array_fill(0, count($allowedReportIds), '?'));
            $sql .= ' AND l.relatorio_id IN (' . $placeholders . ')';
            $params = array_merge($params, $allowedReportIds);
        }
        if ($dataInicio !== null && $dataInicio !== '') {
            $sql .= ' AND l.data_acesso >= ?';
            $params[] = $dataInicio;
        }
        if ($dataFim !== null && $dataFim !== '') {
            $sql .= ' AND l.data_acesso <= ?';
            $params[] = $dataFim;
        }
        $sql .= ' GROUP BY l.relatorio_id, r.nome ORDER BY total_acessos DESC LIMIT ' . (int) $limit;
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Usuários mais ativos (por quantidade de acessos).
     * @param array|null $allowedReportIds Se array vazio, retorna []. Se null, não filtra.
     */
    public function findUsuariosMaisAtivos(int $limit = 20, ?string $dataInicio = null, ?string $dataFim = null, ?array $allowedReportIds = null): array
    {
        if ($allowedReportIds !== null && count($allowedReportIds) === 0) {
            return [];
        }
        $sql = 'SELECT l.usuario_id, u.nome AS user_nome, u.email AS user_email, COUNT(*) AS total_acessos
                FROM log_acesso_relatorio l
                JOIN users u ON u.id = l.usuario_id
                WHERE 1=1';
        $params = [];
        if ($allowedReportIds !== null) {
            $placeholders = implode(',', array_fill(0, count($allowedReportIds), '?'));
            $sql .= ' AND l.relatorio_id IN (' . $placeholders . ')';
            $params = array_merge($params, $allowedReportIds);
        }
        if ($dataInicio !== null && $dataInicio !== '') {
            $sql .= ' AND l.data_acesso >= ?';
            $params[] = $dataInicio;
        }
        if ($dataFim !== null && $dataFim !== '') {
            $sql .= ' AND l.data_acesso <= ?';
            $params[] = $dataFim;
        }
        $sql .= ' GROUP BY l.usuario_id, u.nome, u.email ORDER BY total_acessos DESC LIMIT ' . (int) $limit;
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Acessos por hora do dia (horários de pico).
     * @param array|null $allowedReportIds Se array vazio, retorna []. Se null, não filtra.
     */
    public function findAcessosPorHora(?string $dataInicio = null, ?string $dataFim = null, ?array $allowedReportIds = null): array
    {
        if ($allowedReportIds !== null && count($allowedReportIds) === 0) {
            return [];
        }
        $sql = 'SELECT HOUR(data_acesso) AS hora, COUNT(*) AS total FROM log_acesso_relatorio WHERE 1=1';
        $params = [];
        if ($allowedReportIds !== null) {
            $placeholders = implode(',', array_fill(0, count($allowedReportIds), '?'));
            $sql .= ' AND relatorio_id IN (' . $placeholders . ')';
            $params = array_merge($params, $allowedReportIds);
        }
        if ($dataInicio !== null && $dataInicio !== '') {
            $sql .= ' AND data_acesso >= ?';
            $params[] = $dataInicio;
        }
        if ($dataFim !== null && $dataFim !== '') {
            $sql .= ' AND data_acesso <= ?';
            $params[] = $dataFim;
        }
        $sql .= ' GROUP BY HOUR(data_acesso) ORDER BY hora';
        $stmt = $params === [] ? $this->db->query($sql) : $this->db->prepare($sql);
        if ($params !== []) {
            $stmt->execute($params);
        }
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Total de acessos no período (para KPI).
     * @param array|null $allowedReportIds Se array vazio, retorna 0. Se null, não filtra.
     */
    public function countAcessosNoPeriodo(?string $dataInicio = null, ?string $dataFim = null, ?array $allowedReportIds = null): int
    {
        if ($allowedReportIds !== null && count($allowedReportIds) === 0) {
            return 0;
        }
        $sql = 'SELECT COUNT(*) FROM log_acesso_relatorio WHERE 1=1';
        $params = [];
        if ($allowedReportIds !== null) {
            $placeholders = implode(',', array_fill(0, count($allowedReportIds), '?'));
            $sql .= ' AND relatorio_id IN (' . $placeholders . ')';
            $params = array_merge($params, $allowedReportIds);
        }
        if ($dataInicio !== null && $dataInicio !== '') {
            $sql .= ' AND data_acesso >= ?';
            $params[] = $dataInicio;
        }
        if ($dataFim !== null && $dataFim !== '') {
            $sql .= ' AND data_acesso <= ?';
            $params[] = $dataFim;
        }
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Tempo médio de visualização (em segundos) por relatório ou global.
     * @param array|null $allowedReportIds Se array vazio, retorna null. Se null, considera todos.
     */
    public function tempoMedioVisualizacao(?array $allowedReportIds = null): ?float
    {
        if ($allowedReportIds !== null && count($allowedReportIds) === 0) {
            return null;
        }
        $sql = 'SELECT AVG(COALESCE(tempo_visualizacao, 0)) FROM log_acesso_relatorio WHERE tempo_visualizacao IS NOT NULL AND tempo_visualizacao > 0';
        $params = [];
        if ($allowedReportIds !== null) {
            $placeholders = implode(',', array_fill(0, count($allowedReportIds), '?'));
            $sql .= ' AND relatorio_id IN (' . $placeholders . ')';
            $params = $allowedReportIds;
        }
        $stmt = $params === [] ? $this->db->query($sql) : $this->db->prepare($sql);
        if ($params !== []) {
            $stmt->execute($params);
        }
        $avg = $stmt->fetchColumn();
        return $avg !== false && $avg !== null ? (float) $avg : null;
    }
}
