<?php

/**
 * Gera hash bcrypt para senha (uso em cadastro ou atualização de usuário)
 * Uso: php scripts/gerar_senha.php "minhasenha"
 * PHP 7.3+
 */

if (php_sapi_name() !== 'cli' || $argc < 2) {
    echo "Uso: php gerar_senha.php \"sua_senha\"\n";
    exit(1);
}

$password = $argv[1];
echo password_hash($password, PASSWORD_DEFAULT) . "\n";
