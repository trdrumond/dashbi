<?php

require_once __DIR__ . '/../config/Database.php';

/**
 * ReportRepository - relatórios Power BI
 * PHP 7.3+
 */
class ReportRepository
{
    /** @var PDO */
    private $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    public function findByWorkspace(int $workspaceId): array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM reports WHERE workspace_id = ? ORDER BY nome'
        );
        $stmt->execute([$workspaceId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT r.*, w.tenant_id, w.powerbi_workspace_id,
             u.nome AS dono_nome, u.email AS dono_email
             FROM reports r
             JOIN workspaces w ON r.workspace_id = w.id
             LEFT JOIN users u ON r.dono_id = u.id
             WHERE r.id = ?'
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row && !empty($row['area_tags'])) {
            $row['area_tags'] = is_string($row['area_tags']) ? json_decode($row['area_tags'], true) : $row['area_tags'];
        }
        return $row ?: null;
    }

    public function findByPowerBiId(string $powerbiReportId, int $workspaceId): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM reports WHERE powerbi_report_id = ? AND workspace_id = ?'
        );
        $stmt->execute([$powerbiReportId, $workspaceId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function create(array $data): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO reports (workspace_id, powerbi_report_id, nome, descricao, dono_id, dono_texto, ultima_atualizacao, indicadores_principais, area_tags, imagem, embed_url, dataset_id, rls_role, ativo) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $areaTags = isset($data['area_tags']) ? (is_string($data['area_tags']) ? $data['area_tags'] : json_encode($data['area_tags'])) : null;
        $stmt->execute([
            $data['workspace_id'],
            $data['powerbi_report_id'],
            $data['nome'],
            $data['descricao'] ?? null,
            $data['dono_id'] ?? null,
            isset($data['dono_texto']) ? (trim((string) $data['dono_texto']) ?: null) : null,
            $data['ultima_atualizacao'] ?? null,
            $data['indicadores_principais'] ?? null,
            $areaTags,
            $data['imagem'] ?? null,
            $data['embed_url'] ?? null,
            $data['dataset_id'] ?? null,
            $data['rls_role'] ?? null,
            $data['ativo'] ?? 1,
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function update(int $id, array $data): bool
    {
        $allowed = ['nome', 'descricao', 'dono_id', 'dono_texto', 'ultima_atualizacao', 'indicadores_principais', 'area_tags', 'imagem', 'embed_url', 'dataset_id', 'rls_role', 'ativo'];
        $fields = [];
        $params = [];
        foreach ($allowed as $f) {
            if (array_key_exists($f, $data)) {
                $fields[] = "{$f} = ?";
                $val = $data[$f];
                if ($f === 'area_tags' && $val !== null) {
                    $val = is_string($val) ? $val : json_encode($val);
                }
                $params[] = $val;
            }
        }
        if (empty($fields)) {
            return true;
        }
        $params[] = $id;
        $stmt = $this->db->prepare('UPDATE reports SET ' . implode(', ', $fields) . ' WHERE id = ?');
        return $stmt->execute($params);
    }

    /**
     * @param array $extra opcional: ['descricao' => ?, 'ultima_atualizacao' => ?, 'dono_id' => ?, 'dono_texto' => ?] (sincronização Power BI)
     */
    public function upsert(int $workspaceId, string $powerbiId, string $nome, ?string $embedUrl = null, ?string $datasetId = null, array $extra = []): int
    {
        $existing = $this->findByPowerBiId($powerbiId, $workspaceId);
        $descricao = isset($extra['descricao']) ? (trim((string) $extra['descricao']) ?: null) : null;
        $ultimaAtualizacao = isset($extra['ultima_atualizacao']) ? (trim((string) $extra['ultima_atualizacao']) ?: null) : null;
        $donoTexto = array_key_exists('dono_texto', $extra) ? (trim((string) ($extra['dono_texto'] ?? '')) ?: null) : null;
        $applyDonoTexto = array_key_exists('dono_texto', $extra);
        $donoId = array_key_exists('dono_id', $extra) ? (isset($extra['dono_id']) ? (int) $extra['dono_id'] : null) : null;
        $applyDono = array_key_exists('dono_id', $extra);
        if ($existing) {
            $data = [
                'nome' => $nome,
                'embed_url' => $embedUrl ?? $existing['embed_url'],
                'dataset_id' => $datasetId ?? $existing['dataset_id'],
            ];
            if ($descricao !== null || array_key_exists('descricao', $extra)) {
                $data['descricao'] = $descricao;
            }
            if ($ultimaAtualizacao !== null || array_key_exists('ultima_atualizacao', $extra)) {
                $data['ultima_atualizacao'] = $ultimaAtualizacao;
            }
            if ($applyDonoTexto) {
                $data['dono_texto'] = $donoTexto;
            }
            if ($applyDono) {
                $data['dono_id'] = $donoId;
            }
            $this->update($existing['id'], $data);
            return (int) $existing['id'];
        }
        $data = [
            'workspace_id' => $workspaceId,
            'powerbi_report_id' => $powerbiId,
            'nome' => $nome,
            'imagem' => null,
            'embed_url' => $embedUrl,
            'dataset_id' => $datasetId,
            'rls_role' => null,
            'ativo' => 1,
        ];
        if ($descricao !== null) {
            $data['descricao'] = $descricao;
        }
        if ($ultimaAtualizacao !== null) {
            $data['ultima_atualizacao'] = $ultimaAtualizacao;
        }
        if ($applyDonoTexto && $donoTexto !== null) {
            $data['dono_texto'] = $donoTexto;
        }
        if ($applyDono && $donoId !== null) {
            $data['dono_id'] = $donoId;
        }
        return $this->create($data);
    }

    /**
     * Lista todos os relatórios ativos (para admin/master), com dono
     */
    public function findAllActive(?int $workspaceId = null): array
    {
        $sql = 'SELECT r.*, w.nome AS workspace_nome, u.nome AS dono_nome, u.email AS dono_email
                FROM reports r
                JOIN workspaces w ON r.workspace_id = w.id
                LEFT JOIN users u ON r.dono_id = u.id
                WHERE r.ativo = 1';
        $params = [];
        if ($workspaceId !== null) {
            $sql .= ' AND r.workspace_id = ?';
            $params[] = $workspaceId;
        }
        $sql .= ' ORDER BY w.nome, r.nome';
        $stmt = $params === [] ? $this->db->query($sql) : $this->db->prepare($sql);
        if ($params !== []) {
            $stmt->execute($params);
        }
        return $this->decodeAreaTagsList($stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    /**
     * Lista relatórios que o usuário pode ver (via permissões), com dono
     */
    public function findAllowedByUser(int $userId, ?int $workspaceId = null): array
    {
        $sql = 'SELECT DISTINCT r.*, w.nome AS workspace_nome, u.nome AS dono_nome, u.email AS dono_email
                FROM reports r
                INNER JOIN workspaces w ON w.id = r.workspace_id
                INNER JOIN permissions p ON p.report_id = r.id
                LEFT JOIN users u ON r.dono_id = u.id
                LEFT JOIN user_group ug ON ug.user_id = ?
                WHERE r.ativo = 1
                AND (p.user_id = ? OR (p.group_id IS NOT NULL AND p.group_id = ug.group_id))
                AND (p.data_inicio IS NULL OR p.data_inicio <= CURDATE())
                AND (p.data_fim IS NULL OR p.data_fim >= CURDATE())
                AND p.pode_visualizar = 1';
        $params = [$userId, $userId];
        if ($workspaceId !== null) {
            $sql .= ' AND r.workspace_id = ?';
            $params[] = $workspaceId;
        }
        $sql .= ' ORDER BY w.nome, r.nome';
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $this->decodeAreaTagsList($stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    /**
     * Quantidade de relatórios que o usuário pode ver (para KPI).
     */
    public function countAllowedByUser(int $userId): int
    {
        $sql = 'SELECT COUNT(DISTINCT r.id) FROM reports r
                INNER JOIN permissions p ON p.report_id = r.id
                LEFT JOIN user_group ug ON ug.user_id = ?
                WHERE r.ativo = 1
                AND (p.user_id = ? OR (p.group_id IS NOT NULL AND p.group_id = ug.group_id))
                AND (p.data_inicio IS NULL OR p.data_inicio <= CURDATE())
                AND (p.data_fim IS NULL OR p.data_fim >= CURDATE())
                AND p.pode_visualizar = 1';
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$userId, $userId]);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Quantidade de relatórios (que o usuário pode ver) atualizados nos últimos $dias dias.
     */
    public function countAtualizadosRecentemente(int $userId, int $dias = 7): int
    {
        $sql = 'SELECT COUNT(DISTINCT r.id) FROM reports r
                INNER JOIN permissions p ON p.report_id = r.id
                LEFT JOIN user_group ug ON ug.user_id = ?
                WHERE r.ativo = 1
                AND (p.user_id = ? OR (p.group_id IS NOT NULL AND p.group_id = ug.group_id))
                AND (p.data_inicio IS NULL OR p.data_inicio <= CURDATE())
                AND (p.data_fim IS NULL OR p.data_fim >= CURDATE())
                AND p.pode_visualizar = 1
                AND r.ultima_atualizacao >= DATE_SUB(CURDATE(), INTERVAL ? DAY)';
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$userId, $userId, $dias]);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Relatórios que o usuário pode ver e que não tiveram acesso nos últimos $dias dias
     * (ou nunca tiveram acesso). Retorna lista com id, nome.
     */
    public function findRelatoriosSemAcessoRecente(int $userId, int $dias = 30): array
    {
        $sql = 'SELECT DISTINCT r.id, r.nome
                FROM reports r
                INNER JOIN permissions p ON p.report_id = r.id
                LEFT JOIN user_group ug ON ug.user_id = ?
                LEFT JOIN (
                    SELECT relatorio_id, MAX(data_acesso) AS ultimo_acesso
                    FROM log_acesso_relatorio
                    GROUP BY relatorio_id
                ) l ON l.relatorio_id = r.id
                WHERE r.ativo = 1
                AND (p.user_id = ? OR (p.group_id IS NOT NULL AND p.group_id = ug.group_id))
                AND (p.data_inicio IS NULL OR p.data_inicio <= CURDATE())
                AND (p.data_fim IS NULL OR p.data_fim >= CURDATE())
                AND p.pode_visualizar = 1
                AND (l.ultimo_acesso IS NULL OR l.ultimo_acesso < DATE_SUB(NOW(), INTERVAL ? DAY))
                ORDER BY r.nome';
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$userId, $userId, $dias]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Lista datasets únicos a partir dos relatórios (com workspace e tenant).
     * Para uso no controle de refresh no admin.
     * @return array [{ dataset_id, workspace_id, tenant_id, powerbi_workspace_id, workspace_nome, report_count, report_names }, ...]
     */
    public function getDatasetsWithWorkspace(): array
    {
        $sql = "SELECT r.dataset_id, r.workspace_id, w.tenant_id, w.powerbi_workspace_id,
                w.nome AS workspace_nome,
                COUNT(r.id) AS report_count,
                GROUP_CONCAT(r.nome ORDER BY r.nome SEPARATOR ', ') AS report_names
                FROM reports r
                JOIN workspaces w ON r.workspace_id = w.id
                WHERE r.dataset_id IS NOT NULL AND TRIM(r.dataset_id) != ''
                GROUP BY r.dataset_id, r.workspace_id, w.tenant_id, w.powerbi_workspace_id, w.nome
                ORDER BY w.nome, r.dataset_id";
        $stmt = $this->db->query($sql);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as &$row) {
            $row['report_count'] = (int) $row['report_count'];
        }
        return $rows;
    }

    /**
     * Retorna o powerbi_workspace_id (group_id) do dataset para um tenant.
     * Usado quando o cliente envia apenas dataset_id e tenant_id.
     */
    public function getGroupIdByDatasetAndTenant(string $datasetId, int $tenantId): ?string
    {
        $stmt = $this->db->prepare(
            "SELECT w.powerbi_workspace_id
             FROM reports r
             JOIN workspaces w ON r.workspace_id = w.id
             WHERE r.dataset_id = ? AND w.tenant_id = ?
             LIMIT 1"
        );
        $stmt->execute([$datasetId, $tenantId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row && !empty($row['powerbi_workspace_id']) ? trim($row['powerbi_workspace_id']) : null;
    }

    /**
     * Decodifica area_tags (JSON) em cada item da lista
     */
    private function decodeAreaTagsList(array $rows): array
    {
        foreach ($rows as &$row) {
            if (!empty($row['area_tags']) && is_string($row['area_tags'])) {
                $row['area_tags'] = json_decode($row['area_tags'], true);
            }
        }
        return $rows;
    }
}
