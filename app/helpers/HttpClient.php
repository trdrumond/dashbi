<?php

/**
 * Cliente HTTP para chamadas à API Microsoft (cURL)
 * PHP 7.3+
 */

class HttpClient
{
    /**
     * POST
     * @param string $url
     * @param array $headers
     * @param array|string $body
     * @return array|null decoded JSON
     */
    public static function post(string $url, array $headers = [], $body = []): ?array
    {
        $ch = curl_init($url);
        $bodyStr = is_array($body) ? http_build_query($body) : $body;
        $headers[] = 'Content-Type: application/x-www-form-urlencoded';

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $bodyStr,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 30,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response === false) {
            return null;
        }

        $decoded = json_decode($response, true);
        return is_array($decoded) ? $decoded : ['_raw' => $response, '_http_code' => $httpCode];
    }

    /**
     * GET
     * @param string $url
     * @param array $headers
     * @return array|null
     */
    public static function get(string $url, array $headers = []): ?array
    {
        $ch = curl_init($url);

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 30,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response === false) {
            return null;
        }

        $decoded = json_decode($response, true);
        if (!is_array($decoded)) {
            return ['_raw' => $response, '_http_code' => $httpCode];
        }
        $decoded['_http_code'] = $httpCode;
        return $decoded;
    }

    /**
     * POST JSON (para Power BI GenerateToken por exemplo)
     */
    public static function postJson(string $url, array $headers = [], array $body = []): ?array
    {
        $ch = curl_init($url);
        $headers[] = 'Content-Type: application/json';

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($body),
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 30,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response === false) {
            return null;
        }

        $decoded = json_decode($response, true);
        $out = is_array($decoded) ? $decoded : ['_raw' => $response];
        $out['_http_code'] = $httpCode;
        return $out;
    }
}
