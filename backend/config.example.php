<?php
declare(strict_types=1);

// Copiar a config.local.php. Las claves se usan solo en PHP, nunca en Astro.
return [
    'emailjs_service_id' => '',
    'emailjs_template_id' => '',
    'emailjs_public_key' => '',
    'emailjs_private_key' => '',
    // Vacío: adjuntar logo.png en EmailJS (CID). Alternativa: URL HTTPS pública.
    'email_logo_url' => '',
];
