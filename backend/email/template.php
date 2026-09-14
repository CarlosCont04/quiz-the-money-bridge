<?php
declare(strict_types=1);
if (!isset($report)) { http_response_code(404); exit; }
$palette = [
    'red' => ['ink' => '#a82f35', 'soft' => '#fff0ef', 'dot' => '#dc585c'],
    'yellow' => ['ink' => '#805a05', 'soft' => '#fff8e6', 'dot' => '#e7b33f'],
    'green' => ['ink' => '#217052', 'soft' => '#ecf8f1', 'dot' => '#43a47b'],
][$report['result']['key']];
$e = 'reportEscape';
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="color-scheme" content="light"><meta name="supported-color-schemes" content="light">
<title>Nuevo quiz financiero · The Money Bridge</title>
<style>
body,table,td,a{-webkit-text-size-adjust:100%;-ms-text-size-adjust:100%}table,td{mso-table-lspace:0pt;mso-table-rspace:0pt}img{border:0;outline:none;text-decoration:none}table{border-collapse:collapse}body{margin:0;padding:0;width:100%!important}a{color:#3065af}p{margin:0}@media only screen and (max-width:600px){.email-shell{width:100%!important}.pad{padding-left:22px!important;padding-right:22px!important}.email-heading{font-size:29px!important}.detail-label{width:75px!important}.answer-pad{padding:18px!important}.brand-logo{width:164px!important;height:auto!important}}
</style>
</head>
<body style="margin:0;padding:0;background-color:#f1f5f9;color:#17213d;font-family:'Neue Plak',Arial,Helvetica,sans-serif;">
<div style="display:none;font-size:1px;color:#f1f5f9;line-height:1px;max-height:0;max-width:0;opacity:0;overflow:hidden;mso-hide:all;">Nuevo registro del quiz: semáforo <?= $e($report['result']['color']) ?>, <?= (int)$report['result']['score'] ?>/24 puntos. Incluye las 12 respuestas.</div>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f1f5f9;"><tr><td align="center" style="padding:30px 10px;">
<!--[if mso]><table role="presentation" width="640" align="center"><tr><td><![endif]-->
<table role="presentation" class="email-shell" width="640" cellpadding="0" cellspacing="0" style="width:100%;max-width:640px;background-color:#ffffff;border:1px solid #dce5ee;">
<tr><td class="pad" style="padding:29px 38px;background-color:#ffffff;"><img class="brand-logo" src="cid:tmb-logo" alt="The Money Bridge" width="190" height="79" style="display:block;width:190px;height:79px;"></td></tr>
<tr><td height="4" style="height:4px;line-height:4px;background-color:#64c2c8;">&nbsp;</td></tr>
<tr><td class="pad" style="padding:35px 38px;background-color:#19255b;">
<p style="font-size:10px;line-height:17px;font-weight:bold;letter-spacing:2px;color:#9adade;">TU SEMÁFORO FINANCIERO</p>
<h1 class="email-heading" style="margin:14px 0 13px;font-size:35px;line-height:1.14;letter-spacing:-1px;font-weight:bold;color:#ffffff;">Un nuevo punto<br>de partida.</h1>
<p style="font-size:14px;line-height:23px;color:#e0e7f3;">Una persona ha completado el quiz. Aquí tienes su registro, su resultado y sus respuestas para dar seguimiento.</p>
<?php if ($report['isTest']): ?><p style="margin-top:19px;font-size:11px;line-height:18px;color:#c4edf0;">FASE DE PRUEBAS · Envío a la cuenta de verificación.</p><?php endif; ?>
</td></tr>
<tr><td class="pad" style="padding:31px 38px 25px;">
<h2 style="margin:0 0 17px;font-size:19px;line-height:26px;color:#19255b;">Datos de registro</h2>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="font-size:14px;line-height:23px;table-layout:fixed;">
<tr><td class="detail-label" width="90" valign="top" style="width:90px;padding:7px 10px 7px 0;color:#5e6c80;">Nombre</td><td style="padding:7px 0;color:#19255b;font-weight:bold;word-wrap:break-word;overflow-wrap:anywhere;"><?= $e($report['name']) ?></td></tr>
<tr><td class="detail-label" width="90" valign="top" style="width:90px;padding:7px 10px 7px 0;color:#5e6c80;">Correo</td><td style="padding:7px 0;word-wrap:break-word;overflow-wrap:anywhere;"><a href="mailto:<?= $e($report['email']) ?>" style="color:#3065af;text-decoration:underline;word-break:break-all;"><?= $e($report['email']) ?></a></td></tr>
<tr><td class="detail-label" width="90" valign="top" style="width:90px;padding:7px 10px 7px 0;color:#5e6c80;">Fecha</td><td style="padding:7px 0;color:#4b5d76;"><?= $e($report['date']) ?></td></tr>
</table>
<p style="margin-top:14px;font-size:11px;line-height:18px;color:#5e6c80;">Aceptación del uso de datos: registrada al enviar el quiz.</p>
</td></tr>
<tr><td class="pad" style="padding:0 38px 30px;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:<?= $palette['soft'] ?>;border:1px solid #dce5ee;"><tr><td class="answer-pad" style="padding:26px;">
<table role="presentation" cellpadding="0" cellspacing="0"><tr><?php foreach (['red','yellow','green'] as $key): ?><td width="22" style="width:22px;font-size:21px;line-height:24px;color:<?= $key === $report['result']['key'] ? $palette['dot'] : '#c9d4df' ?>;">&#9679;</td><?php endforeach; ?><td style="padding-left:9px;font-size:12px;line-height:20px;font-weight:bold;color:<?= $palette['ink'] ?>;">Semáforo <?= $e(mb_strtolower($report['result']['color'])) ?></td></tr></table>
<h2 style="margin:16px 0 12px;font-size:26px;line-height:32px;letter-spacing:-.5px;color:#19255b;"><?= $e($report['result']['title']) ?></h2>
<p style="font-size:14px;line-height:36px;color:<?= $palette['ink'] ?>;"><strong style="font-size:32px;"><?= (int)$report['result']['score'] ?></strong> de 24 puntos</p>
<p style="margin-top:15px;font-size:13px;line-height:23px;color:#4b5d76;"><?= $e($report['result']['description']) ?></p>
</td></tr></table>
</td></tr>
<tr><td class="pad" style="padding:0 38px 19px;"><p style="font-size:10px;line-height:17px;font-weight:bold;letter-spacing:1.5px;color:#3065af;">EL PANORAMA COMPLETO</p><h2 style="margin:7px 0 6px;font-size:23px;line-height:30px;color:#19255b;">Sus 12 respuestas</h2><p style="font-size:12px;line-height:20px;color:#5e6c80;">Cada respuesta aporta 0, 1 o 2 puntos al resultado.</p></td></tr>
<?php foreach ($report['rows'] as $row): ?>
<tr><td class="pad" style="padding:0 38px 13px;"><table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #dce5ee;"><tr><td class="answer-pad" style="padding:21px;">
<p style="margin:0 0 8px;font-size:10px;line-height:17px;letter-spacing:1px;color:#3065af;font-weight:bold;">PREGUNTA <?= str_pad((string)$row['number'], 2, '0', STR_PAD_LEFT) ?></p>
<h3 style="margin:0 0 11px;font-size:15px;line-height:23px;color:#19255b;"><?= $e($row['question']) ?></h3>
<?php if ($row['context'] !== ''): ?><p style="margin-bottom:11px;font-size:12px;line-height:21px;color:#5e6c80;"><?= $e($row['context']) ?></p><?php endif; ?>
<p style="font-size:13px;line-height:23px;color:#4b5d76;"><strong style="color:#3065af;"><?= $e($row['letter']) ?>)</strong> <?= $e($row['answer']) ?></p>
<p style="margin-top:11px;font-size:11px;line-height:18px;color:#5e6c80;"><strong style="color:#19255b;"><?= (int)$row['points'] ?> / 2</strong> puntos</p>
</td></tr></table></td></tr>
<?php endforeach; ?>
<tr><td class="pad" style="padding:15px 38px 31px;"><table role="presentation" cellpadding="0" cellspacing="0"><tr><td align="center" bgcolor="#19255b" style="background-color:#19255b;border-radius:6px;mso-padding-alt:15px 23px;"><a href="mailto:<?= $e($report['email']) ?>" style="display:inline-block;padding:15px 23px;font-size:13px;line-height:20px;font-weight:bold;color:#ffffff;text-decoration:none;">Responder al prospecto &nbsp; &#8594;</a></td></tr></table></td></tr>
<tr><td class="pad" style="padding:26px 38px;background-color:#f5f8fb;border-top:1px solid #dce5ee;">
<p style="font-size:13px;line-height:21px;font-weight:bold;color:#19255b;">Un puente entre tu dinero y tus metas.</p>
<p style="margin-top:8px;font-size:11px;line-height:19px;color:#5e6c80;">Quiz creado por Deyanira Mariscal<br>CEO de The Money Bridge</p>
<p style="margin-top:12px;font-size:11px;line-height:19px;"><a href="https://www.themoneybridge.com.mx/" style="color:#3065af;text-decoration:underline;">www.themoneybridge.com.mx</a></p>
<p style="margin-top:16px;font-size:10px;line-height:17px;color:#5e6c80;word-break:break-all;">Registro: <?= $e($report['requestId']) ?></p>
<p style="margin-top:6px;font-size:10px;line-height:17px;color:#5e6c80;">Información de uso interno para dar seguimiento a esta participación.</p>
</td></tr>
</table>
<!--[if mso]></td></tr></table><![endif]-->
</td></tr></table>
</body></html>
