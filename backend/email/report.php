<?php
declare(strict_types=1);
require_once __DIR__ . '/../quiz.php';

function reportEscape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Snapshot del contenido, independiente de cambios futuros en el quiz. */
function quizEmailReport(array $submission, array $result, string $createdAt, bool $isTest): array
{
    $questions = quizDefinition()['questions'];
    $rows = [];
    foreach ($questions as $question) {
        $letter = $submission['answers'][$question['id']];
        $points = ['A' => 0, 'B' => 1, 'C' => 2][$letter];
        $rows[] = ['number' => $question['id'], 'question' => $question['title'], 'context' => $question['description'] ?? '', 'letter' => $letter, 'answer' => $question['options'][$points], 'points' => $points];
    }
    $date = (new DateTimeImmutable($createdAt, new DateTimeZone('UTC')))->setTimezone(new DateTimeZone('America/Mexico_City'));
    $report = [
        'name' => $submission['name'], 'email' => $submission['email'], 'requestId' => $submission['requestId'],
        'date' => $date->format('d/m/Y · H:i') . ' (Ciudad de México)',
        'result' => $result, 'rows' => $rows, 'isTest' => $isTest,
    ];
    $subject = ($isTest ? '[PRUEBA] ' : '') . 'Nuevo quiz financiero | ' . $result['color'] . ' · ' . $result['score'] . '/24';
    $text = "THE MONEY BRIDGE\n" . ($isTest ? "NOTIFICACIÓN DE PRUEBA\n" : '') . "Nuevo quiz financiero\n\n";
    $text .= "Nombre: {$report['name']}\nCorreo: {$report['email']}\nRegistro: {$report['date']}\nReferencia: {$report['requestId']}\nAceptación del uso de datos: registrada\n\n";
    $text .= "SEMÁFORO {$result['color']} · {$result['score']}/24 puntos\n{$result['title']}\n{$result['description']}\n\nLAS 12 RESPUESTAS\n";
    foreach ($rows as $row) {
        $text .= "\n{$row['number']}. {$row['question']}\n" . ($row['context'] !== '' ? $row['context'] . "\n" : '') . "{$row['letter']}) {$row['answer']}\nPuntos: {$row['points']}/2\n";
    }
    $text .= "\nQuiz creado por Deyanira Mariscal, CEO de The Money Bridge.\nhttps://www.themoneybridge.com.mx/\nInformación de uso interno para dar seguimiento a esta participación.\n";
    ob_start();
    require __DIR__ . '/template.php';
    $html = ob_get_clean();
    return ['subject' => $subject, 'html' => $html, 'text' => $text, 'replyTo' => $report['email'], 'replyName' => $report['name']];
}
