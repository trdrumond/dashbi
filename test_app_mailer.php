<?php
// Teste usando as classes REAIS da aplicação
// Salve na raiz (htdocs/dashbi) e acesse via navegador.

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Ajuste os requires para a raiz do projeto
require_once __DIR__ . '/app/helpers/Mailer.php';
require_once __DIR__ . '/app/config/Config.php';

echo "<h1>Teste Mailer da Aplicação</h1>";

$to = isset($_GET['to']) ? $_GET['to'] : 'suporte@logos-ma.com.br';

echo "<p>Tentando enviar para: $to</p>";
echo "<p>Usando Config::MAIL_SMTP_HOST = " . Config::MAIL_SMTP_HOST . "</p>";

// Simula o corpo do e-mail de cadastro
$assunto = 'Teste App Mailer - Cadastro';
$corpo = "Olá, Teste,\n\n"
    . "Você foi cadastrado no sistema DashBI.\n\n"
    . "Dados para login:\n"
    . "E-mail: $to\n"
    . "Senha: 123456\n\n"
    . "Teste de quebra de linha.\n"
    . "Fim.";

// Tenta enviar com isHtml = false (como no UserService)
echo "<h3>Tentativa 1: Texto Puro (isHtml = false)</h3>";
$start = microtime(true);
$result = Mailer::send($to, $assunto . " (TXT)", $corpo, false);
$end = microtime(true);

if ($result) {
    echo "<h2 style='color:green'>[SUCESSO] Enviado em " . round($end - $start, 2) . "s</h2>";
} else {
    echo "<h2 style='color:red'>[ERRO] Falha no envio</h2>";
    echo "<p>Verifique se o arquivo app/helpers/Mailer.php está idêntico ao local.</p>";
}

// Tenta enviar com isHtml = true
echo "<h3>Tentativa 2: HTML (isHtml = true)</h3>";
$corpoHtml = nl2br($corpo);
$start = microtime(true);
$result = Mailer::send($to, $assunto . " (HTML)", $corpoHtml, true);
$end = microtime(true);

if ($result) {
    echo "<h2 style='color:green'>[SUCESSO] Enviado em " . round($end - $start, 2) . "s</h2>";
} else {
    echo "<h2 style='color:red'>[ERRO] Falha no envio</h2>";
}
