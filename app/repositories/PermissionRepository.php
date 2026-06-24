<?php

require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../helpers/JsonHelper.php';

/**
 * PermissionRepository - permissões por relatório (usuário ou grupo)
 * PHP 7.3+
 */
class PermissionRepository
{
    /** @var PDO */
    private $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    public function findByReport(int $reportId): array
    {
        $stmt = $this->db->prepare(
            'SELECT p.*, u.nome as user_nome, u.email as user_email, g.nome as group_nome
             FROM permissions p
             LEFT JOIN users u ON p.user_id = u.id
             LEFT JOIN groups g ON p.group_id = g.id
             WHERE p.report_id = ?
             ORDER BY g.nome, u.nome'
        );
        $stmt->execute([$reportId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM permissions WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }
        $row['pagina_restrita'] = JsonHelper::decodeField($row['pagina_restrita'] ?? null);
        $row['filtro_fixo'] = JsonHelper::decodeField($row['filtro_fixo'] ?? null);
        return $row;
    }

    /**
     * Verifica se usuário pode visualizar relatório (considera usuário direto + grupos)
     * Retorna permissão efetiva (merge por OR entre usuário e grupos) ou null.
     */
    public function findEffectivePermission(int $userId, int $reportId): ?array
    {
        $groupIds = $this->getUserGroupIds($userId);
        $params = [$reportId, $userId];
        $groupCondition = 'user_id = ?';
        if (count($groupIds) > 0) {
            $placeholders = implode(',', array_fill(0, count($groupIds), '?'));
            $groupCondition = "(user_id = ? OR group_id IN ($placeholders))";
            $params = array_merge([$reportId, $userId], $groupIds);
        }

        $stmt = $this->db->prepare(
            "SELECT * FROM permissions WHERE report_id = ?
             AND $groupCondition
             AND (data_inicio IS NULL OR data_inicio <= CURDATE())
             AND (data_fim IS NULL OR data_fim >= CURDATE())"
        );
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (empty($rows)) {
            return null;
        }

        $effective = [
            'pode_visualizar' => 0,
            'mostrar_abas' => 0,
            'mostrar_filtros' => 0,
            'pagina_restrita' => [],
            'filtro_fixo' => [],
        ];
        $allPaginaRestrita = [];
        $hasNoPageRestriction = false;
        $firstFiltroFixo = null;
        foreach ($rows as $row) {
            if (!empty($row['pode_visualizar'])) {
                $effective['pode_visualizar'] = 1;
            }
            if (!empty($row['mostrar_abas'])) {
                $effective['mostrar_abas'] = 1;
            }
            if (!empty($row['mostrar_filtros'])) {
                $effective['mostrar_filtros'] = 1;
            }
            $pr = JsonHelper::decodeField($row['pagina_restrita'] ?? null);
            if (is_array($pr)) {
                if (empty($pr)) {
                    $hasNoPageRestriction = true;
                } else {
                    $allPaginaRestrita = array_unique(array_merge($allPaginaRestrita, $pr));
                }
            }
            if ($firstFiltroFixo === null) {
                $ff = JsonHelper::decodeField($row['filtro_fixo'] ?? null);
                if (is_array($ff) && !empty($ff)) {
                    $firstFiltroFixo = $ff;
                }
            }
        }
        $effective['pagina_restrita'] = $hasNoPageRestriction ? [] : array_values($allPaginaRestrita);
        $effective['filtro_fixo'] = $firstFiltroFixo !== null ? $firstFiltroFixo : [];

        if (empty($effective['pode_visualizar'])) {
            return null;
        }
        return $effective;
    }

    private function getUserGroupIds(int $userId): array
    {
        $stmt = $this->db->prepare('SELECT group_id FROM user_group WHERE user_id = ?');
        $stmt->execute([$userId]);
        return array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'group_id');
    }

    public function create(array $data): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO permissions (report_id, user_id, group_id, pode_visualizar, mostrar_abas, mostrar_filtros, pode_exportar, pode_compartilhar, pagina_restrita, filtro_fixo, data_inicio, data_fim)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $data['report_id'],
            $data['user_id'] ?? null,
            $data['group_id'] ?? null,
            $data['pode_visualizar'] ?? 1,
            $data['mostrar_abas'] ?? 0,
            $data['mostrar_filtros'] ?? 0,
            $data['pode_exportar'] ?? 0,
            $data['pode_compartilhar'] ?? 0,
            isset($data['pagina_restrita']) ? json_encode($data['pagina_restrita']) : null,
            isset($data['filtro_fixo']) ? json_encode($data['filtro_fixo']) : null,
            $data['data_inicio'] ?? null,
            $data['data_fim'] ?? null,
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function update(int $id, array $data): bool
    {
        $fields = [
            'pode_visualizar', 'mostrar_abas', 'mostrar_filtros',
            'pagina_restrita', 'filtro_fixo', 'data_inicio', 'data_fim',
        ];
        $set = [];
        $params = [];
        foreach ($fields as $f) {
            if (array_key_exists($f, $data)) {
                $set[] = "{$f} = ?";
                $params[] = in_array($f, ['pagina_restrita', 'filtro_fixo']) && is_array($data[$f])
                    ? json_encode($data[$f]) : $data[$f];
            }
        }
        if (empty($set)) {
            return true;
        }
        $params[] = $id;
        $stmt = $this->db->prepare('UPDATE permissions SET ' . implode(', ', $set) . ' WHERE id = ?');
        return $stmt->execute($params);
    }

    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare('DELETE FROM permissions WHERE id = ?');
        return $stmt->execute([$id]);
    }

    /**
     * Existe permissão para grupo + relatório?
     */
    public function existsGroupReport(int $groupId, int $reportId): bool
    {
        $stmt = $this->db->prepare('SELECT 1 FROM permissions WHERE group_id = ? AND report_id = ? LIMIT 1');
        $stmt->execute([$groupId, $reportId]);
        return (bool) $stmt->fetchColumn();
    }

    /**
     * Existe permissão para usuário + relatório?
     */
    public function existsUserReport(int $userId, int $reportId): bool
    {
        $stmt = $this->db->prepare('SELECT 1 FROM permissions WHERE user_id = ? AND report_id = ? LIMIT 1');
        $stmt->execute([$userId, $reportId]);
        return (bool) $stmt->fetchColumn();
    }

    /**
     * Remove permissões do grupo para relatórios que não estão na lista (uso: sincronizar por pastas).
     */
    public function deleteByGroupExceptReportIds(int $groupId, array $reportIds): void
    {
        $reportIds = array_map('intval', array_filter($reportIds));
        $stmt = $this->db->prepare('DELETE FROM permissions WHERE group_id = ? AND user_id IS NULL');
        if (empty($reportIds)) {
            $stmt->execute([$groupId]);
            return;
        }
        $placeholders = implode(',', array_fill(0, count($reportIds), '?'));
        $stmt = $this->db->prepare("DELETE FROM permissions WHERE group_id = ? AND user_id IS NULL AND report_id NOT IN ($placeholders)");
        $stmt->execute(array_merge([$groupId], $reportIds));
    }

    /**
     * Remove permissões do usuário para relatórios que não estão na lista (uso: sincronizar por pastas).
     */
    public function deleteByUserExceptReportIds(int $userId, array $reportIds): void
    {
        $reportIds = array_map('intval', array_filter($reportIds));
        $stmt = $this->db->prepare('DELETE FROM permissions WHERE user_id = ? AND group_id IS NULL');
        if (empty($reportIds)) {
            $stmt->execute([$userId]);
            return;
        }
        $placeholders = implode(',', array_fill(0, count($reportIds), '?'));
        $stmt = $this->db->prepare("DELETE FROM permissions WHERE user_id = ? AND group_id IS NULL AND report_id NOT IN ($placeholders)");
        $stmt->execute(array_merge([$userId], $reportIds));
    }
}
