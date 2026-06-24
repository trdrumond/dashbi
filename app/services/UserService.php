<?php

require_once __DIR__ . '/../repositories/UserRepository.php';
require_once __DIR__ . '/../repositories/GroupRepository.php';
require_once __DIR__ . '/../repositories/FolderRepository.php';
require_once __DIR__ . '/../repositories/PermissionRepository.php';
require_once __DIR__ . '/../helpers/CryptoHelper.php';
require_once __DIR__ . '/../helpers/PasswordGenerator.php';
require_once __DIR__ . '/../helpers/Mailer.php';
require_once __DIR__ . '/../config/Config.php';

/**
 * UserService - regras de negócio para usuários
 * PHP 7.3+
 */
class UserService
{
    /** @var UserRepository */
    private $userRepository;

    /** @var GroupRepository */
    private $groupRepository;

    /** @var FolderRepository */
    private $folderRepository;

    /** @var PermissionRepository */
    private $permissionRepository;

    public function __construct()
    {
        $this->userRepository = new UserRepository();
        $this->groupRepository = new GroupRepository();
        $this->folderRepository = new FolderRepository();
        $this->permissionRepository = new PermissionRepository();
    }

    public function findAll(bool $ativosOnly = true): array
    {
        return $this->userRepository->findAll($ativosOnly);
    }

    public function findById(int $id): ?array
    {
        return $this->userRepository->findById($id);
    }

    /**
     * Cria usuário com senha aleatória (política Logos) e envia e-mail com os dados de acesso.
     */
    public function create(array $data): int
    {
        if (empty($data['email']) || empty(trim($data['email']))) {
            throw new InvalidArgumentException('E-mail é obrigatório');
        }
        if ($this->userRepository->findByEmail(trim($data['email']))) {
            throw new InvalidArgumentException('E-mail já cadastrado');
        }
        $senhaPlana = PasswordGenerator::gerar(12);
        $data['senha_hash'] = CryptoHelper::hashPassword($senhaPlana);
        $data['email'] = trim($data['email']);
        $id = $this->userRepository->create($data);
        $this->enviarEmailCadastro(
            $data['email'],
            $data['nome'] ?? $data['email'],
            $senhaPlana
        );
        return $id;
    }

    /**
     * Envia e-mail ao novo usuário com dados de cadastro e login.
     * Mesma configuração SMTP usada no reset de senha (Mailer + Config).
     */
    private function enviarEmailCadastro(string $email, string $nome, string $senha): void
    {
        $url = trim(Config::APP_URL);
        if ($url === '') {
            $url = 'Acesse pelo endereço do sistema informado pelo administrador.';
        } else {
            $url = 'Acesse em: ' . rtrim($url, '/');
        }
        $assunto = 'Cadastro no DashBI - Dados de acesso';
        $corpo = "Olá, " . $nome . ",\n\n"
            . "Você foi cadastrado no sistema DashBI.\n\n"
            . "Dados para login:\n"
            . "E-mail: " . $email . "\n"
            . "Senha: " . $senha . "\n\n"
            . $url . "\n\n"
            . "Recomendamos alterar a senha no primeiro acesso (Meu perfil).\n\n"
            . "Este é um e-mail automático. Não responda.";
        Mailer::send($email, $assunto, $corpo, false);
    }

    /**
     * Reseta a senha do usuário por e-mail: gera nova senha (política), atualiza no banco,
     * define trocar_senha_proximo_acesso=1 e envia a senha por e-mail.
     * Não revela se o e-mail existe ou não (sempre retorna sucesso na mensagem).
     */
    public function resetarSenha(string $email): void
    {
        $user = $this->userRepository->findByEmail(trim($email));
        if (!$user || !$user['ativo']) {
            return;
        }
        $senhaPlana = PasswordGenerator::gerar(12);
        $this->userRepository->update((int) $user['id'], [
            'senha_hash' => CryptoHelper::hashPassword($senhaPlana),
            'trocar_senha_proximo_acesso' => 1,
        ]);
        $this->enviarEmailResetSenha($user['email'], $user['nome'] ?? $user['email'], $senhaPlana);
    }

    /**
     * Envia e-mail com nova senha após reset.
     * Usa a mesma configuração SMTP do cadastro de usuários (Mailer + Config).
     */
    private function enviarEmailResetSenha(string $email, string $nome, string $senha): void
    {
        $url = trim(Config::APP_URL);
        if ($url !== '') {
            $url = 'Acesse em: ' . rtrim($url, '/');
        } else {
            $url = 'Acesse pelo endereço do sistema informado pelo administrador.';
        }
        $assunto = 'DashBI - Nova senha de acesso';
        $corpo = "Olá, " . $nome . ",\n\n"
            . "Foi solicitada uma nova senha para sua conta no DashBI.\n\n"
            . "Nova senha: " . $senha . "\n\n"
            . $url . "\n\n"
            . "Você será obrigado a trocar esta senha no primeiro acesso.\n\n"
            . "Este é um e-mail automático. Não responda.";
        Mailer::send($email, $assunto, $corpo, false);
    }

    public function update(int $id, array $data): bool
    {
        if (isset($data['email'])) {
            $existing = $this->userRepository->findByEmail($data['email']);
            if ($existing && (int) $existing['id'] !== $id) {
                throw new InvalidArgumentException('Email já cadastrado');
            }
        }
        if (!empty($data['senha'])) {
            $erros = PasswordGenerator::validar($data['senha']);
            if (!empty($erros)) {
                throw new InvalidArgumentException('Senha não atende à política: ' . implode(' ', $erros));
            }
            $data['senha_hash'] = CryptoHelper::hashPassword($data['senha']);
            $data['trocar_senha_proximo_acesso'] = 0;
        }
        unset($data['senha']);
        return $this->userRepository->update($id, $data);
    }

    /**
     * Grupos do usuário
     */
    public function getGroupIds(int $userId): array
    {
        return $this->userRepository->getGroupIds($userId);
    }

    /**
     * Atualiza grupos do usuário (lista de group_ids)
     */
    public function setGroups(int $userId, array $groupIds): void
    {
        $current = $this->userRepository->getGroupIds($userId);
        $toAdd = array_diff($groupIds, $current);
        $toRemove = array_diff($current, $groupIds);
        foreach ($toAdd as $gid) {
            $this->groupRepository->addUser($gid, $userId);
        }
        foreach ($toRemove as $gid) {
            $this->groupRepository->removeUser($gid, $userId);
        }
    }

    public function getFolderIds(int $userId): array
    {
        return $this->userRepository->getFolderIds($userId);
    }

    /**
     * Atualiza pastas do usuário e sincroniza permissões: o usuário passa a ter acesso aos relatórios das pastas selecionadas.
     */
    public function setFolders(int $userId, array $folderIds): void
    {
        $this->userRepository->setFolderIds($userId, $folderIds);
        $reportIds = $this->folderRepository->getReportIdsByFolderIds($folderIds);
        foreach ($reportIds as $reportId) {
            if (!$this->permissionRepository->existsUserReport($userId, $reportId)) {
                $this->permissionRepository->create([
                    'report_id' => $reportId,
                    'user_id' => $userId,
                    'pode_visualizar' => 1,
                ]);
            }
        }
        $this->permissionRepository->deleteByUserExceptReportIds($userId, $reportIds);
    }

    public function delete(int $id): bool
    {
        return $this->userRepository->delete($id);
    }
}
