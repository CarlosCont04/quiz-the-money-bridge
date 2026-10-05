<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../backend/email/mailer.php';
$settings = config();
$configured = [];
$actions = [];
foreach (['emailjs_service_id', 'emailjs_template_id', 'emailjs_public_key', 'emailjs_private_key'] as $key) {
    $configured[$key . '_configured'] = is_string($settings[$key]) && trim($settings[$key]) !== '';
    if (!$configured[$key . '_configured']) $actions[] = 'Completar ' . $key . ' en backend/config.local.php o en su variable de entorno.';
}
foreach (['curl', 'mbstring'] as $extension) {
    if (!extension_loaded($extension)) $actions[] = 'Habilitar la extensión PHP ' . $extension . '.';
}
if (PHP_VERSION_ID < 80200) $actions[] = 'Seleccionar PHP 8.2 o posterior.';
$directory = __DIR__ . '/../.runtime';
if (!is_writable(is_dir($directory . '/sessions') ? $directory . '/sessions' : (is_dir($directory) ? $directory : dirname($directory)))) {
    $actions[] = 'Dar al proceso PHP permiso de escritura en .runtime/sessions.';
}
$logo = $settings['email_logo_url'];
if (!is_string($logo) || ($logo !== '' && (!filter_var($logo, FILTER_VALIDATE_URL) || parse_url($logo, PHP_URL_SCHEME) !== 'https'))) {
    $actions[] = 'Usar una URL HTTPS válida en email_logo_url o dejarla vacía para el adjunto logo.png.';
}
echo json_encode([
    'transport' => 'EmailJS REST', 'recipient' => QUIZ_EMAIL_TO, 'from' => QUIZ_EMAIL_FROM,
    'php_version' => PHP_VERSION, 'curl_available' => extension_loaded('curl'),
    'mbstring_available' => extension_loaded('mbstring'),
    'configuration' => $configured, 'logo_mode' => $logo === '' ? 'cid:logo.png (adjunto en EmailJS)' : 'URL HTTPS',
    'actions' => $actions, 'guide' => 'docs/emailjs.md',
    'note' => 'Diagnóstico local: no prueba la conexión ni envía mensajes. El servicio y la plantilla deben autorizar el remitente y fijar la destinataria.',
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
exit($actions === [] ? 0 : 1);
