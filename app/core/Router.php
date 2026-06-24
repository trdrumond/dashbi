<?php

/**
 * Router - roteamento simples baseado em URI e método
 * PHP 7.3+
 */

class Router
{
    /** @var array */
    private $routes = [];

    /** @var array */
    private $middlewares = [];

    /**
     * GET
     */
    public function get(string $path, $handler): self
    {
        return $this->add('GET', $path, $handler);
    }

    /**
     * POST
     */
    public function post(string $path, $handler): self
    {
        return $this->add('POST', $path, $handler);
    }

    /**
     * PUT
     */
    public function put(string $path, $handler): self
    {
        return $this->add('PUT', $path, $handler);
    }

    /**
     * DELETE
     */
    public function delete(string $path, $handler): self
    {
        return $this->add('DELETE', $path, $handler);
    }

    private function add(string $method, string $path, $handler): self
    {
        $this->routes[] = [
            'method' => $method,
            'path' => $path,
            'pattern' => $this->pathToRegex($path),
            'handler' => $handler,
        ];
        return $this;
    }

    private function pathToRegex(string $path): string
    {
        $pattern = preg_replace('/\{([a-zA-Z_]+)\}/', '(?P<$1>[^/]+)', $path);
        return '#^' . $pattern . '$#';
    }

    /**
     * Resolve a rota para a URI e método atuais
     * @return array [handler, params] ou null
     */
    public function match(string $method, string $uri): ?array
    {
        foreach ($this->routes as $route) {
            if ($route['method'] !== $method) {
                continue;
            }
            if (preg_match($route['pattern'], $uri, $matches)) {
                $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
                return [$route['handler'], $params];
            }
        }
        return null;
    }

    public function getRoutes(): array
    {
        return $this->routes;
    }
}
