<?php
declare(strict_types=1);
// Solo PHP CLI de pruebas utiliza este router. Nunca se incluye en el ZIP público.
if (PHP_SAPI !== 'cli-server') { http_response_code(404); return true; }
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if ($path !== '/api/submit.php') return false;
require_once __DIR__ . '/../backend/submission.php';
submitQuizRequest(function (array $payload): array {
    $directory = getenv('QUIZ_TEST_DIRECTORY');
    // E2E: simulación en memoria. API: inspección de payloads en carpeta temporal.
    if (!$directory) return ['status' => 200, 'body' => 'OK'];
    file_put_contents($directory . '/requests.jsonl', json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . PHP_EOL, FILE_APPEND | LOCK_EX);
    $mode = trim(file_get_contents($directory . '/mode'));
    return match ($mode) {
        'failure' => ['status' => 500, 'body' => 'provider-private-detail'],
        'rate' => ['status' => 429, 'body' => 'Too many requests'],
        'unexpected' => ['status' => 200, 'body' => 'unexpected-response'],
        'timeout' => throw new RuntimeException('provider-private-detail'),
        default => ['status' => 200, 'body' => 'OK'],
    };
});
