<?php
declare(strict_types=1);
// Credenciales ficticias: el transporte se inyecta y nunca sale a Internet.
foreach (['SERVICE_ID', 'TEMPLATE_ID', 'PUBLIC_KEY', 'PRIVATE_KEY'] as $key) putenv('EMAILJS_' . $key . '=test_' . strtolower($key));
putenv('EMAIL_LOGO_URL=');
require_once __DIR__ . '/../backend/email/mailer.php';
$checks = 0;
function mailCheck(bool $condition, string $message): void {
    global $checks;
    $checks++;
    if (!$condition) throw new RuntimeException($message);
}
$sample = ['name' => 'José & María "Prueba"', 'email' => 'quiz-test@example.invalid', 'requestId' => '00000000-0000-4000-8000-000000000001'];
foreach (['A' => 'red', 'B' => 'yellow', 'C' => 'green'] as $letter => $key) {
    $submission = $sample + ['answers' => array_fill(1, 12, $letter)];
    $result = calculateResult($submission['answers']);
    $content = quizEmailReport($submission, $result, '2026-10-05 18:00:00', false);
    mailCheck($result['key'] === $key, 'Color esperado');
    mailCheck(str_contains($content['html'], 'José &amp; María &quot;Prueba&quot;'), 'Datos escapados');
    mailCheck(str_contains($content['html'], '05/10/2026 · 12:00 (Ciudad de México)'), 'Fecha en México');
    mailCheck(substr_count($content['html'], 'PREGUNTA ') === 12, 'Doce respuestas');
    mailCheck(str_contains($content['html'], 'cid:logo.png'), 'Logotipo CID de EmailJS');
    foreach (['#19255b', '#64c2c8', 'Un nuevo punto', $sample['requestId'], $result['title']] as $text) mailCheck(str_contains($content['html'], $text), 'Plantilla conservada: ' . $text);
    foreach (quizDefinition()['questions'] as $question) {
        mailCheck(str_contains($content['html'], reportEscape($question['title'])), 'Pregunta completa');
        mailCheck(str_contains($content['html'], reportEscape($question['options'][['A' => 0, 'B' => 1, 'C' => 2][$letter]])), 'Respuesta completa');
    }
    sendQuizEmail($submission, $result, '2026-10-05 18:00:00', false, function (array $payload) use ($sample, $content): array {
        $params = $payload['template_params'];
        mailCheck($params['to_email'] === 'deyanira.mariscalc@outlook.com', 'Destinataria fija');
        mailCheck($params['from_email'] === 'info@themoneybridge.com.mx', 'Remitente fijo');
        mailCheck($params['reply_to'] === $sample['email'], 'Responder al participante');
        mailCheck($params['html_content'] === $content['html'], 'Se envía el HTML completo');
        mailCheck($params['text_content'] === $content['text'], 'Texto alternativo');
        mailCheck(strlen(json_encode($params)) < 50000, 'Límite de variables');
        mailCheck($payload['accessToken'] === 'test_private_key', 'Clave privada solo en transporte');
        return ['status' => 200, 'body' => 'OK'];
    });
}
foreach ([[500, 'detalle privado'], [429, 'límite'], [200, 'respuesta inesperada']] as [$status, $body]) {
    try {
        sendQuizEmail($submission, $result, '2026-10-05 18:00:00', false, fn () => ['status' => $status, 'body' => $body]);
        throw new LogicException('Un fallo no puede anunciar éxito.');
    } catch (EmailJsException $error) {
        mailCheck($error->httpStatus === $status, 'Estado del proveedor');
        mailCheck(!str_contains($error->getMessage(), $body), 'Sin detalles del proveedor');
    }
}
mailCheck(str_starts_with(quizEmailReport($submission, $result, '2026-10-05 18:00:00', true)['subject'], '[PRUEBA]'), 'Muestra identificada');
echo "$checks comprobaciones de correo correctas, sin SQL ni correos reales.\n";
