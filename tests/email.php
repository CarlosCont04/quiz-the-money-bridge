<?php
declare(strict_types=1);
// Aislar las pruebas aunque la instalación local ya utilice al destinatario del cliente.
putenv('EMAIL_PHASE=test');
putenv('EMAIL_RECIPIENT=aldoemonterm@gmail.com');
putenv('EMAIL_TRANSPORT=capture');
require_once __DIR__ . '/../backend/database.php';
require_once __DIR__ . '/../backend/email/outbox.php';

$checks = 0;
function mailCheck(bool $ok, string $label): void {
    global $checks;
    $checks++;
    if (!$ok) { throw new RuntimeException($label); }
}
function mailRejects(callable $operation, string $label): void {
    try { $operation(); } catch (Throwable) { mailCheck(true, $label); return; }
    mailCheck(false, $label);
}
$settings = array_replace(config(), ['email_phase' => 'test', 'email_transport' => 'capture', 'email_recipient' => QUIZ_TEST_RECIPIENT]);
mailCheck(quizEmailRecipient($settings) === QUIZ_TEST_RECIPIENT, 'Test recipient');
mailRejects(fn () => quizEmailRecipient(array_replace($settings, ['email_recipient' => QUIZ_CLIENT_RECIPIENT])), 'Client blocked in test phase');
mailCheck(quizEmailRecipient(array_replace($settings, ['email_phase' => 'production', 'email_recipient' => QUIZ_CLIENT_RECIPIENT])) === QUIZ_CLIENT_RECIPIENT, 'Explicit production routing');

$sample = ['name' => 'Prueba automatizada <etiqueta> & acentos: María', 'email' => 'quiz-test-template@example.invalid', 'requestId' => 'abcdabcd-1234-4234-8234-abcdef123456'];
foreach (['A' => 'red', 'B' => 'yellow', 'C' => 'green'] as $letter => $key) {
    $submission = $sample + ['answers' => array_fill(1, 12, $letter)];
    $result = calculateResult($submission['answers']);
    $report = quizEmailReport($submission, $result, '2026-09-14 18:00:00', true);
    mailCheck($result['key'] === $key && str_contains($report['html'], reportEscape($result['title'])), 'Matching result');
    mailCheck(str_contains($report['html'], '&lt;etiqueta&gt; &amp;') && !str_contains($report['html'], '<etiqueta>'), 'Escaped personal data');
    mailCheck(str_contains($report['text'], $sample['name']) && str_contains($report['text'], $sample['email']), 'Full registration in text');
    mailCheck(str_contains($report['html'], '12:00 (Ciudad de México)'), 'UTC date converted to Mexico City');
    mailCheck(str_starts_with($report['subject'], '[PRUEBA]'), 'Test label');
    mailCheck(strlen($report['html']) < 90000, 'Email HTML below clipping threshold');
    foreach (quizDefinition()['questions'] as $question) {
        mailCheck(str_contains($report['html'], reportEscape($question['title'])), 'Question included');
        mailCheck(str_contains($report['html'], reportEscape($question['options'][['A'=>0,'B'=>1,'C'=>2][$letter]])), 'Selected answer included');
    }
    $job = ['submission_id' => 0, 'recipient' => QUIZ_TEST_RECIPIENT, 'message_id' => '<quiz.test@themoneybridge.com.mx>', 'payload_json' => json_encode($report, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)];
    $mail = quizMailer($settings, $job);
    $mail->preSend();
    $mime = $mail->getSentMIMEMessage();
    mailCheck(str_contains($mime, 'multipart/alternative'), 'HTML and text MIME');
    mailCheck(str_contains($mime, 'Content-ID: <tmb-logo>') && str_contains($mime, 'image/png'), 'Embedded PNG logo');
    mailCheck(count($mail->getToAddresses()) === 1 && $mail->getToAddresses()[0][0] === QUIZ_TEST_RECIPIENT, 'Only intended recipient');
    mailCheck(array_values($mail->getReplyToAddresses())[0][0] === $sample['email'], 'Reply to registrant');
    mailRejects(fn () => sendQuizEmail(array_replace($settings, ['email_transport' => 'smtp', 'smtp_password' => '']), $job), 'Empty SMTP password rejected');
}

$pdo = database();
$pdo->beginTransaction();
try {
    $submission = $sample + ['answers' => array_fill(1, 12, 'B')];
    $result = calculateResult($submission['answers']);
    $insert = $pdo->prepare("INSERT INTO quiz_submissions (request_id,full_name,email,total_score,result_key,result_json,quiz_version,consent_version,consent_at,payload_hash,session_hash) VALUES (?, ?, ?, 12, 'yellow', ?, 'test', 'test', UTC_TIMESTAMP(), ?, ?)");
    $request = 'test-' . bin2hex(random_bytes(12));
    $insert->execute([$request,$sample['name'],$sample['email'],json_encode($result),str_repeat('a',64),str_repeat('b',64)]);
    $id = (int)$pdo->lastInsertId();
    $submission['requestId'] = $request;
    enqueueQuizEmail($pdo, $id, $submission, $result);
    $read = $pdo->prepare('SELECT * FROM quiz_email_outbox WHERE submission_id = ?');
    $read->execute([$id]); $row = $read->fetch();
    mailCheck($row['recipient'] === QUIZ_TEST_RECIPIENT && $row['status'] === 'pending', 'Transactional outbox created');
    $attempts = 0;
    $fail = function () use (&$attempts): string { $attempts++; throw new RuntimeException('Sensitive fake SMTP response must never be logged'); };
    mailCheck(deliverQuizEmail($pdo, $id, $fail) === 'failed', 'SMTP failure retained');
    $read->execute([$id]); $row = $read->fetch();
    mailCheck($row['last_error_code'] === 'smtp_delivery_failed', 'Error details scrubbed');
    mailCheck(deliverQuizEmail($pdo, $id, $fail) === 'skipped' && $attempts === 1, 'Backoff prevents immediate retries');
    $pdo->prepare('UPDATE quiz_email_outbox SET next_attempt_at = UTC_TIMESTAMP() WHERE submission_id = ?')->execute([$id]);
    $send = function () use (&$attempts, $pdo, $id): string { $attempts++; mailCheck(deliverQuizEmail($pdo, $id, fn () => 'sent') === 'skipped', 'Active lease prevents duplicate sender'); return 'sent'; };
    mailCheck(deliverQuizEmail($pdo, $id, $send) === 'sent', 'Retry succeeds');
    mailCheck(deliverQuizEmail($pdo, $id, $send) === 'skipped' && $attempts === 2, 'Delivered email not repeated');
    $read->execute([$id]); $row = $read->fetch();
    mailCheck($row['sent_at'] !== null && $row['last_error_code'] === null && $row['lock_token'] === null, 'Successful status finalized');
    $pdo->prepare("UPDATE quiz_email_outbox SET status = 'sending', locked_at = DATE_SUB(UTC_TIMESTAMP(), INTERVAL 10 MINUTE), lock_token = ? WHERE submission_id = ?")->execute([str_repeat('c',32),$id]);
    mailCheck(deliverQuizEmail($pdo, $id, fn () => 'captured') === 'captured', 'Abandoned lease recovered');
} finally {
    $pdo->rollBack();
}
echo "$checks comprobaciones de correo correctas. Ningún correo fue enviado a Internet por estas pruebas.\n";
