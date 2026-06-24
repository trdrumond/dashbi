<?php

require_once __DIR__ . '/../repositories/GroupRepository.php';
require_once __DIR__ . '/../repositories/FolderRepository.php';
require_once __DIR__ . '/../repositories/PermissionRepository.php';

/**
 * GroupService - regras para grupos
 * PHP 7.3+
 */
class GroupService
{
    /** @var GroupRepository */
    private $groupRepository;

    /** @var FolderRepository */
    private $folderRepository;

    /** @var PermissionRepository */
    private $permissionRepository;

    public function __construct()
    {
        $this->groupRepository = new GroupRepository();
        $this->folderRepository = new FolderRepository();
        $this->permissionRepository = new PermissionRepository();
    }

    public function findAll(): array
    {
        return $this->groupRepository->findAll();
    }

    public function findById(int $id): ?array
    {
        return $this->groupRepository->findById($id);
    }

    public function create(array $data): int
    {
        if (empty($data['nome'])) {
            throw new InvalidArgumentException('Nome do grupo é obrigatório');
        }
        return $this->groupRepository->create($data);
    }

    public function update(int $id, array $data): bool
    {
        return $this->groupRepository->update($id, $data);
    }

    public function addUser(int $groupId, int $userId): bool
    {
        return $this->groupRepository->addUser($groupId, $userId);
    }

    public function removeUser(int $groupId, int $userId): bool
    {
        return $this->groupRepository->removeUser($groupId, $userId);
    }

    public function getUserIds(int $groupId): array
    {
        return $this->groupRepository->getUserIds($groupId);
    }

    public function delete(int $id): bool
    {
        return $this->groupRepository->delete($id);
    }

    public function getFolderIds(int $groupId): array
    {
        return $this->groupRepository->getFolderIds($groupId);
    }

    /**
     * Atualiza pastas do grupo e sincroniza permissões: o grupo passa a ter acesso aos relatórios das pastas selecionadas.
     */
    public function setFolders(int $groupId, array $folderIds): void
    {
        $this->groupRepository->setFolderIds($groupId, $folderIds);
        $reportIds = $this->folderRepository->getReportIdsByFolderIds($folderIds);
        foreach ($reportIds as $reportId) {
            if (!$this->permissionRepository->existsGroupReport($groupId, $reportId)) {
                $this->permissionRepository->create([
                    'report_id' => $reportId,
                    'group_id' => $groupId,
                    'pode_visualizar' => 1,
                ]);
            }
        }
        $this->permissionRepository->deleteByGroupExceptReportIds($groupId, $reportIds);
    }
}
