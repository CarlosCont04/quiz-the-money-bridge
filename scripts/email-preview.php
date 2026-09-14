<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../backend/email/report.php';

$directory = __DIR__ . '/../.runtime/email-preview';
if (!is_dir($directory)) { mkdir($directory, 0700, true); }
foreach (['red' => 'A', 'yellow' => 'B', 'green' => 'C'] as $key => $letter) {
    $submission = ['name' => 'Alex Rivera · Participación de prueba', 'email' => 'prospecto@example.invalid', 'requestId' => '00000000-0000-4000-8000-000000000001', 'answers' => array_fill(1, 12, $letter)];
    $message = quizEmailReport($submission, calculateResult($submission['answers']), gmdate('Y-m-d H:i:s'), true);
    $logo = 'data:image/png;base64,' . base64_encode(file_get_contents(__DIR__ . '/../backend/email/assets/logo.png'));
    file_put_contents($directory . '/' . $key . '.html', str_replace('cid:tmb-logo', $logo, $message['html']));
    file_put_contents($directory . '/' . $key . '.txt', $message['text']);
}
echo "Vistas de los tres semáforos generadas en .runtime/email-preview/. No se enviaron correos.\n";
