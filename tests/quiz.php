<?php
declare(strict_types=1);
require_once __DIR__ . '/../backend/quiz.php';

$checks = 0;
function check(bool $condition, string $message): void {
    global $checks;
    $checks++;
    if (!$condition) { throw new RuntimeException($message); }
}
function invalid(array $input, string $message): void {
    try { validateSubmission($input); } catch (InvalidArgumentException) { check(true, $message); return; }
    check(false, $message);
}
$valid = ['requestId' => 'abcdabcd-1234-4234-8234-abcdef123456', 'name' => 'María López', 'email' => 'quiz-test-unit@example.invalid', 'consent' => true, 'answers' => array_map(fn ($id) => ['questionId' => $id, 'answer' => 'B'], range(1, 12))];
check(count(quizDefinition()['questions']) === 12, 'There must be 12 questions');
check(validateSubmission($valid)['name'] === 'María López', 'Unicode names must be preserved');
foreach (range(0, 24) as $score) {
    $remaining = $score;
    $answers = [];
    foreach (range(1, 12) as $id) { $points = min(2, $remaining); $remaining -= $points; $answers[$id] = ['A', 'B', 'C'][$points]; }
    $result = calculateResult($answers);
    check($result['score'] === $score, "Score $score failed");
    check($result['key'] === ($score <= 8 ? 'red' : ($score <= 16 ? 'yellow' : 'green')), "Boundary for $score failed");
}
invalid(array_replace($valid, ['name' => '   ']), 'Whitespace name must fail');
invalid(array_replace($valid, ['name' => '<script>alert(1)</script>']), 'Markup name must fail');
invalid(array_replace($valid, ['name' => str_repeat('á', 121)]), 'Long unicode name must fail');
invalid(array_replace($valid, ['email' => 'not-an-email']), 'Invalid email must fail');
invalid(array_replace($valid, ['consent' => false]), 'Consent required');
invalid(array_replace($valid, ['consent' => 'true']), 'Consent must be boolean');
invalid(array_replace($valid, ['requestId' => 'abc']), 'UUID required');
invalid(array_replace($valid, ['website' => 'bot.example']), 'Honeypot must fail');
invalid(array_replace($valid, ['answers' => array_slice($valid['answers'], 1)]), 'Incomplete quiz must fail');
$duplicate = $valid; $duplicate['answers'][11] = $duplicate['answers'][0]; invalid($duplicate, 'Repeated question must fail');
$invalidAnswer = $valid; $invalidAnswer['answers'][0]['answer'] = 24; invalid($invalidAnswer, 'Numeric scores must fail');
$unknown = $valid; $unknown['answers'][0]['questionId'] = 13; invalid($unknown, 'Unknown question must fail');
$tampered = $valid; $tampered['score'] = 24; $tampered['result_key'] = 'green';
check(calculateResult(validateSubmission($tampered)['answers'])['score'] === 12, 'Client score must be ignored');
echo "$checks comprobaciones correctas: todos los puntajes 0–24, límites, validación y manipulación.\n";
