# Quiz The Money Bridge

Quiz independiente en español de Deyanira Mariscal, CEO de The Money Bridge: doce preguntas, registro de nombre y correo, y resultado financiero en un diálogo accesible. Cada participación se envía con la plantilla HTML original a **deyanira.mariscalc@outlook.com**, mediante **EmailJS**, desde el servicio autorizado para **info@themoneybridge.com.mx**.

La aplicación usa Astro y TypeScript para la página estática y PHP para validar, calcular el semáforo y enviar el correo. **No utiliza SQL, PHPMailer, Composer ni una cola de correo**. Los datos de una base anterior no se eliminan ni se migran.

## Configurar y ejecutar localmente

Requiere Node.js 22.12 o posterior, Git LFS y PHP 8.2 o posterior con `curl` y `mbstring`. En Windows se detecta `C:/xampp/php/php.exe`; `PHP_BINARY` permite usar otro ejecutable.

```powershell
npm.cmd ci
# Crear solo si no existe; no sobrescribir credenciales locales.
if (-not (Test-Path 'backend/config.local.php')) {
    Copy-Item 'backend/config.example.php' 'backend/config.local.php'
}
```

Seguir [la guía de EmailJS](docs/emailjs.md) para conectar el remitente, crear la plantilla e incorporar en `backend/config.local.php`:

```php
<?php
return [
    'emailjs_service_id' => 'service_REEMPLAZAR',
    'emailjs_template_id' => 'template_REEMPLAZAR',
    'emailjs_public_key' => 'REEMPLAZAR_CLAVE_PUBLICA',
    'emailjs_private_key' => 'REEMPLAZAR_CLAVE_PRIVADA',
    'email_logo_url' => '', // Adjuntar logo.png en la plantilla EmailJS.
];
```

Las variables de proceso `EMAILJS_SERVICE_ID`, `EMAILJS_TEMPLATE_ID`, `EMAILJS_PUBLIC_KEY`, `EMAILJS_PRIVATE_KEY` y `EMAIL_LOGO_URL` tienen prioridad. PHP no lee `.env`; ese archivo se reserva a configuración pública de Astro. No usar `PUBLIC_*` para credenciales. Las claves SQL/SMTP de una configuración anterior se ignoran, sin modificar el archivo local.

```powershell
npm.cmd run email:status
npm.cmd run dev
```

Abrir **http://127.0.0.1:4321/**. El lanzador inicia Astro en 4321 y PHP en 8080; `/api/` se redirige a PHP. No es necesario iniciar MySQL.

## Compilación y despliegue

Para probar el sitio compilado en la raíz, detener desarrollo y ejecutar:

```powershell
npm.cmd run build
npm.cmd start
```

Abrir **http://127.0.0.1:8080/**. Se necesita el servidor PHP para enviar; `astro preview` solo sirve la página estática. Para Apache en **http://localhost/quiz-the-money-bridge/**, ejecutar `npm.cmd run build:xampp`. El `.htaccess` de la raíz del repositorio reescribe a `dist/` y bloquea directorios internos; necesita `mod_rewrite` y `AllowOverride All`.

**Para HostGator bajo `/quiz/`:**

```powershell
npm.cmd run build:hosting
npm.cmd run test:hosting
```

El paquete completo está en **`artifacts/hosting-FECHA/quiz-hosting.zip`** y su ruta se registra en `artifacts/latest-hosting.json`. Separa `public_html/quiz/` de `tmb-quiz-private/`, fuera de la raíz pública. Incluye backend, contenido y recursos, sin claves ni sesiones locales. Seguir [la guía de HostGator](docs/hosting.md). No publicar solamente `dist/` ni el `.htaccess` del repositorio.

## Correo y plantilla HTML

`backend/email/template.php` conserva las tablas, colores, tipografía de respaldo y diseño responsive del correo anterior. `backend/email/report.php` incorpora el nombre, correo, fecha en Ciudad de México, aceptación, UUID, resultado y las doce preguntas con sus respuestas y puntos; todos los valores se escapan para HTML. `backend/email/mailer.php` envía mediante la URL HTTPS fija de EmailJS.

La plantilla del panel EmailJS debe tener destinataria fija **deyanira.mariscalc@outlook.com**, remitente autorizado **info@themoneybridge.com.mx**, asunto `{{subject}}`, Reply-To `{{reply_to}}` y contenido **`{{{html_content}}}`**. Conservar el logotipo subiendo `backend/email/assets/logo.png` como adjunto estático `logo.png`. Como alternativa sin adjuntos, configurar `email_logo_url` con una URL HTTPS; el paquete incluye `/quiz/email-assets/logo.png`. Los pasos y las referencias oficiales están en [docs/emailjs.md](docs/emailjs.md).

```powershell
npm.cmd run email:preview # Tres HTML/TXT locales, sin enviar.
npm.cmd run email:status  # Diagnóstico sin mostrar claves ni enviar.
npm.cmd run email:test    # ENVÍO REAL de una muestra ficticia a la plantilla configurada.
```

La aceptación de EmailJS no garantiza la llegada a Inbox; comprobar la recepción y el diseño en Outlook. Si el proveedor falla, el sitio informa del error y conserva las respuestas en memoria para que el usuario reintente. No hay cola persistente ni cron/worker. Recargar o cerrar la página descarta el registro que aún no se ha enviado.

## Contenido y cálculo

`shared/quiz.json` es la fuente única de las doce preguntas y resultados. PHP calcula A = 0, B = 1, C = 2, con máximo 24. El puntaje/color enviado por el navegador se ignora.

| Puntuación | Semáforo | Resultado |
| --- | --- | --- |
| 0–8 | Rojo | Tu dinero necesita atención |
| 9–16 | Amarillo | Tu dinero puede dar más |
| 17–24 | Verde | Tu dinero está listo para crecer |

El resultado aparece después de confirmar la aceptación del envío, con «Gracias por responder el quiz». Se puede cerrar con Escape, reabrir o iniciar otra participación. No se envía correo al visitante ni se le suscribe a publicidad. El consentimiento y «Uso de tus datos» explican el procesamiento por EmailJS y el registro en el buzón del equipo.

## API y persistencia

- `GET /api/session.php`: crea una sesión con cookie HttpOnly/SameSite y devuelve un token CSRF.
- `POST /api/submit.php`: acepta JSON con `requestId` UUID v4, `name`, `email`, `consent: true`, `website: ""` y doce `answers: [{questionId: 1, answer: "A"}, ...]`. Requiere cabecera `X-CSRF-Token` y cookie.
- `201`: EmailJS aceptó el mensaje; `200`: envío ya confirmado en la misma sesión; `403`: sesión/origen inválido; `409`: UUID reutilizado con otro contenido; `413`: tamaño excesivo; `415`: formato incorrecto; `422`: datos inválidos; `429`: límite de intentos o del proveedor; `503`: fallo del servicio/configuración.

No se almacenan nombres, correos ni respuestas en tablas o archivos de aplicación. La sesión conserva CSRF, tiempos de intentos y UUID/huella/fecha/confirmación durante un máximo lógico de 24 horas o hasta que expire la sesión. El bloqueo de sesión evita envíos concurrentes y reintentos repetidos después de una confirmación. Una sesión nueva o un corte entre aceptación y confirmación puede ocasionar duplicados; la referencia UUID permite reconocerlos.

El registro queda en el buzón y EmailJS procesa el contenido según la configuración de la cuenta. Los logs de aplicación omiten datos personales y secretos. La página enlaza al contacto para solicitudes de eliminación. Integrar el aviso institucional y definir la conservación del correo antes de la publicación comercial.

## Verificación

```powershell
npm.cmd test
npm.cmd run test:email
npm.cmd run test:api
npm.cmd run build:hosting
npm.cmd run test:hosting
$env:PLAYWRIGHT_BROWSERS_PATH = Join-Path (Get-Location) '.runtime/browsers'
npx.cmd playwright install chromium
npm.cmd run test:e2e
```

Las pruebas PHP cubren todos los puntajes, límites, validación, remitente/destinataria y conservación del HTML. Las pruebas API usan un transporte EmailJS simulado y verifican CSRF, errores, cuotas, reintentos y deduplicación. Las pruebas del hosting extraen el ZIP real y validan `/quiz/`, recursos, sesiones privadas y ausencia de secretos/SQL.

Playwright verifica escritorio/móvil, navegación, resultados, accesibilidad con axe y conservación de datos ante fallos. Arranca su propio servidor con el router de prueba `tests/emailjs-router.php`; las claves son ficticias y no hay correo real. Ese router no se publica ni se utiliza en `npm run dev` normal. GitHub Actions ejecuta estas pruebas sin MySQL ni Composer.

## Estructura

```text
src/                     Interfaz Astro, TypeScript y estilos
shared/quiz.json          Preguntas y reglas compartidas
public/api/              Endpoints PHP y localizador del backend
backend/submission.php   Validación y deduplicación en sesión
backend/email/           Plantilla, reporte y transporte EmailJS
backend/config.local.php Claves privadas; excluido de Git y ZIP
scripts/                 Desarrollo, diagnóstico y empaquetado
tests/                   Reglas, correo, API, hosting y navegador
docs/                    Configuración EmailJS y publicación HostGator
dist/                    Sitio generado; necesita backend privado
artifacts/               Entregas completas sin credenciales; excluidas de Git
```

Las imágenes originales usan Git LFS. Al clonar o actualizar, ejecutar `git lfs pull` y `npm.cmd ci`, conservar la configuración privada y recompilar para el destino correspondiente. Los commits no incluyen `dist`, claves, sesiones, navegadores ni artefactos de pruebas. El remoto es `https://github.com/CarlosCont04/quiz-the-money-bridge.git`.
