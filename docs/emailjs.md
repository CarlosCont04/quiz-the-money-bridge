# Configurar EmailJS para The Money Bridge

Cada envío del formulario remite el registro (nombre, correo, fecha y aceptación), las doce respuestas y el resultado a **deyanira.mariscalc@outlook.com**, desde **info@themoneybridge.com.mx**. PHP genera el HTML con la plantilla existente; EmailJS entrega el mensaje mediante el servicio conectado. No se utiliza SQL, PHPMailer ni Composer.

## 1. Autorizar la cuenta remitente

Crear o abrir la cuenta en [EmailJS](https://dashboard.emailjs.com/). En **Email Services → Add New Service**, conectar el proveedor que realmente aloja el buzón **info@themoneybridge.com.mx** y autorizar esa cuenta. El proveedor del buzón puede ser distinto de HostGator, que aloja el sitio.

Si el buzón sigue alojado en Hostinger, conectar su servicio SMTP si está disponible en la cuenta EmailJS y usar los parámetros vigentes del proveedor, no la URL del webmail. Si EmailJS no ofrece el proveedor actual, utilizar un servicio compatible que verifique y autorice `info@themoneybridge.com.mx`. Escribir esa dirección como remitente no autoriza por sí solo a enviar desde ella.

Probar la conexión desde EmailJS y copiar el **Service ID**. Las credenciales SMTP, si el servicio las requiere, se guardan en EmailJS; ya no se configuran en este proyecto.

Referencia: [conectar servicios de correo](https://www.emailjs.com/docs/user-guide/connecting-email-services/).

## 2. Crear la plantilla para el HTML existente

En **Email Templates**, crear una plantilla con estos valores:

| Campo | Valor |
| --- | --- |
| To Email | `deyanira.mariscalc@outlook.com`, escrito como dirección fija |
| From Name | `The Money Bridge` |
| From Email | Remitente predeterminado/autorizado del servicio: `info@themoneybridge.com.mx` |
| Reply-To | `{{reply_to}}` (correo validado del participante) |
| Subject | `{{subject}}` |
| CC / BCC | Vacíos |
| Contenido en el editor HTML/código | `{{{html_content}}}` |

Usar exactamente **tres llaves** para `html_content`, sin añadir otro diseño envolvente. PHP ya renderiza el documento con tablas, CSS, paleta, registro, semáforo, resultado y las doce respuestas. No pegar el archivo PHP en el panel ni usar dos llaves: eso escaparía las etiquetas y mostraría el código HTML como texto. Los valores del participante se escapan antes de incorporarlos al HTML.

El código transmite también `to_email`, `from_email`, `from_name`, `reply_name`, `request_id` y `text_content`; el servicio autorizado y los campos guardados en la plantilla determinan las cabeceras reales. Si el editor permite una versión de texto independiente, usar `{{text_content}}`; EmailJS también puede generar texto a partir del HTML. Mantener To Email fijo evita destinatarios variables.

Guardar y copiar el **Template ID**. No activar Auto-Reply: el visitante no recibe un correo automático.

Referencias: [HTML desde código](https://www.emailjs.com/docs/faq/can-i-send-html-from-my-code/) y [variables dinámicas y límite de 50 KB](https://www.emailjs.com/docs/user-guide/dynamic-variables-templates/).

## 3. Conservar el logotipo

La opción predeterminada conserva el logotipo **incrustado por CID**, como en el envío SMTP anterior:

1. En los adjuntos de la plantilla EmailJS, subir **`backend/email/assets/logo.png`** como adjunto estático.
2. Conservar exactamente el nombre **`logo.png`**: EmailJS usa el nombre del archivo como Content-ID.
3. Dejar `email_logo_url` vacío. El HTML generado utiliza `src="cid:logo.png"`.

Comprobar que el plan contratado admite adjuntos estáticos. Si no los admite, configurar una URL HTTPS pública en `email_logo_url`, por ejemplo `https://www.themoneybridge.com.mx/quiz/email-assets/logo.png`, ajustando el dominio al real. El paquete HostGator incluye esa imagen en `email-assets/logo.png`, que se extrae dentro de `quiz/`. Esta alternativa conserva el diseño, pero Outlook puede pedir permiso para descargar imágenes externas.

No insertar un `data:image/png;base64,...` en el correo: su compatibilidad varía y consume el límite de variables. Las vistas locales sí usan base64 para poder inspeccionar el diseño sin publicar imágenes.

Referencias: [imágenes incrustadas](https://www.emailjs.com/docs/user-guide/embedded-images/) y [adjuntos y disponibilidad por plan](https://www.emailjs.com/docs/user-guide/file-attachments/).

## 4. Habilitar llamadas desde PHP

En **Account → Security**, habilitar solicitudes desde aplicaciones que no sean un navegador y la autorización con clave privada. Obtener **Public Key** y **Private Key**. Esta implementación exige ambas y transmite la clave privada como `accessToken` únicamente desde PHP.

Referencias: [SDK oficial: habilitar aplicaciones de servidor](https://github.com/emailjs-com/emailjs-nodejs#usage), [opciones de autorización](https://www.emailjs.com/docs/sdk/options/) y [API REST `/send`](https://www.emailjs.com/docs/rest-api/send/).

## 5. Configurar las cuatro claves

Crear `backend/config.local.php` si no existe, copiando `backend/config.example.php`. En HostGator, el archivo estará en **`/quiz/tmb-quiz-private/backend/config.local.php`**, dentro de la única carpeta autorizada y protegido por los `.htaccess` del paquete. Antes de introducir claves, verificar que `/quiz/tmb-quiz-private/shared/quiz.json` y `/quiz/tmb-quiz-private/backend/config.example.php` devuelven **403** por HTTP. PHP los lee por filesystem, sin acceder a esas URLs.

```php
<?php
declare(strict_types=1);

return [
    'emailjs_service_id' => 'service_REEMPLAZAR',
    'emailjs_template_id' => 'template_REEMPLAZAR',
    'emailjs_public_key' => 'REEMPLAZAR_CLAVE_PUBLICA',
    'emailjs_private_key' => 'REEMPLAZAR_CLAVE_PRIVADA',
    'email_logo_url' => '', // CID: adjuntar logo.png en EmailJS.
];
```

Si ya existe una configuración local, agregar las nuevas claves sin compartir ni sobrescribir sus secretos. Las claves SQL/SMTP antiguas se ignoran. Alternativamente configurar las variables de proceso **`EMAILJS_SERVICE_ID`**, **`EMAILJS_TEMPLATE_ID`**, **`EMAILJS_PUBLIC_KEY`**, **`EMAILJS_PRIVATE_KEY`** y, opcionalmente, `EMAIL_LOGO_URL`. Tienen prioridad sobre el archivo local, incluso si su valor es vacío.

PHP no carga los archivos `.env` de Astro. No colocar estas claves en `PUBLIC_*`, JavaScript, Git ni archivos accesibles por HTTP. En HostGator se guardan únicamente en `/quiz/tmb-quiz-private/backend/config.local.php`, protegido por Apache. No es necesario recompilar para cambiar las claves del archivo privado.

PHP requiere **8.2 o posterior**, `curl`, `mbstring` y acceso HTTPS saliente a `api.emailjs.com:443`. Se verifica el certificado TLS. Ante errores de certificados en XAMPP, configurar un almacén CA válido en `curl.cainfo` del `php.ini` utilizado; no desactivar la verificación TLS.

## 6. Comprobar diseño y entrega

```powershell
npm.cmd run email:status   # Diagnóstico de configuración sin mostrar claves ni enviar.
npm.cmd run email:preview  # Tres muestras HTML/TXT locales; no envía.
npm.cmd run email:test     # ENVÍO REAL de una participación ficticia a la plantilla configurada.
```

`email:test` marca el asunto `[PRUEBA]`. La plantilla de producción debe tener como destinataria fija a Deyanira. Si se necesita verificar primero en otro buzón, usar una copia temporal de la plantilla con ese destinatario fijo y luego restablecer el Template ID de Deyanira; el campo guardado en EmailJS prevalece sobre `to_email`.

Confirmar en Outlook el remitente **info@themoneybridge.com.mx**, el registro, el resultado, las doce respuestas, los acentos, el logotipo y el botón de respuesta al participante. Verificar también Spam y el historial de EmailJS. Después responder un quiz desde `/quiz/` para comprobar el flujo completo del hosting.

La confirmación de la API acredita aceptación, no llegada a la bandeja de entrada. Sin SQL no hay cola persistente ni reintentos automáticos: si el proveedor falla, el usuario recibe un error y puede volver a enviar conservando sus datos en la página. Un corte de conexión después de la aceptación puede ocasionar un duplicado; la referencia UUID del correo ayuda a identificarlo.

EmailJS documenta un límite de una solicitud por segundo en `/send`. El sitio informa de un 429 para que el visitante pueda reintentar. Vigilar además la cuota del plan y los límites del servicio conectado.

## Diagnóstico de fallos

| Indicación | Revisar |
| --- | --- |
| `configuracion_o_conexion_1005` | Faltan una o más claves EmailJS; `email:status` indica cuáles, sin mostrar sus valores. |
| `configuracion_o_conexion_1006` | La extensión `curl` de PHP no está habilitada. |
| `configuracion_o_conexion_1007` | Acceso HTTPS, certificados CA y conectividad saliente del hosting. |
| `configuracion_o_conexion_1008` | El contenido supera el límite de variables; revisar cambios de preguntas/plantilla. |
| `configuracion_o_conexion_1009` | `email_logo_url` debe quedar vacío o usar una URL HTTPS válida. |
| `emailjs_http_400`, `401` o `403` | IDs, claves, seguridad para aplicaciones de servidor, autorización y servicio conectado en el panel. |
| `emailjs_http_429` | Límite por segundo o cuota; consultar el panel y reintentar. |
| `emailjs_http_5xx` | Problema temporal del proveedor; consultar el historial y reintentar. |

El diagnóstico local no puede verificar los campos privados guardados en el panel. Es necesario comprobar el servicio, la plantilla y una entrega real con las claves de la cuenta.
