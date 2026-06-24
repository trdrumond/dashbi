<?php

require_once __DIR__ . '/../config/Config.php';
require_once __DIR__ . '/../repositories/UserRepository.php';
require_once __DIR__ . '/../helpers/CryptoHelper.php';

/**
 * AuthService - autenticação interna (login) e sessão
 * PHP 7.3+
 */
class AuthService
{
    /** @var UserRepository */
    private $userRepository;

    public function __construct()
    {
        $this->userRepository = new UserRepository();
    }

    /**
     * Login por email e senha. Retorna usuário (sem senha) ou null.
     * @return array|null
     */
    public function login(string $email, string $password): ?array
    {
        $user = $this->userRepository->findByEmail($email);
        if (!$user || !$user['ativo']) {
            return null;
        }
        if (!CryptoHelper::verifyPassword($password, $user['senha_hash'])) {
            return null;
        }
        unset($user['senha_hash']);
        return $user;
    }

    /**
     * Registra sessão do usuário (session ou JWT conforme uso)
     */
    public function setSession(array $user): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user'] = $user;
        $_SESSION['last_activity'] = time();
    }

    /**
     * Retorna usuário da sessão ou null
     * @return array|null
     */
    public function getCurrentUser(): ?array
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (empty($_SESSION['user_id'])) {
            return null;
        }
        if (!empty(Config::SESSION_LIFETIME) && isset($_SESSION['last_activity'])) {
            if (time() - $_SESSION['last_activity'] > Config::SESSION_LIFETIME) {
                $this->logout();
                return null;
            }
        }
        $_SESSION['last_activity'] = time();
        return $_SESSION['user'] ?? null;
    }

    /**
     * ID do usuário logado ou null
     */
    public function getCurrentUserId(): ?int
    {
        $user = $this->getCurrentUser();
        return $user ? (int) $user['id'] : null;
    }

    public function logout(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }
        session_destroy();
    }

    /**
     * Verifica se o perfil do usuário tem nível >= ao exigido
     */
    public function hasProfile(string $requiredProfile): bool
    {
        $user = $this->getCurrentUser();
        if (!$user) {
            return false;
        }
        $order = [
            Config::PROFILE_USER => 0,
            Config::PROFILE_SUPERVISOR => 1,
            Config::PROFILE_GESTOR => 2,
            Config::PROFILE_ADMIN => 3,
            Config::PROFILE_MASTER => 4,
        ];
        $userLevel = $order[$user['perfil'] ?? 'usuario'] ?? 0;
        $requiredLevel = $order[$requiredProfile] ?? 0;
        return $userLevel >= $requiredLevel;
    }
}
