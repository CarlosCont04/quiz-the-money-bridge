<?php
declare(strict_types=1);

function quizDefinition(): array
{
    static $quiz;
    return $quiz ??= json_decode(file_get_contents(__DIR__ . '/../shared/quiz.json'), true, 512, JSON_THROW_ON_ERROR);
}

function validateSubmission(array $input): array
{
    if (!is_string($input['name'] ?? null) || !is_string($input['email'] ?? null)) {
        throw new InvalidArgumentException('Escribe tu nombre y un correo válido.');
    }
    $name = trim($input['name']);
    $email = trim($input['email']);
    if (mb_strlen($name) < 2 || mb_strlen($name) > 120 || preg_match('/[\x00-\x1F\x7F<>]/u', $name)) {
        throw new InvalidArgumentException('Escribe un nombre válido de 2 a 120 caracteres.');
    }
    if (strlen($email) > 254 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new InvalidArgumentException('Escribe un correo electrónico válido.');
    }
    if (($input['consent'] ?? null) !== true) {
        throw new InvalidArgumentException('Necesitamos tu aceptación del uso de datos para registrar el quiz.');
    }
    if (!is_string($input['requestId'] ?? null) || !preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $input['requestId'])) {
        throw new InvalidArgumentException('El identificador de envío no es válido. Recarga la página e intenta de nuevo.');
    }
    if (($input['website'] ?? '') !== '') {
        throw new InvalidArgumentException('No fue posible registrar el quiz.');
    }
    $rawAnswers = $input['answers'] ?? null;
    $questions = quizDefinition()['questions'];
    if (!is_array($rawAnswers) || !array_is_list($rawAnswers) || count($rawAnswers) !== count($questions)) {
        throw new InvalidArgumentException('Responde las 12 preguntas antes de enviar el quiz.');
    }
    $answers = [];
    foreach ($rawAnswers as $answer) {
        if (!is_array($answer) || !is_int($answer['questionId'] ?? null) || !in_array($answer['questionId'], array_column($questions, 'id'), true) || !in_array($answer['answer'] ?? null, ['A', 'B', 'C'], true) || isset($answers[$answer['questionId']])) {
            throw new InvalidArgumentException('Hay una respuesta inválida o repetida. Revisa el quiz.');
        }
        $answers[$answer['questionId']] = $answer['answer'];
    }
    ksort($answers);
    return ['name' => $name, 'email' => $email, 'answers' => $answers, 'requestId' => strtolower($input['requestId']), 'consent' => true];
}

function calculateResult(array $answers): array
{
    $definition = quizDefinition();
    if (count($answers) !== count($definition['questions'])) {
        throw new InvalidArgumentException('Incomplete answers');
    }
    $points = ['A' => 0, 'B' => 1, 'C' => 2];
    $score = 0;
    foreach ($answers as $answer) {
        if (!is_string($answer) || !array_key_exists($answer, $points)) {
            throw new InvalidArgumentException('Invalid answer');
        }
        $score += $points[$answer];
    }
    foreach ($definition['results'] as $result) {
        if ($score >= $result['min'] && $score <= $result['max']) {
            return ['score' => $score, 'maxScore' => $definition['maxScore'], 'key' => $result['key'], 'color' => $result['color'], 'title' => $result['title'], 'description' => $result['description']];
        }
    }
    throw new LogicException('Unmapped score');
}
