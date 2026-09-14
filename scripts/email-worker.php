<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../backend/database.php';
require_once __DIR__ . '/../backend/email/outbox.php';

$options = getopt('', ['watch', 'retry:']);
if (isset($options['retry'])) {
    $id = filter_var($options['retry'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($id === false) { fwrite(STDERR, "Indica un ID de participación válido.\n"); exit(1); }
    $pdo = database();
    $reset = $pdo->prepare("UPDATE quiz_email_outbox SET status = 'pending', attempts = 0, next_attempt_at = UTC_TIMESTAMP(), last_error_code = NULL WHERE submission_id = ? AND status = 'failed'");
    $reset->execute([$id]);
    echo 'Notificaciones fallidas reactivadas: ' . $reset->rowCount() . "\n";
}
do {
    try {
        $pdo = database();
        $pending = $pdo->query("SELECT submission_id FROM quiz_email_outbox WHERE attempts < 8 AND ((status IN ('pending','failed') AND next_attempt_at <= UTC_TIMESTAMP()) OR (status = 'sending' AND locked_at < DATE_SUB(UTC_TIMESTAMP(), INTERVAL 5 MINUTE))) ORDER BY next_attempt_at LIMIT 20")->fetchAll(PDO::FETCH_COLUMN);
        foreach ($pending as $id) {
            $state = deliverQuizEmail($pdo, (int)$id);
            echo gmdate(DATE_ATOM) . ' notification=' . $id . ' status=' . $state . "\n";
        }
    } catch (Throwable) {
        fwrite(STDERR, "No se pudo procesar la cola. Verifica MySQL y la migración email-outbox.sql.\n");
        if (!isset($options['watch'])) { exit(1); }
    }
    if (isset($options['watch'])) { sleep(60); }
} while (isset($options['watch']));
