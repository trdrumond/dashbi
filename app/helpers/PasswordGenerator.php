<?php

/**
 * Gera senha aleatória conforme política Logos:
 * - Caracteres especiais, maiúsculas, minúsculas, números, 8+ caracteres
 */
class PasswordGenerator
{
    private const ESPECIAIS = '!@#$%&*()-_=+[]{}';
    private const MAIUSCULAS = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    private const MINUSCULAS = 'abcdefghijklmnopqrstuvwxyz';
    private const NUMEROS = '0123456789';

    /**
     * Gera uma senha com pelo menos 1 de cada tipo e tamanho mínimo 8.
     */
    public static function gerar(int $tamanho = 12): string
    {
        $todos = self::ESPECIAIS . self::MAIUSCULAS . self::MINUSCULAS . self::NUMEROS;
        $obrigatorios = [
            self::ESPECIAIS[random_int(0, strlen(self::ESPECIAIS) - 1)],
            self::MAIUSCULAS[random_int(0, strlen(self::MAIUSCULAS) - 1)],
            self::MINUSCULAS[random_int(0, strlen(self::MINUSCULAS) - 1)],
            self::NUMEROS[random_int(0, strlen(self::NUMEROS) - 1)],
        ];
        $resto = $tamanho - count($obrigatorios);
        if ($resto < 0) {
            $resto = 0;
        }
        for ($i = 0; $i < $resto; $i++) {
            $obrigatorios[] = $todos[random_int(0, strlen($todos) - 1)];
        }
        shuffle($obrigatorios);
        return implode('', $obrigatorios);
    }

    /**
     * Valida se a senha atende à política Logos.
     * Retorna array vazio se válida, ou lista de mensagens de erro.
     */
    public static function validar(string $senha): array
    {
        $erros = [];
        if (strlen($senha) < 8) {
            $erros[] = 'A senha deve ter 8 caracteres ou mais.';
        }
        if (!preg_match('/[A-Z]/', $senha)) {
            $erros[] = 'A senha deve ter pelo menos uma letra maiúscula.';
        }
        if (!preg_match('/[a-z]/', $senha)) {
            $erros[] = 'A senha deve ter pelo menos uma letra minúscula.';
        }
        if (!preg_match('/[0-9]/', $senha)) {
            $erros[] = 'A senha deve ter pelo menos um número.';
        }
        if (!preg_match('/[' . preg_quote(self::ESPECIAIS, '/') . ']/', $senha)) {
            $erros[] = 'A senha deve ter pelo menos um caractere especial (!@#$%&*()-_=+[]{}).';
        }
        return $erros;
    }
}
