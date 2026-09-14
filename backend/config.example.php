<?php
declare(strict_types=1);

// Copiar a config.local.php; este último nunca se incluye en Git ni en dist.
return [
    'db_host' => '127.0.0.1',
    'db_port' => 3306, // En este equipo, XAMPP utiliza 3308.
    'db_name' => 'quiz_money_bridge',
    'db_user' => 'root', // Solo desarrollo local; usar un usuario limitado en producción.
    'db_password' => '',
    // Pruebas: conservar este destinatario hasta verificar la entrega y aprobar el cambio.
    'email_phase' => 'test',
    'email_transport' => 'smtp',
    'email_recipient' => 'aldoemonterm@gmail.com',
    // Producción aprobada: email_phase=production y destinatario deyanira.mariscalc@outlook.com.
    'email_from' => 'info@themoneybridge.com.mx',
    'email_from_name' => 'The Money Bridge',
    'smtp_host' => 'smtp.hostinger.com',
    'smtp_port' => 465,
    'smtp_encryption' => 'ssl',
    'smtp_username' => 'info@themoneybridge.com.mx',
    // Contraseña de aplicación exclusivamente en config.local.php o SMTP_PASSWORD.
    'smtp_password' => '',
];
