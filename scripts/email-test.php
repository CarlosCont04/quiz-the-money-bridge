<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../backend/config.php';
require_once __DIR__ . '/../backend/email/report.php';
require_once __DIR__ . '/../backend/email/mailer.php';

$settings = config();
if ($settings['email_phase'] !== 'test' || quizEmailRecipient($settings) !== QUIZ_TEST_RECIPIENT || $settings['email_transport'] !== 'smtp') {
    fwrite(STDERR, "Esta prueba solo permite SMTP en fase test hacia aldoemonterm@gmail.com.\n"); exit(1);
}
$hex = bin2hex(random_bytes(16));
$requestId = substr($hex,0,8) . '-' . substr($hex,8,4) . '-4' . substr($hex,13,3) . '-8' . substr($hex,17,3) . '-' . substr($hex,20,12);
$submission = ['name' => 'Alex Rivera · Participación de prueba', 'email' => 'prospecto@example.invalid', 'requestId' => $requestId, 'answers' => array_fill(1, 12, 'B')];
$report = quizEmailReport($submission, calculateResult($submission['answers']), gmdate('Y-m-d H:i:s'), true);
$job = ['submission_id' => 0, 'recipient' => QUIZ_TEST_RECIPIENT, 'message_id' => '<quiz-test.' . $requestId . '@themoneybridge.com.mx>', 'payload_json' => json_encode($report, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)];
try {
    sendQuizEmail($settings, $job);
    echo "SMTP aceptó la prueba para aldoemonterm@gmail.com. Confirmar recepción y diseño en esa bandeja antes de activar el destinatario del cliente.\n";
} catch (Throwable $error) {
    $code = $error->getMessage() === 'smtp_password_missing' ? 'smtp_password_missing' : 'smtp_delivery_failed';
    fwrite(STDERR, "La prueba no fue aceptada por SMTP ($code). Verifica las credenciales, el host y la conexión. No se muestran datos de autenticación.\n"); exit(1);
}
