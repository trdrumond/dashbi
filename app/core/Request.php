<?php

/**
 * Request - encapsula dados da requisição HTTP
 * PHP 7.3+
 */

class Request
{
    /** @var string */
    private $method;

    /** @var string */
    private $uri;

    /** @var array */
    private $query = [];

    /** @var array */
    private $body = [];

    /** @var array */
    private $headers = [];

    /** @var array */
    private $server = [];

    public function __construct()
    {
        $this->method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $this->uri = $this->parseUri();
        $this->query = $_GET ?? [];
        $this->headers = $this->parseHeaders();
        $this->server = $_SERVER;

        if (in_array($this->method, ['POST', 'PUT', 'PATCH'])) {
            $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
            if (strpos($contentType, 'application/json') !== false) {
                $raw = file_get_contents('php://input');
                $this->body = json_decode($raw, true) ?: [];
            } else {
                $this->body = $_POST ?? [];
            }
        }
    }

    private function parseUri()
    {
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        if (($pos = strpos($uri, '?')) !== false) {
            $uri = substr($uri, 0, $pos);
        }
        $path = parse_url($uri, PHP_URL_PATH);
        if ($path === false || $path === '') {
            $path = '/';
        }
        // Remove o caminho base quando o app está em subpasta (ex: /workbi/public)
        $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
        $basePath = $scriptName !== '' ? dirname($scriptName) : '';
        if ($basePath !== '' && $basePath !== '/' && strpos($path, $basePath) === 0) {
            $path = substr($path, strlen($basePath));
            if ($path === '' || $path === false) {
                $path = '/';
            }
        }
        $path = '/' . trim($path, '/');
        return ($path === '/' || $path === '') ? '/' : $path;
    }

    private function parseHeaders()
    {
        $headers = [];
        foreach ($_SERVER as $key => $value) {
            if (strpos($key, 'HTTP_') === 0) {
                $name = str_replace('_', '-', substr($key, 5));
                $headers[strtolower($name)] = $value;
            }
        }
        return $headers;
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    public function getUri(): string
    {
        return $this->uri ?: '/';
    }

    public function get(string $key, $default = null)
    {
        return $this->query[$key] ?? $this->body[$key] ?? $default;
    }

    public function getQuery(): array
    {
        return $this->query;
    }

    public function getBody(): array
    {
        return $this->body;
    }

    public function getHeader(string $name): ?string
    {
        return $this->headers[strtolower($name)] ?? null;
    }

    public function getBearerToken(): ?string
    {
        $auth = $this->getHeader('authorization');
        if ($auth && preg_match('/Bearer\s+(.+)$/i', $auth, $m)) {
            return trim($m[1]);
        }
        return null;
    }

    public function getClientIp(): string
    {
        return $this->server['HTTP_X_FORWARDED_FOR'] ?? $this->server['REMOTE_ADDR'] ?? '0.0.0.0';
    }

    public function isAjax(): bool
    {
        return strtolower($this->getHeader('x-requested-with') ?? '') === 'xmlhttprequest';
    }
}
