<?php

require_once __DIR__ . '/../config/Database.php';

/**
 * TvPanelRepository - painéis de TV e itens (playlist)
 * PHP 7.3+
 */
class TvPanelRepository
{
    /** @var PDO */
    private $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    public function findAll(bool $onlyActive = true): array
    {
        $sql = 'SELECT p.*, u.nome AS criado_por_nome
                FROM tv_panels p
                LEFT JOIN users u ON u.id = p.criado_por';
        if ($onlyActive) {
            $sql .= ' WHERE p.ativo = 1';
        }
        $sql .= ' ORDER BY p.nome';
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT p.*, u.nome AS criado_por_nome
             FROM tv_panels p
             LEFT JOIN users u ON u.id = p.criado_por
             WHERE p.id = ?'
        );
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function create(array $data): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO tv_panels (nome, criado_por, ativo, modo_reproducao, mostrar_contador) VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $data['nome'],
            (int) $data['criado_por'],
            isset($data['ativo']) ? (int) $data['ativo'] : 1,
            in_array(($data['modo_reproducao'] ?? 'loop'), ['loop', 'once'], true) ? $data['modo_reproducao'] : 'loop',
            isset($data['mostrar_contador']) ? (int) $data['mostrar_contador'] : 1,
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function update(int $id, array $data): bool
    {
        $allowed = ['nome', 'ativo', 'modo_reproducao', 'mostrar_contador'];
        $set = [];
        $params = [];
        foreach ($allowed as $f) {
            if (array_key_exists($f, $data)) {
                $set[] = "{$f} = ?";
                $params[] = $data[$f];
            }
        }
        if (empty($set)) {
            return true;
        }
        $params[] = $id;
        $stmt = $this->db->prepare('UPDATE tv_panels SET ' . implode(', ', $set) . ' WHERE id = ?');
        return $stmt->execute($params);
    }

    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare('DELETE FROM tv_panels WHERE id = ?');
        return $stmt->execute([$id]);
    }

    public function findItemsByPanel(int $panelId): array
    {
        $stmt = $this->db->prepare(
            'SELECT i.*, r.nome AS report_nome, r.imagem AS report_imagem
             FROM tv_panel_items i
             JOIN reports r ON r.id = i.report_id
             WHERE i.panel_id = ?
             ORDER BY i.ordem, i.id'
        );
        $stmt->execute([$panelId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function replaceItems(int $panelId, array $items): bool
    {
        $this->db->beginTransaction();
        try {
            $del = $this->db->prepare('DELETE FROM tv_panel_items WHERE panel_id = ?');
            $del->execute([$panelId]);

            if (!empty($items)) {
                $ins = $this->db->prepare(
                    'INSERT INTO tv_panel_items (panel_id, report_id, ordem, tempo_segundos, pagina_nome) VALUES (?, ?, ?, ?, ?)'
                );
                $ordem = 1;
                foreach ($items as $item) {
                    $reportId = (int) ($item['report_id'] ?? 0);
                    if ($reportId <= 0) {
                        continue;
                    }
                    $tempo = (int) ($item['tempo_segundos'] ?? 30);
                    if ($tempo < 5) {
                        $tempo = 5;
                    }
                    if ($tempo > 3600) {
                        $tempo = 3600;
                    }
                    $paginaNome = isset($item['pagina_nome']) && $item['pagina_nome'] !== '' ? trim((string) $item['pagina_nome']) : null;
                    $ins->execute([$panelId, $reportId, $ordem, $tempo, $paginaNome]);
                    $ordem++;
                }
            }

            $this->db->commit();
            return true;
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }
}
