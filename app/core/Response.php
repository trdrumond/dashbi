<?php

/**
 * Response - padroniza saída HTTP
 * PHP 7.3+
 */

class Response
{
    /** @var int */
    private $statusCode = 200;

    /** @var array */
    private $headers = [];

    /** @var mixed */
    private $body;

    public function status(int $code): self
    {
        $this->statusCode = $code;
        return $this;
    }

    public function header(string $name, string $value): self
    {
        $this->headers[$name] = $value;
        return $this;
    }

    public function json($data, int $status = 200): self
    {
        $this->statusCode = $status;
        $this->header('Content-Type', 'application/json; charset=utf-8');
        $this->body = is_string($data) ? $data : json_encode($data, JSON_UNESCAPED_UNICODE);
        return $this;
    }

    public function send(): void
    {
        if (!headers_sent()) {
            http_response_code($this->statusCode);
            foreach ($this->headers as $name => $value) {
                header("{$name}: {$value}");
            }
            // Segurança
            header('X-Content-Type-Options: nosniff');
            header('X-Frame-Options: SAMEORIGIN');
        }
        echo $this->body;
    }

    public static function jsonSuccess($data = [], string $message = 'OK', int $status = 200): self
    {
        $payload = [
            'success' => true,
            'message' => $message,
            'data' => $data,
        ];
        return (new self())->json($payload, $status);
    }

    public static function jsonError(string $message, int $status = 400, $errors = null): self
    {
        $payload = [
            'success' => false,
            'message' => $message,
            'errors' => $errors,
        ];
        return (new self())->json($payload, $status);
    }
}
