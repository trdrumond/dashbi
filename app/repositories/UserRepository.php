<?php

require_once __DIR__ . '/../config/Database.php';

/**
 * UserRepository - usuários do sistema
 * PHP 7.3+
 */
class UserRepository
{
    /** @var PDO */
    private $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    public function findAll(bool $ativosOnly = true): array
    {
        $sql = 'SELECT id, nome, email, perfil, empresa_id, ativo, created_at FROM users';
        if ($ativosOnly) {
            $sql .= ' WHERE ativo = 1';
        }
        $sql .= ' ORDER BY nome';
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT id, nome, email, perfil, empresa_id, ativo, trocar_senha_proximo_acesso, created_at FROM users WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function findByEmail(string $email): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM users WHERE email = ?');
        $stmt->execute([$email]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function create(array $data): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO users (nome, email, senha_hash, perfil, empresa_id, ativo) VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $data['nome'],
            $data['email'],
            $data['senha_hash'],
            $data['perfil'] ?? 'usuario',
            $data['empresa_id'] ?? null,
            $data['ativo'] ?? 1,
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function update(int $id, array $data): bool
    {
        $fields = [];
        $params = [];
        foreach (['nome', 'email', 'senha_hash', 'perfil', 'empresa_id', 'ativo', 'trocar_senha_proximo_acesso'] as $f) {
            if (array_key_exists($f, $data)) {
                $fields[] = "{$f} = ?";
                $params[] = $data[$f];
            }
        }
        if (empty($fields)) {
            return true;
        }
        $params[] = $id;
        $stmt = $this->db->prepare('UPDATE users SET ' . implode(', ', $fields) . ' WHERE id = ?');
        return $stmt->execute($params);
    }

    public function getGroupIds(int $userId): array
    {
        $stmt = $this->db->prepare('SELECT group_id FROM user_group WHERE user_id = ?');
        $stmt->execute([$userId]);
        return array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'group_id');
    }

    public function getFolderIds(int $userId): array
    {
        $stmt = $this->db->prepare('SELECT folder_id FROM user_folder WHERE user_id = ?');
        $stmt->execute([$userId]);
        return array_map('intval', array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'folder_id'));
    }

    /**
     * Substitui as pastas do usuário (sincronização de permissões é feita no UserService).
     */
    public function setFolderIds(int $userId, array $folderIds): void
    {
        $this->db->prepare('DELETE FROM user_folder WHERE user_id = ?')->execute([$userId]);
        $folderIds = array_unique(array_map('intval', array_filter($folderIds)));
        if (empty($folderIds)) {
            return;
        }
        $stmt = $this->db->prepare('INSERT INTO user_folder (user_id, folder_id) VALUES (?, ?)');
        foreach ($folderIds as $fid) {
            if ($fid > 0) {
                $stmt->execute([$userId, $fid]);
            }
        }
    }

    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare('DELETE FROM users WHERE id = ?');
        return $stmt->execute([$id]);
    }
}
