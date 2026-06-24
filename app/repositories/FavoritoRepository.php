<?php

require_once __DIR__ . '/../config/Database.php';

/**
 * FavoritoRepository - favoritos e fixados por usuário
 * PHP 7.3+
 */
class FavoritoRepository
{
    /** @var PDO */
    private $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    /**
     * Lista report_ids favoritados e lista de fixados com ordem para o usuário.
     * @return array ['favoritos' => [report_id, ...], 'fixados' => [['report_id' => id, 'ordem' => n], ...]]
     */
    public function getByUser(int $userId): array
    {
        $stmt = $this->db->prepare(
            'SELECT report_id, fixado, ordem FROM user_relatorio_favorito WHERE user_id = ? ORDER BY fixado DESC, ordem ASC, report_id ASC'
        );
        $stmt->execute([$userId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $favoritos = [];
        $fixados = [];
        foreach ($rows as $row) {
            $rid = (int) $row['report_id'];
            $favoritos[] = $rid;
            if (!empty($row['fixado'])) {
                $fixados[] = ['report_id' => $rid, 'ordem' => (int) $row['ordem']];
            }
        }
        return ['favoritos' => $favoritos, 'fixados' => $fixados];
    }

    public function isFavorito(int $userId, int $reportId): bool
    {
        $stmt = $this->db->prepare('SELECT 1 FROM user_relatorio_favorito WHERE user_id = ? AND report_id = ?');
        $stmt->execute([$userId, $reportId]);
        return (bool) $stmt->fetch();
    }

    /**
     * Adiciona ou remove favorito. Se add=true insere (ou atualiza), se add=false remove.
     */
    public function setFavorito(int $userId, int $reportId, bool $add): void
    {
        if ($add) {
            $stmt = $this->db->prepare(
                'INSERT INTO user_relatorio_favorito (user_id, report_id, fixado, ordem) VALUES (?, ?, 0, 0)
                 ON DUPLICATE KEY UPDATE fixado = fixado'
            );
            $stmt->execute([$userId, $reportId]);
        } else {
            $stmt = $this->db->prepare('DELETE FROM user_relatorio_favorito WHERE user_id = ? AND report_id = ?');
            $stmt->execute([$userId, $reportId]);
        }
    }

    /**
     * Fixa ou desfixa na dashboard. fixado=true com ordem opcional.
     */
    public function setFixado(int $userId, int $reportId, bool $fixado, int $ordem = 0): void
    {
        if ($fixado) {
            $stmt = $this->db->prepare(
                'INSERT INTO user_relatorio_favorito (user_id, report_id, fixado, ordem) VALUES (?, ?, 1, ?)
                 ON DUPLICATE KEY UPDATE fixado = 1, ordem = ?'
            );
            $stmt->execute([$userId, $reportId, $ordem, $ordem]);
        } else {
            $stmt = $this->db->prepare(
                'UPDATE user_relatorio_favorito SET fixado = 0, ordem = 0 WHERE user_id = ? AND report_id = ?'
            );
            $stmt->execute([$userId, $reportId]);
        }
    }

    /**
     * Quantidade de relatórios favoritados pelo usuário (para KPI).
     */
    public function countFavoritosByUser(int $userId): int
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM user_relatorio_favorito WHERE user_id = ?');
        $stmt->execute([$userId]);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Quantidade de relatórios fixados pelo usuário (para KPI).
     */
    public function countFixadosByUser(int $userId): int
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM user_relatorio_favorito WHERE user_id = ? AND fixado = 1');
        $stmt->execute([$userId]);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Retorna a próxima ordem disponível para fixados do usuário.
     */
    public function getProximaOrdemFixado(int $userId): int
    {
        $stmt = $this->db->prepare('SELECT COALESCE(MAX(ordem), 0) + 1 FROM user_relatorio_favorito WHERE user_id = ? AND fixado = 1');
        $stmt->execute([$userId]);
        return (int) $stmt->fetchColumn();
    }
}
