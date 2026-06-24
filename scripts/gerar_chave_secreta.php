<?php

/**
 * Gera uma chave segura para JWT_SECRET / criptografia
 * Uso: php scripts/gerar_chave_secreta.php
 * PHP 7.3+
 */

if (php_sapi_name() !== 'cli') {
    die('Execute pelo terminal: php gerar_chave_secreta.php');
}

// 32 bytes = 256 bits, em base64 para copiar e colar no Config.php
$chave = base64_encode(random_bytes(32));

echo "Cole no Config.php em JWT_SECRET:\n\n";
echo "const JWT_SECRET = '" . $chave . "';\n\n";
echo "(Ou use a string entre aspas no .env / configuração de produção)\n";
