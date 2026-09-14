<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../backend/database.php';
$settings = config();
echo json_encode([
    'phase' => $settings['email_phase'],
    'transport' => $settings['email_transport'],
    'recipient' => $settings['email_recipient'],
    'smtp_password_configured' => trim((string)$settings['smtp_password']) !== '',
    'outbox' => database()->query('SELECT status, COUNT(*) AS total FROM quiz_email_outbox GROUP BY status')->fetchAll(),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . "\n";
