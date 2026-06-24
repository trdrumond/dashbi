<?php

/**
 * Utilitários JSON e resposta
 * PHP 7.3+
 */

class JsonHelper
{
    public static function encode($data, int $flags = 0): string
    {
        $flags |= JSON_UNESCAPED_UNICODE;
        $json = json_encode($data, $flags);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException('JSON encode error: ' . json_last_error_msg());
        }
        return $json;
    }

    public static function decode(string $json, bool $assoc = true)
    {
        $data = json_decode($json, $assoc);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException('JSON decode error: ' . json_last_error_msg());
        }
        return $data;
    }

    /**
     * Decodifica campo JSON do banco (pagina_restrita, filtro_fixo)
     */
    public static function decodeField($value): array
    {
        if ($value === null || $value === '') {
            return [];
        }
        if (is_array($value)) {
            return $value;
        }
        $decoded = json_decode($value, true);
        return is_array($decoded) ? $decoded : [];
    }
}
