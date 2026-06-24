<?php

require_once __DIR__ . '/../config/Database.php';

/**
 * TenantRepository - acesso a dados de tenants (conexão Microsoft)
 * PHP 7.3+
 */
class TenantRepository
{
    /** @var PDO */
    private $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    public function findAll(bool $ativosOnly = true): array
    {
        $sql = 'SELECT id, nome, tenant_id, client_id, workspace_id, ativo, created_at FROM tenants';
        if ($ativosOnly) {
            $sql .= ' WHERE ativo = 1';
        }
        $sql .= ' ORDER BY nome';
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM tenants WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function create(array $data): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO tenants (nome, tenant_id, client_id, client_secret, workspace_id, ativo) VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $data['nome'],
            $data['tenant_id'],
            $data['client_id'],
            $data['client_secret'],
            $data['workspace_id'] ?? null,
            $data['ativo'] ?? 1,
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function update(int $id, array $data): bool
    {
        $fields = [];
        $params = [];
        foreach (['nome', 'tenant_id', 'client_id', 'client_secret', 'workspace_id', 'ativo'] as $f) {
            if (array_key_exists($f, $data)) {
                $fields[] = "{$f} = ?";
                $params[] = $data[$f];
            }
        }
        if (empty($fields)) {
            return true;
        }
        $params[] = $id;
        $stmt = $this->db->prepare('UPDATE tenants SET ' . implode(', ', $fields) . ' WHERE id = ?');
        return $stmt->execute($params);
    }

    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare('DELETE FROM tenants WHERE id = ?');
        return $stmt->execute([$id]);
    }
}
