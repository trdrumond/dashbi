<?php

require_once __DIR__ . '/../config/Database.php';

/**
 * FolderRepository - pastas de relatórios (N:N com reports)
 * PHP 7.3+
 */
class FolderRepository
{
    /** @var PDO */
    private $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    public function findAll(): array
    {
        $stmt = $this->db->query('SELECT * FROM folders ORDER BY ordem IS NULL, ordem ASC, nome ASC');
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Pastas ativas para exibição no dashboard do usuário
     */
    public function findActive(): array
    {
        $stmt = $this->db->query('SELECT * FROM folders WHERE ativo = 1 ORDER BY ordem IS NULL, ordem ASC, nome ASC');
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * IDs de pastas configuradas para o usuário (user_folder) ou para algum grupo do usuário (group_folder).
     */
    public function getFolderIdsForUser(int $userId): array
    {
        $stmt = $this->db->prepare(
            'SELECT DISTINCT folder_id FROM (
                SELECT folder_id FROM user_folder WHERE user_id = ?
                UNION
                SELECT gf.folder_id FROM group_folder gf
                INNER JOIN user_group ug ON gf.group_id = ug.group_id
                WHERE ug.user_id = ?
            ) t'
        );
        $stmt->execute([$userId, $userId]);
        return array_map('intval', array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'folder_id'));
    }

    /**
     * Pastas ativas cujo id está na lista (para dashboard: apenas pastas do usuário/grupos).
     */
    public function findActiveByIds(array $folderIds): array
    {
        $folderIds = array_unique(array_map('intval', array_filter($folderIds)));
        if (empty($folderIds)) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($folderIds), '?'));
        $stmt = $this->db->prepare(
            "SELECT * FROM folders WHERE ativo = 1 AND id IN ($placeholders) ORDER BY ordem IS NULL, ordem ASC, nome ASC"
        );
        $stmt->execute($folderIds);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM folders WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function create(array $data): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO folders (nome, descricao, ativo, ordem) VALUES (?, ?, ?, ?)'
        );
        $stmt->execute([
            $data['nome'],
            $data['descricao'] ?? null,
            $data['ativo'] ?? 1,
            $data['ordem'] ?? null,
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function update(int $id, array $data): bool
    {
        $fields = [];
        $params = [];
        foreach (['nome', 'descricao', 'ativo', 'ordem'] as $f) {
            if (array_key_exists($f, $data)) {
                $fields[] = "{$f} = ?";
                $params[] = $data[$f];
            }
        }
        if (empty($fields)) {
            return true;
        }
        $params[] = $id;
        $stmt = $this->db->prepare('UPDATE folders SET ' . implode(', ', $fields) . ' WHERE id = ?');
        return $stmt->execute($params);
    }

    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare('DELETE FROM folders WHERE id = ?');
        return $stmt->execute([$id]);
    }

    /**
     * IDs dos relatórios que estão nesta pasta
     */
    public function getReportIds(int $folderId): array
    {
        $stmt = $this->db->prepare('SELECT report_id FROM report_folder WHERE folder_id = ?');
        $stmt->execute([$folderId]);
        return array_map('intval', array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'report_id'));
    }

    /**
     * Define os relatórios da pasta (substitui a lista)
     */
    public function setReports(int $folderId, array $reportIds): void
    {
        $this->db->prepare('DELETE FROM report_folder WHERE folder_id = ?')->execute([$folderId]);
        $reportIds = array_unique(array_map('intval', $reportIds));
        if (empty($reportIds)) {
            return;
        }
        $stmt = $this->db->prepare('INSERT INTO report_folder (folder_id, report_id) VALUES (?, ?)');
        foreach ($reportIds as $rid) {
            if ($rid > 0) {
                $stmt->execute([$folderId, $rid]);
            }
        }
    }

    /**
     * IDs de relatórios que estão em pelo menos uma das pastas (união)
     */
    public function getReportIdsByFolderIds(array $folderIds): array
    {
        $folderIds = array_unique(array_map('intval', array_filter($folderIds)));
        if (empty($folderIds)) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($folderIds), '?'));
        $stmt = $this->db->prepare("SELECT DISTINCT report_id FROM report_folder WHERE folder_id IN ($placeholders)");
        $stmt->execute($folderIds);
        return array_map('intval', array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'report_id'));
    }
}
