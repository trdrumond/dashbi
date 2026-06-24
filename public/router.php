<?php
/**
 * Router para o servidor embutido do PHP (php -S).
 * Uso: php -S localhost:8000 -t public public/router.php
 * Assim todas as requisições (incluindo /api/*) passam pelo index.php.
 */
$uri = $_SERVER['REQUEST_URI'] ?? '/';
if (($pos = strpos($uri, '?')) !== false) {
    $uri = substr($uri, 0, $pos);
}
// Servir arquivos estáticos se existirem
$file = __DIR__ . $uri;
if ($uri !== '/' && $uri !== '' && file_exists($file) && is_file($file)) {
    return false; // deixa o servidor servir o arquivo
}
require __DIR__ . '/index.php';
