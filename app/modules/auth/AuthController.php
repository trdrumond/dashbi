<?php

require_once __DIR__ . '/../../core/Controller.php';
require_once __DIR__ . '/../../services/AuthService.php';
require_once __DIR__ . '/../../services/UserService.php';
require_once __DIR__ . '/../../helpers/PasswordGenerator.php';
require_once __DIR__ . '/../../config/Config.php';

/**
 * AuthController - login e sessão
 */
class AuthController extends Controller
{
    /** @var AuthService */
    private $authService;

    /** @var UserService */
    private $userService;

    public function __construct()
    {
        $this->authService = new AuthService();
        $this->userService = new UserService();
    }

    /**
     * POST /api/auth/login
     */
    public function login(): Response
    {
        $body = $this->request->getBody();
        $email = $this->request->get('email') ?? (isset($body['email']) ? trim((string) $body['email']) : null);
        $password = $this->request->get('senha') ?? $this->request->get('password') ?? (isset($body['senha']) ? $body['senha'] : (isset($body['password']) ? $body['password'] : null));

        if (empty($email) || $password === null || $password === '') {
            return $this->error('Email e senha são obrigatórios', 400);
        }

        $user = $this->authService->login($email, $password);
        if (!$user) {
            return $this->error('Credenciais inválidas', 401);
        }

        $this->authService->setSession($user);
        $trocarSenhaObrigatorio = !empty($user['trocar_senha_proximo_acesso']);
        return $this->success([
            'user' => $user,
            'trocarSenhaObrigatorio' => $trocarSenhaObrigatorio,
        ], 'Login realizado com sucesso');
    }

    /**
     * POST /api/auth/esqueci-senha - solicita reset de senha por e-mail.
     * Resposta genérica para não revelar se o e-mail existe.
     */
    public function esqueciSenha(): Response
    {
        $data = $this->request->getBody();
        $email = isset($data['email']) ? trim((string) $data['email']) : '';
        if ($email === '') {
            return $this->error('Informe o e-mail', 400);
        }
        $this->userService->resetarSenha($email);
        return $this->success(
            [],
            'Se o e-mail estiver cadastrado, você receberá uma nova senha em instantes. Verifique sua caixa de entrada e o spam.'
        );
    }

    /**
     * POST /api/auth/logout
     */
    public function logout(): Response
    {
        $this->authService->logout();
        return $this->success([], 'Logout realizado');
    }

    /**
     * GET /api/auth/me - usuário logado
     */
    public function me(): Response
    {
        $user = $this->authService->getCurrentUser();
        if (!$user) {
            return $this->error('Não autenticado', 401);
        }
        return $this->success($user);
    }

    /**
     * PUT /api/auth/profile - atualizar perfil do usuário logado (nome e/ou senha; email não é alterável)
     */
    public function updateProfile(): Response
    {
        $user = $this->authService->getCurrentUser();
        if (!$user) {
            return $this->error('Não autenticado', 401);
        }
        $data = $this->request->getBody();
        $allowed = [];
        if (isset($data['nome'])) {
            $allowed['nome'] = trim($data['nome']);
        }
        if (!empty($data['senha'])) {
            if (isset($data['confirmar_senha']) && $data['senha'] !== $data['confirmar_senha']) {
                return $this->error('Senha e confirmação não conferem', 400);
            }
            $errosSenha = PasswordGenerator::validar($data['senha']);
            if (!empty($errosSenha)) {
                return $this->error('Senha não atende à política: ' . implode(' ', $errosSenha), 400);
            }
            $allowed['senha'] = $data['senha'];
        }
        if (empty($allowed)) {
            return $this->error('Nenhum dado para atualizar', 400);
        }
        try {
            $this->userService->update((int) $user['id'], $allowed);
            $updated = $this->userService->findById((int) $user['id']);
            unset($updated['senha_hash']);
            $this->authService->setSession($updated);
            return $this->success($updated, 'Perfil atualizado');
        } catch (InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 400);
        }
    }
}
