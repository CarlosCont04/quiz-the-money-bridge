# Quiz The Money Bridge

Proyecto independiente del sitio principal y del evento de The Money Bridge. Incluye el quiz de Deyanira Mariscal, CEO de The Money Bridge, con 12 preguntas, registro de nombre y correo, almacenamiento en SQL y resultado en una ventana emergente.

## Ejecutar en este equipo

Requiere Node.js 22.12 o posterior, PHP 8.2 con `pdo_mysql`, `mbstring` y `openssl`, Composer, y MySQL 8 o MariaDB 10.4 de XAMPP.

```powershell
npm.cmd install
composer install
# Iniciar MySQL desde XAMPP.
npm.cmd run db:setup
npm.cmd run dev
```

Abrir **http://127.0.0.1:4321/**. Un solo comando de desarrollo inicia Astro/Vite en 4321 y PHP en 8080; Vite redirige `/api/` al servidor PHP. `PHP_BINARY` permite indicar una instalación diferente de PHP. En Windows se detecta `C:/xampp/php/php.exe`.

La configuración local de este equipo utiliza `127.0.0.1:3308`, base `quiz_money_bridge`. Las credenciales están en `backend/config.local.php`, excluido de Git. En un clon nuevo, copiar `backend/config.example.php` a `backend/config.local.php` y ajustar puerto y credenciales. También se admiten `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER` y `DB_PASSWORD` como variables de entorno; tienen prioridad sobre el archivo local.

`npm run db:setup` crea únicamente la base configurada y las tablas que no existan; conserva los registros existentes. En un servidor con una base ya provisionada, importar `database/schema.sql` y `database/email-outbox.sql` dentro de esa base. En una instalación anterior basta importar la segunda migración o volver a ejecutar `db:setup`. No se envían participaciones históricas automáticamente.

## Después de hacer pull en otro equipo

`git pull` actualiza el código. No copia `backend/config.local.php` (credenciales), `vendor/` (dependencias PHP), `dist/` (sitio compilado) ni la base de datos. Que el correo funcione en el equipo de otra persona no configura SMTP en el tuyo.

Desde la carpeta del proyecto:

```powershell
git pull
git lfs pull
npm.cmd ci
composer install
# Crear la configuración privada solo si todavía no existe; no sobrescribirla.
if (-not (Test-Path 'backend/config.local.php')) {
    Copy-Item 'backend/config.example.php' 'backend/config.local.php'
}
```

Editar **tu propio** `backend/config.local.php`: ajustar el puerto y las credenciales de tu MySQL y configurar `smtp_password` con la contraseña de la cuenta SMTP. En pruebas conservar `email_phase=test`, `email_recipient=aldoemonterm@gmail.com` y `email_transport=smtp`. No subir la contraseña al repositorio ni enviarla junto al diagnóstico. Si defines variables de entorno, tienen prioridad sobre este archivo; una variable `SMTP_PASSWORD` vacía también reemplaza el valor del archivo.

Iniciar MySQL y ejecutar:

```powershell
npm.cmd run db:setup
npm.cmd run email:status
```

`email:status` no envía correos ni muestra contraseñas. Revisa `smtp_password_configured`, `phpmailer_installed`, `database_connected`, `outbox_table_exists` y las instrucciones de `actions`. Un código de salida 1 significa que encontró algo que revisar. Si el diagnóstico está correcto, `npm.cmd run email:test` envía una muestra real a Aldo para comprobar SMTP desde ese equipo.

Para ver los cambios en Apache, **recompilar** después del pull:

```powershell
$env:PUBLIC_BASE_PATH = '/quiz-the-money-bridge/'
npm.cmd run build
Remove-Item Env:PUBLIC_BASE_PATH
```

Después responder un quiz nuevo en `http://localhost/quiz-the-money-bridge/` y consultar otra vez `npm.cmd run email:status`. Si utilizas `npm run dev`, reiniciar ese proceso después de actualizar. Si hay correos fallidos, corregir la configuración y ejecutar `npm.cmd run email:retry`; usar `email:work` para los reintentos posteriores. Reiniciar un worker que ya estaba activo para que cargue la nueva configuración.

Los archivos SQL crean las tablas: no contienen contraseñas SMTP ni disparadores de correo. El envío nace en `public/api/submit.php` al recibir el formulario. Importar SQL o insertar registros manualmente no genera notificaciones ni reenvía participaciones antiguas.

## Compilación y Apache de XAMPP

Para probar la versión compilada en la raíz:

```powershell
npm.cmd run build
npm.cmd start
```

Abrir **http://127.0.0.1:8080/**. Detener antes el modo de desarrollo, porque ambos usan el puerto 8080. No usar `astro preview` para validar envíos: se necesita PHP.

Para servir la carpeta actual con Apache en **http://localhost/quiz-the-money-bridge/**:

```powershell
$env:PUBLIC_BASE_PATH = '/quiz-the-money-bridge/'
npm.cmd run build
Remove-Item Env:PUBLIC_BASE_PATH
```

Iniciar Apache y MySQL en XAMPP. La URL puede requerir el puerto configurado de Apache. El `.htaccess` de la raíz publica `dist/` y bloquea los directorios internos. Requiere `mod_rewrite` y `AllowOverride All`. Si el proyecto se publica en un dominio propio, compilar con base `/` y configurar el `DocumentRoot` directamente en `dist/`. Conservar `backend/`, `shared/` y `vendor/` como directorios hermanos de `dist/`: los endpoints PHP los utilizan en ejecución. PHP necesita permisos de escritura en `.runtime/sessions/`.

Usar HTTPS y un usuario SQL propio con permisos `SELECT`, `INSERT` sobre las tablas del quiz y `SELECT`, `INSERT`, `UPDATE` sobre `quiz_email_outbox`. Reservar las credenciales de creación de esquema para instalación y mantenimiento. La base y las sesiones no deben ser públicas. El límite de 30 intentos por sesión cada 15 minutos es una protección básica; para una campaña pública, configurar límites adicionales en el servidor frontal. No hay un panel público de prospectos.

## Contenido y cálculo

`shared/quiz.json` es la única fuente del contenido. Conserva las 12 preguntas, opciones y tres descripciones de `QUIZ.docx`. La puntuación se determina en PHP: A = 0, B = 1, C = 2; máximo 24. No se muestran puntos junto a las respuestas.

| Puntuación | Semáforo | Resultado |
| --- | --- | --- |
| 0–8 | Rojo | Tu dinero necesita atención |
| 9–16 | Amarillo | Tu dinero puede dar más |
| 17–24 | Verde | Tu dinero está listo para crecer |

El resultado aparece después de confirmar la transacción SQL y contiene “Gracias por responder el quiz”. Se puede cerrar con Escape, reabrir o iniciar otro quiz. Si falla el guardado, las respuestas permanecen en memoria para reintentar. Recargar o cerrar la página descarta respuestas todavía no enviadas. Cada nueva participación genera una notificación interna con nombre, correo, fecha, resultado y las 12 respuestas; no se suscribe al visitante a campañas ni se le envía un correo automáticamente.

Las secciones temáticas son ayudas de navegación añadidas a la interfaz. El documento se trató como fuente de contenido y reglas del quiz, sin convertir sus notas para el programador en texto visible al visitante. Se normalizaron mayúsculas de los títulos de resultados para lectura en pantalla.

## Estructura

```text
src/components/       Secciones Astro y ventanas accesibles
src/styles/           CSS responsive y variables de identidad
src/scripts/          Control del quiz en TypeScript
src/assets/           Originales multimedia administrados con Git LFS
shared/quiz.json       Preguntas y resultados compartidos con PHP
public/api/           Endpoints session.php y submit.php
backend/              Validación, cálculo, configuración y PDO
database/schema.sql   Tablas de registros y respuestas
scripts/              Desarrollo, servidor PHP e instalación SQL
tests/                Pruebas de reglas, navegación, API y SQL
dist/                 HTML, CSS, JS, imágenes optimizadas y API; generado
```

Astro genera HTML estático y optimiza imágenes localmente; no se carga un framework de interfaz ni fuentes remotas. Los estilos usan la paleta del sitio del evento: `#19255B`, `#3065AF`, `#3CA0DA` y `#64C2C8`. El logotipo, la fotografía y la insignia proceden del proyecto local `the-money-bridge`; este quiz conserva copias independientes. `styles.zip` no estaba disponible en la ruta indicada, por lo que la referencia fue el código del sitio del evento.

## Datos y API

- `GET /api/session.php`: crea una sesión con cookie HttpOnly/SameSite y devuelve un token CSRF.
- `POST /api/submit.php`: acepta JSON con `requestId` UUID v4, `name`, `email`, `consent: true`, `website: ""` y `answers: [{questionId: 1, answer: "A"}, ...]`. Requiere cabecera `X-CSRF-Token` y la cookie de sesión.
- Respuestas: `201` guardado, `200` reintento ya guardado, `403` sesión inválida, `409` identificador reutilizado con otro contenido, `413` tamaño excesivo, `415` formato incorrecto, `422` datos inválidos, `429` límite de intentos, `503` fallo del servicio.

El servidor valida las 12 respuestas únicas, calcula el puntaje desde sus valores, utiliza sentencias preparadas PDO y guarda el registro, las respuestas y la notificación pendiente en una transacción. El UUID único permite repetir un envío sin duplicarlo ni repetir un correo ya enviado. El puntaje y el color enviados por un cliente se ignoran. No se exponen correos ni respuestas mediante endpoints de consulta. Los errores públicos no incluyen detalles SQL y los logs de aplicación omiten datos personales y credenciales.

`quiz_submissions` conserva nombre, correo, puntaje, semáforo, resultado calculado, versiones de quiz/aceptación, fecha y hashes del envío/sesión. `quiz_answers` conserva una fila por pregunta. Para revisión interna, consultar estas tablas con phpMyAdmin o una herramienta SQL autorizada. El administrador puede atender solicitudes de eliminación usando el correo registrado; la eliminación de una participación elimina sus respuestas por clave foránea.

El texto “Uso de tus datos” explica la captura y enlaza al contacto que figura en el sitio del evento. Antes de una publicación comercial, The Money Bridge debe proporcionar su aviso de privacidad institucional y definir su política de conservación para integrar esa información.

## Verificación

```powershell
npm.cmd test
npm.cmd run test:email
npm.cmd run build
$env:PLAYWRIGHT_BROWSERS_PATH = Join-Path (Get-Location) '.runtime/browsers'
npx.cmd playwright install chromium
npm.cmd run test:e2e
```

Las pruebas PHP cubren todos los puntajes de 0 a 24, límites de color, datos inválidos y manipulación del resultado. Playwright verifica escritorio y móvil, validación, retroceso, tres resultados, accesibilidad con axe, reintentos, CSRF y persistencia real en SQL. Las pruebas utilizan correos `quiz-test-…@example.invalid` y eliminan exclusivamente sus registros al terminar. Requieren una base de pruebas o la base local de desarrollo, nunca una base de producción.

Playwright inicia su servidor con transporte `capture`: genera archivos MIME en `.runtime/mail-capture/` sin salir a Internet y valida que cada participación produce una única notificación. La limpieza elimina sus filas y capturas. Para verificar la versión compilada que sirve Apache, establecer `E2E_BASE_URL=http://localhost/quiz-the-money-bridge/` y configurar **ese servidor** con `email_transport=capture` y `email_phase=test`. En ese modo no se inicia el servidor de desarrollo. No ejecutar esa suite contra un servidor configurado para enviar correo real.

## Correo interno de cada participación

PHPMailer, fijado en `composer.lock`, envía por SMTP autenticado. Se utiliza `smtp.hostinger.com`, puerto `465`, cifrado `ssl`; `https://mail.hostinger.com/` es el webmail y no debe usarse como host SMTP. El remitente es `info@themoneybridge.com.mx`, con nombre `The Money Bridge`. El `Reply-To` apunta al correo del prospecto.

La configuración predeterminada es la **fase de pruebas**:

```php
'email_phase' => 'test',
'email_transport' => 'smtp',
'email_recipient' => 'aldoemonterm@gmail.com',
'email_from' => 'info@themoneybridge.com.mx',
'email_from_name' => 'The Money Bridge',
'smtp_host' => 'smtp.hostinger.com',
'smtp_port' => 465,
'smtp_encryption' => 'ssl',
'smtp_username' => 'info@themoneybridge.com.mx',
'smtp_password' => '', // Solo config.local.php o variable SMTP_PASSWORD.
```

Todas estas claves también admiten variables de entorno en mayúsculas (`EMAIL_PHASE`, `EMAIL_RECIPIENT`, `SMTP_PASSWORD`, etc.), con prioridad sobre el archivo local. Las credenciales de correo no se cargan desde archivos públicos ni llegan a JavaScript. No guardar contraseñas en `config.example.php` ni en Git.

El correo usa tablas e instrucciones CSS en línea para compatibilidad con Gmail y Outlook, HTML adaptable, versión de texto y el logotipo PNG incrustado mediante CID. No requiere descargar imágenes externas. `backend/email/report.php` crea una copia del contenido al registrar el quiz; `backend/email/template.php` define su presentación. Esa copia conserva los textos enviados aunque posteriormente cambie el cuestionario.

```powershell
npm.cmd run email:preview # Tres HTML y TXT locales, sin enviar.
npm.cmd run email:test    # Un correo real con datos ficticios, únicamente a Aldo.
npm.cmd run email:status  # Diagnóstico local sin secretos ni envíos; incluye errores y pasos a seguir.
npm.cmd run email:retry   # Procesar los pendientes cuyo tiempo de espera ya venció.
npm.cmd run email:work    # Procesar pendientes cada 60 segundos; detener con Ctrl+C.
```

En este equipo, Composer está disponible también con `C:\xampp\php\php.exe -d extension=zip .runtime/composer.phar install`. Esa opción activa ZIP únicamente para instalar dependencias; no modifica `php.ini`.

Después de guardar el quiz se intenta el envío inmediatamente. Si SMTP falla, el resultado sigue disponible y la fila queda `failed` para reintento. Ejecutar `email:work` como proceso supervisado o programar `php /ruta/al/proyecto/scripts/email-worker.php` cada minuto (cron/Programador de tareas). La cola no se procesa por sí sola cuando no hay ningún proceso activo. El backoff aumenta de 1 a 60 minutos, con un máximo de 8 intentos; consultar `last_error_code` para resolver fallos. Para reactivar una notificación agotada: `npm run email:retry -- --retry=ID`; solo se reactivan filas fallidas, nunca correos enviados.

Estados: `pending`, `sending`, `sent` (aceptado por SMTP), `failed` y `captured` (prueba local; nunca significa entrega). Los procesos reclaman cada fila con un bloqueo temporal de 5 minutos para evitar envíos concurrentes; los reintentos conservan el `Message-ID`. Como con SMTP en general, un corte entre la aceptación remota y su confirmación SQL puede producir una entrega duplicada en un reintento. Confirmar la recepción en la bandeja: aceptación SMTP no prueba llegada a Inbox.

Después de verificar la prueba y **aprobar la entrega**, cambiar únicamente en la configuración privada:

```php
'email_phase' => 'production',
'email_recipient' => 'deyanira.mariscalc@outlook.com',
```

La fase test impide enviar al destinatario del cliente. Las notificaciones pendientes conservan el destinatario que tenían al crearse; cambiar de fase no redirige pruebas antiguas a Deyanira. No hay copia CC/BCC ni envíos a destinos controlados por el visitante. La aceptación del uso de datos en pantalla incluye el envío interno y se registra con versión `datos-2026-2`.

Referencia: [configuración SMTP oficial de Hostinger](https://support.hostinger.com/en/articles/1575756-how-to-get-email-account-configuration-details-for-hostinger-email) y [PHPMailer](https://github.com/PHPMailer/PHPMailer).

## Git y GitHub

El repositorio remoto configurado es `https://github.com/CarlosCont04/quiz-the-money-bridge.git`. Las imágenes originales se gestionan con Git LFS; código, CSS, JSON y lockfile se gestionan con Git. `dist`, credenciales, sesiones, navegadores y resultados de pruebas están excluidos.

```powershell
git lfs install
git clone https://github.com/CarlosCont04/quiz-the-money-bridge.git
cd quiz-the-money-bridge
git lfs pull
npm.cmd ci
composer install
```

Referencias de implementación: [instalación oficial de Astro](https://docs.astro.build/en/install-and-setup/) y [sentencias preparadas de PHP PDO](https://www.php.net/manual/en/pdo.prepared-statements.php).
