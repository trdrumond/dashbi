<?php

require_once __DIR__ . '/../repositories/TvPanelRepository.php';

/**
 * TvPanelService - regra de negócio do Painel TV
 * PHP 7.3+
 */
class TvPanelService
{
    /** @var TvPanelRepository */
    private $repo;

    public function __construct()
    {
        $this->repo = new TvPanelRepository();
    }

    public function findAll(bool $onlyActive = false): array
    {
        return $this->repo->findAll($onlyActive);
    }

    public function findById(int $id): ?array
    {
        return $this->repo->findById($id);
    }

    public function create(array $data): int
    {
        if (empty($data['nome'])) {
            throw new InvalidArgumentException('Nome do painel é obrigatório.');
        }
        return $this->repo->create($data);
    }

    public function update(int $id, array $data): bool
    {
        return $this->repo->update($id, $data);
    }

    public function delete(int $id): bool
    {
        return $this->repo->delete($id);
    }

    public function getItems(int $panelId): array
    {
        return $this->repo->findItemsByPanel($panelId);
    }

    public function replaceItems(int $panelId, array $items): bool
    {
        return $this->repo->replaceItems($panelId, $items);
    }
}
