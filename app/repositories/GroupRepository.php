<?php

require_once __DIR__ . '/../config/Database.php';

/**
 * GroupRepository - grupos de usuários
 * PHP 7.3+
 */
class GroupRepository
{
    /** @var PDO */
    private $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    public function findAll(): array
    {
        $stmt = $this->db->query('SELECT * FROM groups ORDER BY nome');
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM groups WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function create(array $data): int
    {
        $stmt = $this->db->prepare('INSERT INTO groups (nome, descricao) VALUES (?, ?)');
        $stmt->execute([$data['nome'], $data['descricao'] ?? '']);
        return (int) $this->db->lastInsertId();
    }

    public function update(int $id, array $data): bool
    {
        $stmt = $this->db->prepare('UPDATE groups SET nome = ?, descricao = ? WHERE id = ?');
        return $stmt->execute([$data['nome'], $data['descricao'] ?? '', $id]);
    }

    public function addUser(int $groupId, int $userId): bool
    {
        $stmt = $this->db->prepare('INSERT IGNORE INTO user_group (user_id, group_id) VALUES (?, ?)');
        return $stmt->execute([$userId, $groupId]);
    }

    public function removeUser(int $groupId, int $userId): bool
    {
        $stmt = $this->db->prepare('DELETE FROM user_group WHERE user_id = ? AND group_id = ?');
        return $stmt->execute([$userId, $groupId]);
    }

    public function getUserIds(int $groupId): array
    {
        $stmt = $this->db->prepare('SELECT user_id FROM user_group WHERE group_id = ?');
        $stmt->execute([$groupId]);
        return array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'user_id');
    }

    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare('DELETE FROM groups WHERE id = ?');
        return $stmt->execute([$id]);
    }

    public function getFolderIds(int $groupId): array
    {
        $stmt = $this->db->prepare('SELECT folder_id FROM group_folder WHERE group_id = ?');
        $stmt->execute([$groupId]);
        return array_map('intval', array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'folder_id'));
    }

    /**
     * Substitui as pastas do grupo e sincroniza permissões (chamado por GroupService).
     */
    public function setFolderIds(int $groupId, array $folderIds): void
    {
        $this->db->prepare('DELETE FROM group_folder WHERE group_id = ?')->execute([$groupId]);
        $folderIds = array_unique(array_map('intval', array_filter($folderIds)));
        if (empty($folderIds)) {
            return;
        }
        $stmt = $this->db->prepare('INSERT INTO group_folder (group_id, folder_id) VALUES (?, ?)');
        foreach ($folderIds as $fid) {
            if ($fid > 0) {
                $stmt->execute([$groupId, $fid]);
            }
        }
    }
}
