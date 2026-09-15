<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../backend/database.php';
require_once __DIR__ . '/../backend/email/mailer.php';

// Diagnóstico de instalación: no enviar correos, modificar registros ni mostrar secretos.
$report = [
    'local_config_exists' => is_file(__DIR__ . '/../backend/config.local.php'),
    'phpmailer_installed' => false,
    'smtp_password_configured' => false,
    'database_connected' => false,
    'outbox_table_exists' => false,
    'outbox' => [],
    'delivery_errors' => [],
    'actions' => [],
];
try {
    $settings = config();
} catch (Throwable) {
    $report['actions'][] = 'Corrige la sintaxis y las claves de backend/config.local.php. No compartas su contraseña.';
    echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
    exit(1);
}
$report['phase'] = $settings['email_phase'];
$report['transport'] = $settings['email_transport'];
$report['smtp_password_configured'] = trim((string)$settings['smtp_password']) !== '';

try {
    if (is_file(__DIR__ . '/../vendor/autoload.php')) {
        require_once __DIR__ . '/../vendor/autoload.php';
        $report['phpmailer_installed'] = class_exists(\PHPMailer\PHPMailer\PHPMailer::class);
    }
} catch (Throwable) { /* Un vendor incompleto también requiere composer install. */ }
if (!$report['phpmailer_installed']) {
    $report['actions'][] = 'Ejecuta composer install en este equipo; vendor/ no se descarga con git pull.';
}
try {
    $report['recipient'] = quizEmailRecipient($settings);
} catch (Throwable) {
    $report['actions'][] = 'Revisa email_phase y email_recipient: en test debe ser aldoemonterm@gmail.com.';
}
if ($settings['email_transport'] === 'capture') {
    $report['actions'][] = 'El transporte capture guarda pruebas locales y no envía correos. Usa email_transport=smtp para el envío real.';
} elseif ($settings['email_transport'] !== 'smtp') {
    $report['actions'][] = 'Configura email_transport=smtp.';
}
if (!$report['smtp_password_configured']) {
    $report['actions'][] = 'Configura smtp_password en backend/config.local.php de ESTE equipo o en SMTP_PASSWORD. Git excluye las credenciales; el ejemplo deja la contraseña vacía.';
}
if (!extension_loaded('openssl')) {
    $report['actions'][] = 'Habilita openssl en el PHP que ejecuta el quiz.';
}
if (!extension_loaded('mbstring') || !extension_loaded('pdo_mysql')) {
    $report['actions'][] = 'Habilita mbstring y pdo_mysql en el PHP que ejecuta el quiz.';
}
if ($settings['smtp_host'] !== 'smtp.hostinger.com') {
    $report['actions'][] = 'Para esta cuenta de Hostinger, configura smtp_host=smtp.hostinger.com, sin https:// ni rutas.';
}
if (!(($settings['smtp_encryption'] === 'ssl' && (int)$settings['smtp_port'] === 465) || ($settings['smtp_encryption'] === 'tls' && (int)$settings['smtp_port'] === 587))) {
    $report['actions'][] = 'Usa smtp_encryption=ssl con smtp_port=465, o tls con puerto 587.';
}
try {
    $pdo = database();
    $report['database_connected'] = true;
    $query = $pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'quiz_email_outbox'");
    $report['outbox_table_exists'] = (int)$query->fetchColumn() === 1;
    if (!$report['outbox_table_exists']) {
        $report['actions'][] = 'Ejecuta npm run db:setup para instalar también database/email-outbox.sql; importar solo schema.sql no basta.';
    } else {
        $report['outbox'] = $pdo->query('SELECT status, COUNT(*) AS total FROM quiz_email_outbox GROUP BY status')->fetchAll();
        $report['delivery_errors'] = $pdo->query("SELECT last_error_code AS code, COUNT(*) AS total FROM quiz_email_outbox WHERE status = 'failed' GROUP BY last_error_code")->fetchAll();
        if ($report['delivery_errors'] !== []) {
            $report['actions'][] = 'Resuelve los errores de delivery_errors y ejecuta npm run email:retry o npm run email:work. Para intentos agotados: npm run email:retry -- --retry=ID.';
        }
    }
} catch (Throwable) {
    $report['actions'][] = 'Verifica que MySQL esté iniciado y que db_host, db_port, db_name y credenciales correspondan a ESTE equipo. Si falta la base, ejecuta npm run db:setup.';
}
$report['note'] = 'Este diagnóstico verifica configuración y base de datos; no comprueba autenticación SMTP ni entrega. npm run email:test envía una muestra real a Aldo. Importar SQL o insertar filas directamente no genera correos.';
echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
exit($report['actions'] === [] ? 0 : 1);
