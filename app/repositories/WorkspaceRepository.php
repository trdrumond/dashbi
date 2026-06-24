<?php

require_once __DIR__ . '/../config/Database.php';

/**
 * WorkspaceRepository - workspaces Power BI sincronizados
 * PHP 7.3+
 */
class WorkspaceRepository
{
    /** @var PDO */
    private $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    public function findByTenant(int $tenantId): array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM workspaces WHERE tenant_id = ? ORDER BY nome'
        );
        $stmt->execute([$tenantId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM workspaces WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function findByPowerBiId(string $powerbiWorkspaceId, int $tenantId): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM workspaces WHERE powerbi_workspace_id = ? AND tenant_id = ?'
        );
        $stmt->execute([$powerbiWorkspaceId, $tenantId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function create(array $data): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO workspaces (tenant_id, powerbi_workspace_id, nome, sincronizado_em) VALUES (?, ?, ?, NOW())'
        );
        $stmt->execute([
            $data['tenant_id'],
            $data['powerbi_workspace_id'],
            $data['nome'],
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function updateSincronizado(int $id): void
    {
        $stmt = $this->db->prepare('UPDATE workspaces SET sincronizado_em = NOW() WHERE id = ?');
        $stmt->execute([$id]);
    }

    public function upsert(int $tenantId, string $powerbiId, string $nome): int
    {
        $existing = $this->findByPowerBiId($powerbiId, $tenantId);
        if ($existing) {
            $stmt = $this->db->prepare('UPDATE workspaces SET nome = ?, sincronizado_em = NOW() WHERE id = ?');
            $stmt->execute([$nome, $existing['id']]);
            return (int) $existing['id'];
        }
        return $this->create([
            'tenant_id' => $tenantId,
            'powerbi_workspace_id' => $powerbiId,
            'nome' => $nome,
        ]);
    }
}
