<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../../vendor/autoload.php';

/**
 * Função global para envio de e-mails via PHPMailer.
 * Configure as credenciais SMTP conforme seu ambiente.
 */
function sendEmail($to, $subject, $body, $altBody = '') {
    $mail = new PHPMailer(true);

    try {
        // Configurações do Servidor (AJUSTE CONFORME NECESSÁRIO)
        // $mail->SMTPDebug = 2; // Habilite para debug
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com'; // Exemplo: Gmail
        $mail->SMTPAuth   = true;
        $mail->Username   = 'seu-email@gmail.com'; // Seu e-mail
        $mail->Password   = 'sua-senha-ou-app-password'; // Sua senha
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        // Destinatários
        $mail->setFrom('seu-email@gmail.com', 'Sistema de Horários');
        $mail->addAddress($to);

        // Conteúdo
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $body;
        $mail->AltBody = $altBody ?: strip_tags($body);

        // Charset
        $mail->CharSet = 'UTF-8';

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Erro ao enviar e-mail: {$mail->ErrorInfo}");
        return false;
    }
}
