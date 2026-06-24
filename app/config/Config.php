<?php

/**
 * Configurações gerais do sistema - DashBI 2.0
 * PHP 7.3+
 */

class Config
{
    // Database
    const DB_HOST = 'localhost';
    const DB_NAME = 'web_dashbi_2';
    const DB_USER = 'acesso.sistemas';
    const DB_PASS = 'tDHMpeXVTzQAZsGD';
    const DB_CHARSET = 'utf8mb4';

    // Paths
    const APP_PATH = __DIR__ . '/../';
    const STORAGE_PATH = __DIR__ . '/../../storage/';
    const CACHE_PATH = __DIR__ . '/../../storage/cache/';
    const LOG_PATH = __DIR__ . '/../../storage/logs/';
    const TOKEN_CACHE_PATH = __DIR__ . '/../../storage/cache/';

    // Security
    const JWT_SECRET = 'hNEGy3e5IEidht8gPwSedLr+psymea3Qk3WtPMEbIK0=';
    const CSRF_TOKEN_NAME = 'csrf_token';
    const SESSION_LIFETIME = 3600; // 1 hora

    // Microsoft OAuth2 (compatível com config/azure.php)
    const MS_TOKEN_URL = 'https://login.microsoftonline.com/%s/oauth2/v2.0/token';
    const POWERBI_API_BASE = 'https://api.powerbi.com/v1.0/myorg';
    const POWERBI_RLS_ROLE_DEFAULT = 'RLS_ROLE';

    // E-mail (SMTP - envio de credenciais ao criar usuário)
    const MAIL_FROM = 'naoresponda@logos-ma.com.br';
    const MAIL_SMTP_HOST = 'smtplw.com.br';
    const MAIL_SMTP_PORT = 587;
    const MAIL_SMTP_USER = 'maillogos';
    const MAIL_SMTP_PASS = 'YhXYiPVX4160';
    /** URL base do sistema para o link de login no e-mail (ex.: https://bi.empresa.com ou http://localhost/workbi/public) */
    const APP_URL = 'https://www.logos-ma.com.br/dashbi/';

    // Perfis de usuário (hierarquia)
    const PROFILE_MASTER = 'master';
    const PROFILE_ADMIN = 'admin';
    const PROFILE_GESTOR = 'gestor';
    const PROFILE_SUPERVISOR = 'supervisor';
    const PROFILE_USER = 'usuario';

    public static function get($key, $default = null)
    {
        $constants = [
            'db_host' => self::DB_HOST,
            'db_name' => self::DB_NAME,
            'db_user' => self::DB_USER,
            'db_pass' => self::DB_PASS,
            'cache_path' => self::CACHE_PATH,
            'log_path' => self::LOG_PATH,
        ];
        return $constants[$key] ?? $default;
    }
}
