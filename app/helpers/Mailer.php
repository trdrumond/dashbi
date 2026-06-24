<?php

require_once __DIR__ . '/../config/Config.php';

// Carregar PHPMailer manualmente (sem composer)
require_once __DIR__ . '/../libs/PHPMailer/Exception.php';
require_once __DIR__ . '/../libs/PHPMailer/PHPMailer.php';
require_once __DIR__ . '/../libs/PHPMailer/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

/**
 * Envio de e-mail usando PHPMailer.
 * Substitui implementação antiga de socket.
 * PHP 7.3+
 */
class Mailer
{
    /**
     * Envia um e-mail via SMTP.
     * @param string $to Endereço do destinatário
     * @param string $subject Assunto
     * @param string $body Corpo (texto ou HTML)
     * @param bool $isHtml Define se o corpo é HTML
     * @return bool true se enviado com sucesso
     */
    public static function send(string $to, string $subject, string $body, bool $isHtml = false): bool
    {
        $mail = new PHPMailer(true);

        try {
            // Configurações de Servidor
            $mail->isSMTP();
            $mail->Host       = Config::MAIL_SMTP_HOST;
            $mail->SMTPAuth   = true;
            $mail->Username   = Config::MAIL_SMTP_USER;
            $mail->Password   = Config::MAIL_SMTP_PASS;
            $mail->Port       = (int) Config::MAIL_SMTP_PORT;
            $mail->CharSet    = 'UTF-8';

            // Segurança (SMTPSecure)
            // Se porta 587, geralmente é TLS. Se 465, SSL.
            // PHPMailer geralmente detecta, mas podemos forçar se necessário.
            if ($mail->Port == 587) {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            } elseif ($mail->Port == 465) {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            } else {
                $mail->SMTPSecure = false;
                $mail->SMTPAutoTLS = false;
            }

            // Remetente e Destinatário
            $mail->setFrom(Config::MAIL_FROM, 'DashBI System');
            $mail->addAddress($to);

            // Conteúdo
            $mail->isHTML($isHtml);
            $mail->Subject = $subject;
            $mail->Body    = $body;
            
            if ($isHtml) {
                // Versão texto simples para clientes sem suporte HTML
                $mail->AltBody = strip_tags(str_replace(['<br>', '<p>'], "\n", $body));
            }

            $mail->send();
            return true;
        } catch (Exception $e) {
            if (function_exists('error_log')) {
                error_log("Mailer Error: {$mail->ErrorInfo}");
            }
            return false;
        }
    }
}
