<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../backend/email/mailer.php';

$hex = bin2hex(random_bytes(16));
$id = substr($hex, 0, 8) . '-' . substr($hex, 8, 4) . '-4' . substr($hex, 13, 3) . '-8' . substr($hex, 17, 3) . '-' . substr($hex, 20, 12);
$submission = ['name' => 'Alex Rivera · Participación de prueba', 'email' => 'prospecto@example.invalid', 'requestId' => $id, 'answers' => array_fill(1, 12, 'B')];
try {
    sendQuizEmail($submission, calculateResult($submission['answers']), gmdate('Y-m-d H:i:s'), true);
    echo "EmailJS aceptó la muestra para la destinataria configurada en la plantilla (Deyanira). Verifica recepción, remitente, logotipo y las 12 respuestas en Outlook.\n";
} catch (Throwable $error) {
    $reason = $error instanceof EmailJsException ? 'emailjs_http_' . $error->httpStatus : 'configuracion_o_conexion_' . $error->getCode();
    fwrite(STDERR, "No se pudo confirmar la prueba ($reason). Ejecuta npm run email:status y consulta docs/emailjs.md. No se muestran claves ni datos del proveedor.\n");
    exit(1);
}
