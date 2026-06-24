<?php

require_once __DIR__ . '/../repositories/PermissionRepository.php';
require_once __DIR__ . '/../repositories/ReportRepository.php';

/**
 * PermissionService - regra: permissão sempre interna (sem hierarquia).
 * Antes de gerar embed token: canView(userId, reportId).
 * PHP 7.3+
 */
class PermissionService
{
    /** @var PermissionRepository */
    private $permissionRepository;

    /** @var ReportRepository */
    private $reportRepository;

    public function __construct()
    {
        $this->permissionRepository = new PermissionRepository();
        $this->reportRepository = new ReportRepository();
    }

    /**
     * Usuário pode visualizar o relatório? Apenas pela permissão cadastrada (sem hierarquia).
     */
    public function canView(int $userId, int $reportId): bool
    {
        $permission = $this->permissionRepository->findEffectivePermission($userId, $reportId);
        return $permission !== null && !empty($permission['pode_visualizar']);
    }

    /**
     * Retorna a permissão efetiva (para embed: páginas restritas, filtro fixo)
     * @return array|null [pode_visualizar, mostrar_abas, mostrar_filtros]
     */
    public function getEffectivePermission(int $userId, int $reportId): ?array
    {
        return $this->permissionRepository->findEffectivePermission($userId, $reportId);
    }

    /**
     * Lista permissões de um relatório (admin)
     */
    public function listByReport(int $reportId): array
    {
        return $this->permissionRepository->findByReport($reportId);
    }

    /**
     * Cria permissão (usuário ou grupo)
     */
    public function create(array $data): int
    {
        if (empty($data['user_id']) && empty($data['group_id'])) {
            throw new InvalidArgumentException('Informe user_id ou group_id');
        }
        if (!empty($data['user_id']) && !empty($data['group_id'])) {
            throw new InvalidArgumentException('Informe apenas user_id ou group_id');
        }
        return $this->permissionRepository->create($data);
    }

    public function update(int $id, array $data): bool
    {
        return $this->permissionRepository->update($id, $data);
    }

    public function delete(int $id): bool
    {
        return $this->permissionRepository->delete($id);
    }
}
