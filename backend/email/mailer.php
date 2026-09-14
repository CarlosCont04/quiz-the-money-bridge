<?php
declare(strict_types=1);

use PHPMailer\PHPMailer\PHPMailer;

const QUIZ_TEST_RECIPIENT = 'aldoemonterm@gmail.com';
const QUIZ_CLIENT_RECIPIENT = 'deyanira.mariscalc@outlook.com';

function quizEmailRecipient(array $settings): string
{
    $phase = $settings['email_phase'];
    $expected = match ($phase) { 'test' => QUIZ_TEST_RECIPIENT, 'production' => QUIZ_CLIENT_RECIPIENT, default => throw new RuntimeException('email_phase_invalid') };
    if ($settings['email_recipient'] !== $expected) {
        throw new RuntimeException('email_recipient_phase_mismatch');
    }
    return $expected;
}

function quizMailer(array $settings, array $job): PHPMailer
{
    require_once __DIR__ . '/../../vendor/autoload.php';
    // Una fila pendiente conserva su destinatario original incluso al cambiar de fase.
    if (!in_array($job['recipient'], [QUIZ_TEST_RECIPIENT, QUIZ_CLIENT_RECIPIENT], true)
        || ($settings['email_phase'] === 'test' && $job['recipient'] !== QUIZ_TEST_RECIPIENT)) {
        throw new RuntimeException('email_recipient_not_allowed');
    }
    $payload = json_decode($job['payload_json'], true, 512, JSON_THROW_ON_ERROR);
    $mail = new PHPMailer(true);
    $mail->CharSet = PHPMailer::CHARSET_UTF8;
    $mail->Encoding = PHPMailer::ENCODING_BASE64;
    $mail->setFrom($settings['email_from'], $settings['email_from_name']);
    $mail->addAddress($job['recipient']);
    $mail->addReplyTo($payload['replyTo'], $payload['replyName']);
    $mail->MessageID = $job['message_id'];
    $mail->XMailer = '';
    $mail->isHTML(true);
    $mail->Subject = $payload['subject'];
    $mail->Body = $payload['html'];
    $mail->AltBody = $payload['text'];
    $mail->addEmbeddedImage(__DIR__ . '/assets/logo.png', 'tmb-logo', 'the-money-bridge.png', PHPMailer::ENCODING_BASE64, 'image/png');
    return $mail;
}

/** Devuelve sent solo si el servidor SMTP acepta el mensaje. */
function sendQuizEmail(array $settings, array $job): string
{
    if ($settings['email_transport'] === 'capture') {
        if ($settings['email_phase'] !== 'test') { throw new RuntimeException('email_capture_requires_test'); }
        $mail = quizMailer($settings, $job);
        $mail->preSend();
        $directory = __DIR__ . '/../../.runtime/mail-capture';
        if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) { throw new RuntimeException('email_capture_directory_failed'); }
        if (file_put_contents($directory . '/' . (int)$job['submission_id'] . '.eml', $mail->getSentMIMEMessage()) === false) { throw new RuntimeException('email_capture_write_failed'); }
        return 'captured';
    }
    if ($settings['email_transport'] !== 'smtp') { throw new RuntimeException('email_transport_invalid'); }
    if (trim((string)$settings['smtp_password']) === '') { throw new RuntimeException('smtp_password_missing'); }
    if (!is_string($settings['smtp_host']) || !preg_match('/^[a-z0-9.-]+$/i', $settings['smtp_host'])) { throw new RuntimeException('smtp_host_invalid'); }
    if (!(($settings['smtp_encryption'] === 'ssl' && (int)$settings['smtp_port'] === 465) || ($settings['smtp_encryption'] === 'tls' && (int)$settings['smtp_port'] === 587))) { throw new RuntimeException('smtp_security_invalid'); }
    $mail = quizMailer($settings, $job);
    $mail->isSMTP();
    $mail->Host = $settings['smtp_host'];
    $mail->Port = (int)$settings['smtp_port'];
    $mail->SMTPAuth = true;
    $mail->Username = $settings['smtp_username'];
    $mail->Password = $settings['smtp_password'];
    $mail->SMTPSecure = $settings['smtp_encryption'] === 'ssl' ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Timeout = 8;
    $mail->Timelimit = 12;
    $mail->SMTPDebug = 0;
    // Nunca desactivar la validación del certificado TLS para solucionar una conexión.
    $mail->SMTPOptions = ['ssl' => ['verify_peer' => true, 'verify_peer_name' => true, 'allow_self_signed' => false]];
    $mail->send();
    return 'sent';
}
