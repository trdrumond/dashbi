<?php
// Script de Diagnóstico Completo para Servidor de Produção
// Salve este arquivo na pasta PUBLIC do seu site (ex: public_html ou htdocs/public) e acesse via navegador.

// 1. Configurações de Exibição de Erro
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Tenta aumentar o tempo de execução
set_time_limit(120);

echo "<h1>Diagnóstico de Envio de E-mail (Servidor)</h1>";
echo "<p>Data: " . date('Y-m-d H:i:s') . "</p>";
echo "<hr>";

// 2. Carregar Dependências
echo "<h3>1. Carregando Dependências</h3>";

// Ajuste de caminhos considerando que este arquivo está na pasta 'public'
$paths = [
    __DIR__ . '/../app/libs/PHPMailer/Exception.php',
    __DIR__ . '/../app/libs/PHPMailer/PHPMailer.php',
    __DIR__ . '/../app/libs/PHPMailer/SMTP.php',
    __DIR__ . '/../app/config/Config.php'
];

foreach ($paths as $path) {
    if (file_exists($path)) {
        require_once $path;
        echo "<div style='color:green'>[OK] Encontrado: " . basename($path) . "</div>";
    } else {
        echo "<div style='color:red'>[ERRO] ARQUIVO NÃO ENCONTRADO: $path</div>";
        die("Corrija os caminhos dos arquivos antes de continuar.");
    }
}

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

// 3. Verificações de Ambiente
echo "<h3>2. Ambiente do Servidor</h3>";
echo "<ul>";
echo "<li><strong>PHP Version:</strong> " . phpversion() . "</li>";
echo "<li><strong>Servidor Web:</strong> " . $_SERVER['SERVER_SOFTWARE'] . "</li>";
echo "<li><strong>Sistema Operacional:</strong> " . PHP_OS . "</li>";

$ext_openssl = extension_loaded('openssl');
echo "<li><strong>Extensão OpenSSL:</strong> " . ($ext_openssl ? "<span style='color:green'>Instalada</span>" : "<span style='color:red'>NÃO INSTALADA (Crítico para SMTP seguro)</span>") . "</li>";

$ext_sockets = extension_loaded('sockets');
echo "<li><strong>Extensão Sockets:</strong> " . ($ext_sockets ? "<span style='color:green'>Instalada</span>" : "<span style='color:orange'>Ausente (PHPMailer pode funcionar sem ela, mas é recomendada)</span>") . "</li>";
echo "</ul>";

// 4. Teste de Conexão de Rede (DNS e TCP)
echo "<h3>3. Teste de Conexão de Rede</h3>";
$host = Config::MAIL_SMTP_HOST;
$port = Config::MAIL_SMTP_PORT;

echo "<p>Tentando resolver DNS para: <strong>$host</strong>...</p>";
$ip = gethostbyname($host);
if ($ip != $host) {
    echo "<div style='color:green'>[OK] DNS Resolvido: $ip</div>";
} else {
    echo "<div style='color:red'>[ERRO] Falha na resolução de DNS. Verifique se o servidor tem acesso à internet e DNS configurado.</div>";
}

echo "<p>Tentando conexão TCP na porta $port...</p>";
$fp = @fsockopen($host, $port, $errno, $errstr, 10);
if ($fp) {
    echo "<div style='color:green'>[OK] Conexão TCP estabelecida com sucesso na porta $port.</div>";
    fclose($fp);
} else {
    echo "<div style='color:red'>[ERRO] Falha na conexão TCP: $errstr ($errno).</div>";
    echo "<p>Possíveis causas:</p><ul>";
    echo "<li>Bloqueio de Firewall (entrada ou saída)</li>";
    echo "<li>Bloqueio no provedor de hospedagem (muitos bloqueiam portas SMTP padrão)</li>";
    echo "<li>Servidor SMTP offline</li>";
    echo "</ul>";
}

// 5. Teste de Envio com PHPMailer
echo "<h3>4. Teste de Envio Real (PHPMailer)</h3>";

$to = isset($_GET['to']) ? $_GET['to'] : 'suporte@logos-ma.com.br';
echo "<p>Enviando para: <strong>$to</strong> (Use ?to=email@teste.com para alterar)</p>";

$mail = new PHPMailer(true);
$debugOutput = "";

try {
    // Capturar output de debug
    $mail->Debugoutput = function($str, $level) {
        global $debugOutput;
        $debugOutput .= "[$level] $str<br>";
    };
    $mail->SMTPDebug = 2; // Nível detalhado

    $mail->isSMTP();
    $mail->Host       = Config::MAIL_SMTP_HOST;
    $mail->SMTPAuth   = true;
    $mail->Username   = Config::MAIL_SMTP_USER;
    $mail->Password   = Config::MAIL_SMTP_PASS;
    $mail->Port       = Config::MAIL_SMTP_PORT;
    $mail->CharSet    = 'UTF-8';
    
    // Tenta detectar criptografia
    if ($mail->Port == 587) {
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    } elseif ($mail->Port == 465) {
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
    } else {
        $mail->SMTPAutoTLS = false;
        $mail->SMTPSecure = false;
    }

    // Descomente abaixo se suspeitar de erro de certificado SSL, mas cuidado em produção
    /*
    $mail->SMTPOptions = array(
        'ssl' => array(
            'verify_peer' => false,
            'verify_peer_name' => false,
            'allow_self_signed' => true
        )
    );
    echo "<div style='color:orange'>[AVISO] Verificação de certificado SSL DESABILITADA (apenas para teste)</div>";
    */

    $mail->setFrom(Config::MAIL_FROM, 'DashBI Diagnostico Server');
    $mail->addAddress($to);

    $mail->isHTML(true);
    $mail->Subject = 'Teste Diagnostico Servidor - ' . date('H:i:s');
    $mail->Body    = 'Este é um teste de diagnóstico do servidor.<br>Se você recebeu, o envio está funcionando.';
    $mail->AltBody = 'Este é um teste de diagnóstico do servidor.';

    $mail->send();
    echo "<h2 style='color:green'>[SUCESSO] E-mail enviado!</h2>";
    echo "<p>Verifique sua caixa de entrada (e spam).</p>";

} catch (Exception $e) {
    echo "<h2 style='color:red'>[ERRO] Falha no envio</h2>";
    echo "<strong>Mensagem de Erro:</strong> " . $e->getMessage() . "<br>";
    echo "<strong>Erro PHPMailer:</strong> " . $mail->ErrorInfo . "<br>";
}

echo "<h4>Log Detalhado do SMTP:</h4>";
echo "<div style='background-color:#f5f5f5; padding:10px; border:1px solid #ccc; font-family:monospace; font-size:12px; max-height:400px; overflow-y:scroll;'>";
echo $debugOutput;
echo "</div>";
