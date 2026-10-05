<?php
declare(strict_types=1);
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/report.php';

const QUIZ_EMAIL_TO = 'deyanira.mariscalc@outlook.com';
const QUIZ_EMAIL_FROM = 'info@themoneybridge.com.mx';

final class EmailJsException extends RuntimeException
{
    public function __construct(public readonly int $httpStatus)
    {
        // No incluir respuestas del proveedor: pueden contener datos o claves.
        parent::__construct('EmailJS no aceptó el envío.', $httpStatus);
    }
}

/** El endpoint siempre usa la URL fija; el transporte inyectable es para pruebas. */
function sendQuizEmail(array $submission, array $result, string $createdAt, bool $isTest = false, ?callable $post = null): void
{
    $settings = config();
    foreach (['emailjs_service_id', 'emailjs_template_id', 'emailjs_public_key', 'emailjs_private_key'] as $key) {
        if (!is_string($settings[$key]) || trim($settings[$key]) === '') {
            throw new RuntimeException('Falta configurar EmailJS.', 1005);
        }
    }
    $content = quizEmailReport($submission, $result, $createdAt, $isTest);
    $params = [
        'to_email' => QUIZ_EMAIL_TO,
        'from_email' => QUIZ_EMAIL_FROM,
        'from_name' => 'The Money Bridge',
        'reply_to' => $content['replyTo'],
        'reply_name' => $content['replyName'],
        'subject' => $content['subject'],
        'html_content' => $content['html'],
        'text_content' => $content['text'],
        'request_id' => $submission['requestId'],
    ];
    // EmailJS limita las variables dinámicas (sin adjuntos) a 50 KB.
    if (strlen(json_encode($params, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)) > 50000) {
        throw new RuntimeException('El reporte excede el límite de EmailJS.', 1008);
    }
    $payload = [
        'service_id' => $settings['emailjs_service_id'],
        'template_id' => $settings['emailjs_template_id'],
        'user_id' => $settings['emailjs_public_key'],
        'accessToken' => $settings['emailjs_private_key'],
        'template_params' => $params,
    ];
    $response = ($post ?? 'emailjsHttpPost')($payload);
    if ($response['status'] !== 200 || trim($response['body']) !== 'OK') {
        throw new EmailJsException($response['status']);
    }
}

function emailjsHttpPost(array $payload): array
{
    if (!extension_loaded('curl')) throw new RuntimeException('Se requiere la extensión cURL de PHP.', 1006);
    $curl = curl_init('https://api.emailjs.com/api/v1.0/email/send');
    curl_setopt_array($curl, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
        CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Accept: text/plain'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ]);
    try {
        $body = curl_exec($curl);
        if ($body === false) {
            error_log('TMB quiz EmailJS transport: curl_errno=' . curl_errno($curl));
            throw new RuntimeException('No se pudo confirmar el envío a EmailJS.', 1007);
        }
        return ['status' => (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE), 'body' => $body];
    } finally {
        curl_close($curl);
    }
}
