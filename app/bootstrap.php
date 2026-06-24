<?php

/**
 * Bootstrap - carrega config e autoload básico
 * PHP 7.3+
 */

error_reporting(E_ALL);
// Em produção, warnings/notices no output quebram respostas JSON da API.
$host = isset($_SERVER['HTTP_HOST']) ? (string) $_SERVER['HTTP_HOST'] : '';
$isLocalHost = stripos($host, 'localhost') !== false || stripos($host, '127.0.0.1') !== false;
$isCli = (PHP_SAPI === 'cli');
ini_set('display_errors', ($isLocalHost || $isCli) ? '1' : '0');
ini_set('log_errors', '1');
if (is_dir(dirname(__DIR__) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'logs')) {
    ini_set('error_log', dirname(__DIR__) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'logs' . DIRECTORY_SEPARATOR . 'php_errors.log');
}

define('ROOT_PATH', dirname(__DIR__) . DIRECTORY_SEPARATOR);

require_once __DIR__ . '/config/Config.php';
require_once __DIR__ . '/config/Database.php';
require_once __DIR__ . '/core/Request.php';
require_once __DIR__ . '/core/Response.php';
require_once __DIR__ . '/core/Controller.php';
require_once __DIR__ . '/core/Router.php';

// Garante diretórios de storage
$dirs = [Config::CACHE_PATH, Config::LOG_PATH];
foreach ($dirs as $d) {
    if (!is_dir($d)) {
        mkdir($d, 0755, true);
    }
}
