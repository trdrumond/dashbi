<?php

/**
 * Criptografia para client_secret e dados sensíveis
 * PHP 7.3+
 */

class CryptoHelper
{
    private const CIPHER = 'aes-256-cbc';
    private const KEY_LENGTH = 32;

    /**
     * Gera chave a partir de uma string (Config::JWT_SECRET ou similar)
     */
    public static function deriveKey(string $secret): string
    {
        return hash('sha256', $secret, true);
    }

    /**
     * Criptografa valor (ex: client_secret do tenant)
     */
    public static function encrypt(string $plainText, string $secret): string
    {
        $key = self::deriveKey($secret);
        $iv = random_bytes(16);
        $encrypted = openssl_encrypt($plainText, self::CIPHER, $key, OPENSSL_RAW_DATA, $iv);
        if ($encrypted === false) {
            throw new RuntimeException('Falha ao criptografar');
        }
        return base64_encode($iv . $encrypted);
    }

    /**
     * Descriptografa valor
     */
    public static function decrypt(string $encrypted, string $secret): string
    {
        $key = self::deriveKey($secret);
        $raw = base64_decode($encrypted, true);
        if ($raw === false || strlen($raw) < 16) {
            throw new RuntimeException('Dados criptografados inválidos');
        }
        $iv = substr($raw, 0, 16);
        $payload = substr($raw, 16);
        $decrypted = openssl_decrypt($payload, self::CIPHER, $key, OPENSSL_RAW_DATA, $iv);
        if ($decrypted === false) {
            throw new RuntimeException('Falha ao descriptografar');
        }
        return $decrypted;
    }

    /**
     * Hash de senha (login interno)
     */
    public static function hashPassword(string $password): string
    {
        return password_hash($password, PASSWORD_DEFAULT);
    }

    /**
     * Verifica senha
     */
    public static function verifyPassword(string $password, string $hash): bool
    {
        return password_verify($password, $hash);
    }
}
