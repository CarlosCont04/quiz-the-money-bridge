<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/backend/http.php';
require_once dirname(__DIR__, 2) . '/backend/quiz.php';
require_once dirname(__DIR__, 2) . '/backend/database.php';

requireMethod('POST');
if (strtolower(trim(explode(';', $_SERVER['CONTENT_TYPE'] ?? '')[0])) !== 'application/json') {
    jsonResponse(['message' => 'El formato de envío debe ser JSON.'], 415);
}
if ((int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 16384) {
    jsonResponse(['message' => 'El envío excede el tamaño permitido.'], 413);
}
startQuizSession();
if (!hash_equals($_SESSION['csrf'], $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '')) {
    jsonResponse(['message' => 'La sesión expiró. Intenta enviar nuevamente.'], 403);
}
$now = time();
$_SESSION['attempts'] = array_values(array_filter($_SESSION['attempts'] ?? [], fn ($timestamp) => $timestamp > $now - 900));
if (count($_SESSION['attempts']) >= 30) {
    header('Retry-After: 900');
    jsonResponse(['message' => 'Has realizado varios intentos. Espera 15 minutos antes de volver a enviar.'], 429);
}
$_SESSION['attempts'][] = $now;
$sessionHash = hash('sha256', session_id());
session_write_close();

try {
    $raw = file_get_contents('php://input', false, null, 0, 16385);
    if (strlen($raw) > 16384) {
        jsonResponse(['message' => 'El envío excede el tamaño permitido.'], 413);
    }
    $input = json_decode($raw, true, 32, JSON_THROW_ON_ERROR);
    if (!is_array($input) || array_is_list($input)) {
        throw new InvalidArgumentException('El envío no es válido.');
    }
    $submission = validateSubmission($input);
} catch (JsonException | InvalidArgumentException $error) {
    jsonResponse(['message' => $error instanceof JsonException ? 'El envío no contiene un JSON válido.' : $error->getMessage()], 422);
}

// La puntuación enviada por un cliente nunca se utiliza.
$result = calculateResult($submission['answers']);
$payloadHash = hash('sha256', json_encode($submission, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
$pdo = database();
$findExisting = function () use ($pdo, $submission, $sessionHash, $payloadHash): ?array {
    $query = $pdo->prepare('SELECT payload_hash, session_hash, result_json FROM quiz_submissions WHERE request_id = ?');
    $query->execute([$submission['requestId']]);
    $existing = $query->fetch();
    if (!$existing) {
        return null;
    }
    if (!hash_equals($existing['payload_hash'], $payloadHash) || !hash_equals($existing['session_hash'], $sessionHash)) {
        jsonResponse(['message' => 'Este envío ya fue registrado con otros datos. Vuelve a intentarlo con un nuevo quiz.'], 409);
    }
    return json_decode($existing['result_json'], true, 512, JSON_THROW_ON_ERROR);
};
if ($existing = $findExisting()) {
    jsonResponse(['message' => 'Gracias por responder el quiz', 'result' => $existing]);
}

try {
    $pdo->beginTransaction();
    $query = $pdo->prepare('INSERT INTO quiz_submissions (request_id, full_name, email, total_score, result_key, result_json, quiz_version, consent_version, consent_at, payload_hash, session_hash) VALUES (?, ?, ?, ?, ?, ?, ?, ?, UTC_TIMESTAMP(), ?, ?)');
    $query->execute([$submission['requestId'], $submission['name'], $submission['email'], $result['score'], $result['key'], json_encode($result, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), quizDefinition()['version'], 'datos-2026-1', $payloadHash, $sessionHash]);
    $submissionId = (int) $pdo->lastInsertId();
    $saveAnswer = $pdo->prepare('INSERT INTO quiz_answers (submission_id, question_id, answer, points) VALUES (?, ?, ?, ?)');
    foreach ($submission['answers'] as $questionId => $answer) {
        $saveAnswer->execute([$submissionId, $questionId, $answer, ['A' => 0, 'B' => 1, 'C' => 2][$answer]]);
    }
    $pdo->commit();
} catch (Throwable $error) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    // Un doble clic o reintento concurrente comparte el mismo identificador.
    if ($error instanceof PDOException && (int) ($error->errorInfo[1] ?? 0) === 1062 && ($existing = $findExisting())) {
        jsonResponse(['message' => 'Gracias por responder el quiz', 'result' => $existing]);
    }
    throw $error;
}
jsonResponse(['message' => 'Gracias por responder el quiz', 'result' => $result], 201);
