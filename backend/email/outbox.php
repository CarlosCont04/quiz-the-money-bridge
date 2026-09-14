<?php
declare(strict_types=1);
require_once __DIR__ . '/report.php';
require_once __DIR__ . '/mailer.php';
require_once __DIR__ . '/../config.php';

/** Se llama dentro de la misma transacción que guarda el quiz. */
function enqueueQuizEmail(PDO $pdo, int $submissionId, array $submission, array $result): void
{
    $settings = config();
    $recipient = quizEmailRecipient($settings);
    $report = quizEmailReport($submission, $result, gmdate('Y-m-d H:i:s'), $settings['email_phase'] === 'test');
    $insert = $pdo->prepare('INSERT INTO quiz_email_outbox (submission_id, recipient, payload_json, message_id) VALUES (?, ?, ?, ?)');
    $insert->execute([$submissionId, $recipient, json_encode($report, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), '<quiz.' . $submission['requestId'] . '@themoneybridge.com.mx>']);
}

/** Reclamo atómico: dos procesos no envían simultáneamente la misma participación. */
function deliverQuizEmail(PDO $pdo, int $submissionId, ?callable $transport = null): string
{
    $token = bin2hex(random_bytes(16));
    $claim = $pdo->prepare("UPDATE quiz_email_outbox SET status = 'sending', lock_token = ?, locked_at = UTC_TIMESTAMP(), attempts = attempts + 1 WHERE submission_id = ? AND attempts < 8 AND ((status IN ('pending','failed') AND next_attempt_at <= UTC_TIMESTAMP()) OR (status = 'sending' AND locked_at < DATE_SUB(UTC_TIMESTAMP(), INTERVAL 5 MINUTE)))");
    $claim->execute([$token, $submissionId]);
    if ($claim->rowCount() === 0) { return 'skipped'; }
    $read = $pdo->prepare('SELECT * FROM quiz_email_outbox WHERE submission_id = ? AND lock_token = ?');
    $read->execute([$submissionId, $token]);
    $job = $read->fetch();
    try {
        $state = ($transport ?? 'sendQuizEmail')(config(), $job);
        if (!in_array($state, ['sent', 'captured'], true)) { throw new RuntimeException('email_transport_result_invalid'); }
    } catch (Throwable $error) {
        // Guardar solo códigos controlados, nunca mensajes SMTP que puedan contener datos o secretos.
        $allowed = ['smtp_password_missing', 'smtp_host_invalid', 'smtp_security_invalid', 'email_transport_invalid', 'email_recipient_not_allowed', 'email_capture_requires_test'];
        $code = in_array($error->getMessage(), $allowed, true) ? $error->getMessage() : 'smtp_delivery_failed';
        $delay = min(3600, 60 * (2 ** min(6, (int)$job['attempts'] - 1)));
        $retryAt = gmdate('Y-m-d H:i:s', time() + $delay);
        $failed = $pdo->prepare("UPDATE quiz_email_outbox SET status = 'failed', last_error_code = ?, next_attempt_at = ?, lock_token = NULL, locked_at = NULL WHERE submission_id = ? AND lock_token = ?");
        $failed->execute([$code, $retryAt, $submissionId, $token]);
        error_log('TMB quiz notification: submission=' . $submissionId . ' code=' . $code);
        return 'failed';
    }
    // Fuera del catch SMTP: si falla este UPDATE, mantener el lease para no reenviar inmediatamente.
    $done = $pdo->prepare('UPDATE quiz_email_outbox SET status = ?, sent_at = CASE WHEN ? = \'sent\' THEN UTC_TIMESTAMP() ELSE NULL END, last_error_code = NULL, lock_token = NULL, locked_at = NULL WHERE submission_id = ? AND lock_token = ?');
    $done->execute([$state, $state, $submissionId, $token]);
    return $state;
}

function attemptQuizEmail(PDO $pdo, int $submissionId): void
{
    try { deliverQuizEmail($pdo, $submissionId); }
    catch (Throwable) { error_log('TMB quiz notification: submission=' . $submissionId . ' code=outbox_unavailable'); }
}
