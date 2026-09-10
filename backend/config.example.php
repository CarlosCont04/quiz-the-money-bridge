<?php
declare(strict_types=1);

// Copiar a config.local.php; este último nunca se incluye en Git ni en dist.
return [
    'db_host' => '127.0.0.1',
    'db_port' => 3306, // En este equipo, XAMPP utiliza 3308.
    'db_name' => 'quiz_money_bridge',
    'db_user' => 'root', // Solo desarrollo local; usar un usuario limitado en producción.
    'db_password' => '',
];
