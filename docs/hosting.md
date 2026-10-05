# HostGator: instalación completa exclusivamente dentro de /quiz/

Toda la entrega queda dentro de la única carpeta autorizada **`quiz/`**, incluida **`quiz/tmb-quiz-private/`**. No hay que crear carpetas hermanas, modificar el sitio principal ni escribir fuera de `quiz/`. La carpeta privada contiene backend, configuración y sesiones: se bloquea por HTTP mediante dos `.htaccess`, aunque PHP puede leerla por filesystem.

Requiere Apache 2.4 con `mod_rewrite` y soporte de `.htaccess`, PHP 8.2 o posterior con `curl`, `mbstring`, sesiones y acceso HTTPS a EmailJS. No necesita Node, Composer, SQL ni un worker en el hosting.

## 1. Generar y verificar el ZIP

Desde el proyecto local:

```powershell
npm.cmd run build:hosting
npm.cmd run test:hosting
npm.cmd run test:hosting:apache
```

El paquete aparece en **`artifacts/hosting-FECHA/quiz-hosting.zip`**; `artifacts/latest-hosting.json` indica la entrega más reciente. La compilación utiliza `/quiz/` para recursos y API. El ZIP contiene directamente **el contenido de quiz/**, sin carpetas contenedoras `public_html/` o `quiz/` y sin claves, datos locales, SQL ni dependencias.

`test:hosting` extrae el ZIP en una instalación temporal y comprueba las rutas, la lectura del backend y las sesiones. `test:hosting:apache` inicia un Apache temporal, sin modificar la instalación existente, y comprueba los bloqueos HTTP reales con fixtures sin credenciales. Detecta Apache de XAMPP en Windows y `/usr/sbin/apache2` en Linux; se puede configurar `APACHE_BINARY`, `APACHE_SERVER_ROOT` y `APACHE_MODULES_DIR` si la instalación utiliza otras rutas.

## 2. Subir y extraer únicamente en quiz/

En el Administrador de archivos de cPanel, mostrar los archivos ocultos. Subir el ZIP **dentro de la carpeta `quiz/` existente** y extraerlo **en esa misma carpeta**. No crear otra carpeta `quiz/` dentro ni extraerlo en la raíz del sitio. Si se utiliza FTP, copiar todos los archivos del directorio `package/` generado directamente a `quiz/`, incluyendo `.htaccess` y `tmb-quiz-private/`.

La estructura final es:

```text
quiz/
├── index.html
├── .htaccess
├── _astro/
├── email-assets/logo.png
├── api/
│   ├── .htaccess
│   ├── bootstrap.php
│   ├── private-root.php
│   ├── session.php
│   └── submit.php
├── tmb-quiz-private/
│   ├── .htaccess
│   ├── backend/
│   │   ├── config.example.php
│   │   ├── config.local.php   ← crear después de comprobar el bloqueo HTTP
│   │   ├── config.php
│   │   ├── http.php
│   │   ├── quiz.php
│   │   ├── submission.php
│   │   └── email/
│   ├── shared/quiz.json
│   └── .runtime/sessions/
├── INSTRUCCIONES-HOSTING.md
├── CONFIGURAR-EMAILJS.md
└── VERSION.json
```

Retirar el ZIP subido de `quiz/` después de extraerlo; conservar la copia local. No subir el `.htaccess` de la raíz del repositorio, que es para XAMPP y redirige a `dist/`: el paquete ya incluye el archivo correcto. Las guías y `VERSION.json` del paquete también se bloquean por HTTP y se consultan localmente o desde el Administrador de archivos.

## 3. Comprobar los bloqueos antes de configurar claves

El `.htaccess` de `quiz/` contiene una regla que bloquea `tmb-quiz-private/` y cualquier subruta. Dentro de la propia carpeta privada, otro `.htaccess` aplica **`Require all denied`**. No eliminar ninguno ni convertirlos en archivos `.htaccess.txt`.

Antes de introducir las claves, comprobar que estas URLs responden **403 Forbidden**:

```text
https://DOMINIO/quiz/tmb-quiz-private/shared/quiz.json
https://DOMINIO/quiz/tmb-quiz-private/backend/config.example.php
https://DOMINIO/quiz/tmb-quiz-private/.runtime/sessions/
https://DOMINIO/quiz/api/private-root.php
```

Si una ruta muestra contenido o devuelve 200, no configurar todavía las claves: revisar que los `.htaccess` estén presentes y que el hosting los interprete. Si devuelve 500, revisar en el log PHP/Apache la disponibilidad de `mod_rewrite` y las directivas permitidas. Si el servicio no permite los bloqueos `.htaccess`, se necesita que el proveedor habilite esa protección antes de guardar secretos en esta estructura. No se resuelve publicando la carpeta privada sin protección.

Referencia: [Require all denied en Apache 2.4](https://httpd.apache.org/docs/2.4/mod/mod_authz_core.html#require).

## 4. Ruta del backend y configuración EmailJS

`quiz/api/private-root.php` se genera con esta ruta relativa:

```php
<?php
return dirname(__DIR__) . '/tmb-quiz-private';
```

Funciona independientemente de la raíz del dominio: no exige modificar rutas fuera de `quiz/`. No usar una URL HTTP como ruta del backend.

Seguir `CONFIGURAR-EMAILJS.md` incluido en el ZIP o `docs/emailjs.md` del repositorio. Crear **`quiz/tmb-quiz-private/backend/config.local.php`** a partir del ejemplo y completar las cuatro claves. Conservar el logotipo CID adjuntando `logo.png` a la plantilla EmailJS, o configurar `email_logo_url` con la URL HTTPS de `/quiz/email-assets/logo.png`.

PHP debe poder leer el backend y escribir en **`quiz/tmb-quiz-private/.runtime/sessions/`**. Usar los permisos mínimos admitidos por la cuenta; no asignar 777. No hace falta importar SQL, instalar Composer ni crear tareas fuera de `quiz/`.

## 5. Verificar el funcionamiento

1. Abrir **`https://DOMINIO/quiz/`** y comprobar estilos, imágenes y navegación.
2. Abrir **`https://DOMINIO/quiz/api/session.php`**: debe devolver JSON con `csrfToken`, sin avisos PHP ni rutas internas.
3. Responder un quiz de prueba: el resultado aparece después de la aceptación de EmailJS.
4. Confirmar en Outlook la recepción en **deyanira.mariscalc@outlook.com**, desde **info@themoneybridge.com.mx**, con registro, resultado, doce respuestas y logotipo.
5. Repetir en móvil y confirmar de nuevo los bloqueos 403, incluyendo `/quiz/tmb-quiz-private/backend/config.local.php`.

Un GET a `submit.php` devuelve 405 porque solo acepta POST. Para fallos de envío, revisar el historial de EmailJS y los códigos del log del hosting; el sitio no muestra secretos ni respuestas completas del proveedor.

## Actualizar una instalación

Hacer una copia de la instalación dentro del ámbito permitido o descargarla al equipo local. Extraer la nueva entrega únicamente en `quiz/` y conservar **`quiz/tmb-quiz-private/backend/config.local.php`** y las sesiones: el ZIP no incluye la configuración local. Sustituir juntos API y backend, y verificar los bloqueos otra vez. La ruta nueva de `private-root.php` ya es relativa al interior de `quiz/` y debe reemplazar la versión anterior que apuntaba fuera.

Usar esta entrega nueva, no los ZIP anteriores con `public_html/quiz/` y una carpeta privada hermana. No se ha publicado automáticamente en HostGator. Los registros antiguos de SQL no se modifican y no hay cola persistente de correo; los reintentos se deduplican únicamente dentro de la sesión vigente.
