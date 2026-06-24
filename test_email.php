<?php
// Exibir erros para debug
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Ajuste o caminho conforme necessário
require_once __DIR__ . '/app/helpers/Mailer.php';

// Destinatário padrão (altere aqui ou passe ?to=email@exemplo.com na URL)
$to = 'suporte@logos-ma.com.br'; 

if (isset($_GET['to']) && filter_var($_GET['to'], FILTER_VALIDATE_EMAIL)) {
    $to = $_GET['to'];
}

// Se for rodado via CLI
if (php_sapi_name() === 'cli') {
    if (isset($argv[1])) {
        $to = $argv[1];
    }
    echo "Iniciando teste de envio de e-mail...\n";
    echo "Destinatário: $to\n";
    echo "Configurações:\n";
    echo "  Host: " . Config::MAIL_SMTP_HOST . "\n";
    echo "  Port: " . Config::MAIL_SMTP_PORT . "\n";
    echo "  User: " . Config::MAIL_SMTP_USER . "\n";
    echo "  From: " . Config::MAIL_FROM . "\n";
} else {
    echo "<h1>Teste de Envio de E-mail</h1>";
    echo "<p>Tentando enviar e-mail para: <strong>$to</strong></p>";
    echo "<p>Para alterar o destinatário, use: <code>test_email.php?to=seu@email.com</code></p>";
    echo "<p>Configurações:</p>";
    echo "<ul>";
    echo "<li>Host: " . Config::MAIL_SMTP_HOST . "</li>";
    echo "<li>Port: " . Config::MAIL_SMTP_PORT . "</li>";
    echo "<li>User: " . Config::MAIL_SMTP_USER . "</li>";
    echo "<li>From: " . Config::MAIL_FROM . "</li>";
    echo "</ul>";
}

$subject = "Teste de E-mail DashBI - " . date('Y-m-d H:i:s');
$body = "
    <h2>Teste de Envio</h2>
    <p>Este é um e-mail de teste enviado pelo script de verificação do DashBI.</p>
    <p><strong>Data/Hora:</strong> " . date('d/m/Y H:i:s') . "</p>
    <hr>
    <p>Se você recebeu esta mensagem, as configurações de SMTP estão corretas.</p>
";

echo "\nEnviando...\n";

if (Mailer::send($to, $subject, $body, true)) {
    if (php_sapi_name() === 'cli') {
        echo "[SUCESSO] E-mail enviado com sucesso!\n";
    } else {
        echo "<h2 style='color: green;'>[SUCESSO] E-mail enviado com sucesso!</h2>";
    }
} else {
    if (php_sapi_name() === 'cli') {
        echo "[ERRO] Falha ao enviar e-mail.\n";
        echo "Verifique os logs do PHP ou habilite o debug no PHPMailer.\n";
    } else {
        echo "<h2 style='color: red;'>[ERRO] Falha ao enviar e-mail.</h2>";
        echo "<p>Verifique o arquivo de log de erros do PHP para mais detalhes.</p>";
    }
}
