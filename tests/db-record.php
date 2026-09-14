<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../backend/database.php';
$mode = $argv[1] ?? '';
$requestId = $argv[2] ?? '';
if (!preg_match('/^[a-f0-9-]{36}$/', $requestId)) { exit(1); }
$pdo = database();
// Nunca tocar registros que no tengan el correo reservado de estas pruebas.
if ($mode === 'delete') {
    $find = $pdo->prepare("SELECT id FROM quiz_submissions WHERE request_id = ? AND email LIKE 'quiz-test-%@example.invalid'");
    $find->execute([$requestId]);
    $id = $find->fetchColumn();
    $capture = __DIR__ . '/../.runtime/mail-capture/' . (int)$id . '.eml';
    if ($id && is_file($capture)) { unlink($capture); }
    $query = $pdo->prepare("DELETE FROM quiz_submissions WHERE request_id = ? AND email LIKE 'quiz-test-%@example.invalid'");
    $query->execute([$requestId]);
    exit;
}
if ($mode === 'notification') {
    $find = $pdo->prepare("SELECT o.status, o.attempts, o.recipient, o.payload_json FROM quiz_email_outbox o JOIN quiz_submissions s ON s.id = o.submission_id WHERE s.request_id = ? AND s.email LIKE 'quiz-test-%@example.invalid'");
    $find->execute([$requestId]);
    echo json_encode($find->fetch() ?: null, JSON_THROW_ON_ERROR);
    exit;
}
$query = $pdo->prepare("SELECT s.total_score, s.result_key, s.consent_version, COUNT(a.question_id) answer_count, SUM(a.points) answer_score FROM quiz_submissions s JOIN quiz_answers a ON a.submission_id = s.id WHERE s.request_id = ? AND s.email LIKE 'quiz-test-%@example.invalid' GROUP BY s.id");
$query->execute([$requestId]);
echo json_encode($query->fetch() ?: null, JSON_THROW_ON_ERROR);
