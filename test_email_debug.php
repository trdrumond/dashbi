<?php
// Exibir erros para debug
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Configurações (Copiado de Config.php para isolamento)
class TestConfig {
    const MAIL_FROM = 'naoresponda@logos-ma.com.br';
    const MAIL_SMTP_HOST = 'smtplw.com.br';
    const MAIL_SMTP_PORT = 587;
    const MAIL_SMTP_USER = 'maillogos';
    const MAIL_SMTP_PASS = 'YhXYiPVX4160';
}

require_once __DIR__ . '/app/libs/PHPMailer/Exception.php';
require_once __DIR__ . '/app/libs/PHPMailer/PHPMailer.php';
require_once __DIR__ . '/app/libs/PHPMailer/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

// Destinatário padrão
$to = 'suporte@logos-ma.com.br'; 

if (isset($_GET['to']) && filter_var($_GET['to'], FILTER_VALIDATE_EMAIL)) {
    $to = $_GET['to'];
}

// Se for rodado via CLI
if (php_sapi_name() === 'cli') {
    if (isset($argv[1])) {
        $to = $argv[1];
    }
}

echo "<h1>Diagnostico de Envio de E-mail</h1>";
echo "<pre>";

// 1. Verificações de Ambiente
echo "<h2>1. Ambiente</h2>";
echo "PHP Version: " . phpversion() . "\n";
echo "OpenSSL Extensão: " . (extension_loaded('openssl') ? "OK" : "FALTANDO!") . "\n";
echo "Sockets Extensão: " . (extension_loaded('sockets') ? "OK" : "Ausente (opcional)") . "\n";

// 2. Teste de Conexão TCP
echo "\n<h2>2. Teste de Conexão TCP (fsockopen)</h2>";
$host = TestConfig::MAIL_SMTP_HOST;
$port = TestConfig::MAIL_SMTP_PORT;
echo "Tentando conectar em $host:$port ...\n";

$connection = @fsockopen($host, $port, $errno, $errstr, 10);
if (is_resource($connection)) {
    echo "Conexão TCP OK!\n";
    fclose($connection);
} else {
    echo "ERRO DE CONEXÃO TCP: $errstr ($errno)\n";
    echo "Isso indica bloqueio de firewall ou DNS.\n";
}

// 3. Teste PHPMailer com Debug
echo "\n<h2>3. Teste PHPMailer (SMTP Debug)</h2>";

$mail = new PHPMailer(true);

try {
    // Configurações de Servidor
    $mail->SMTPDebug = SMTP::DEBUG_CONNECTION; // Nível detalhado
    $mail->Debugoutput = function($str, $level) {
        echo "DEBUG: $str\n";
    };

    $mail->isSMTP();
    $mail->Host       = TestConfig::MAIL_SMTP_HOST;
    $mail->SMTPAuth   = true;
    $mail->Username   = TestConfig::MAIL_SMTP_USER;
    $mail->Password   = TestConfig::MAIL_SMTP_PASS;
    $mail->Port       = TestConfig::MAIL_SMTP_PORT;
    $mail->CharSet    = 'UTF-8';
    
    // Auto TLS
    $mail->SMTPAutoTLS = true; 
    
    // Desabilitar verificação de certificado (apenas para teste/diagnóstico)
    $mail->SMTPOptions = array(
        'ssl' => array(
            'verify_peer' => false,
            'verify_peer_name' => false,
            'allow_self_signed' => true
        )
    );
    echo "AVISO: Verificação de certificado SSL desabilitada para este teste.\n";

    if ($mail->Port == 587) {
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    } elseif ($mail->Port == 465) {
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
    }

    // Remetente e Destinatário
    $mail->setFrom(TestConfig::MAIL_FROM, 'DashBI Diagnostico');
    $mail->addAddress($to);

    // Conteúdo
    $mail->isHTML(true);
    $mail->Subject = "Teste Diagnostico DashBI - " . date('H:i:s');
    $mail->Body    = "Teste de envio com debug detalhado.<br>Hora: " . date('H:i:s');
    $mail->AltBody = "Teste de envio com debug detalhado.";

    echo "\nIniciando envio...\n";
    $mail->send();
    echo "\n[SUCESSO] E-mail aceito pelo servidor SMTP.\n";
    
} catch (Exception $e) {
    echo "\n[ERRO FATAL] O e-mail não pôde ser enviado.\n";
    echo "Erro PHPMailer: {$mail->ErrorInfo}\n";
}

echo "</pre>";
