# Desplegar The Money Bridge en HostGator bajo /quiz/

El despliegue utiliza la misma separación pública/privada que `quiz-tienda`, con una carpeta privada propia para evitar mezclar configuraciones. No se requiere Node, Composer, MySQL ni un worker en HostGator. El servidor ejecuta PHP 8.2 o posterior con `curl`, `mbstring`, sesiones y acceso HTTPS a EmailJS.

## 1. Generar y verificar la entrega

En el equipo de desarrollo, desde la raíz del proyecto:

```powershell
npm.cmd ci
npm.cmd test
npm.cmd run test:email
npm.cmd run test:api
npm.cmd run build:hosting
npm.cmd run test:hosting
```

`build:hosting` compila con **`PUBLIC_BASE_PATH=/quiz/`**, verifica las rutas de recursos/API y crea una entrega nueva en **`artifacts/hosting-FECHA/quiz-hosting.zip`**. El archivo **`artifacts/latest-hosting.json`** indica cuál es la última entrega. Las pruebas extraen y sirven el ZIP real bajo `/quiz/`, sin enviar correos.

El ZIP incluye todos los archivos públicos, backend, cuestionario, logotipo y estas guías, pero **no contiene claves**, datos de participantes, sesiones locales, SQL, dependencias ni herramientas de prueba. No publicar solamente `dist/`: sus endpoints necesitan los archivos privados.

## 2. Preparar las carpetas en cPanel

Activar PHP 8.2 o posterior y las extensiones necesarias en el selector de PHP del dominio. Mostrar los archivos ocultos en el Administrador de archivos de cPanel para copiar los `.htaccess`.

La estructura final esperada para un dominio con raíz `public_html` es:

```text
/home/USUARIO/
├── public_html/
│   └── quiz/
│       ├── index.html
│       ├── .htaccess
│       ├── _astro/
│       ├── email-assets/logo.png
│       └── api/
│           ├── .htaccess
│           ├── bootstrap.php
│           ├── private-root.php
│           ├── session.php
│           └── submit.php
└── tmb-quiz-private/
    ├── .htaccess
    ├── backend/
    │   ├── config.example.php
    │   ├── config.local.php     ← crear solo en el hosting
    │   ├── config.php
    │   ├── http.php
    │   ├── quiz.php
    │   ├── submission.php
    │   └── email/
    ├── shared/quiz.json
    └── .runtime/sessions/
```

Extraer el ZIP en una carpeta de preparación de la cuenta y copiar **el contenido de `public_html/quiz/`** a la carpeta `/quiz/` de la raíz pública del dominio. Copiar **`tmb-quiz-private/`** al directorio de la cuenta, fuera de la raíz pública. No extraer todo dentro de `public_html/quiz`: dejaría el backend en un lugar incorrecto y produciría carpetas públicas anidadas.

No subir el `.htaccess` de la raíz del repositorio; sirve para XAMPP y reescribe a `dist/`. El ZIP ya incluye el `.htaccess` adecuado para la página compilada. Si el sitio principal utiliza WordPress u otras reglas, comprobar que los archivos/directorios existentes de `/quiz/` se atiendan antes de su regla general.

## 3. Ajustar la ruta privada cuando sea necesario

El paquete genera `public_html/quiz/api/private-root.php` con:

```php
<?php
return dirname(__DIR__, 3) . '/tmb-quiz-private';
```

Esa ruta corresponde a `/home/USUARIO/public_html/quiz/api` y `/home/USUARIO/tmb-quiz-private`. Si el dominio adicional/subdominio utiliza otra raíz pública, reemplazarla por la ruta absoluta privada real:

```php
<?php
return '/home/USUARIO/tmb-quiz-private';
```

No usar una URL HTTPS como ruta privada ni colocar la carpeta de claves dentro de `/quiz/`. PHP debe poder leer los archivos de `tmb-quiz-private` y escribir en `.runtime/sessions`. Usar los permisos mínimos compatibles con la cuenta (normalmente carpetas 755 y archivos 644; sesiones/configuración privada más restrictivas si PHP lo admite); no asignar 777.

## 4. Configurar EmailJS en el servidor

Seguir la guía `docs/emailjs.md` en el repositorio o el archivo `CONFIGURAR-EMAILJS.md` incluido en el ZIP. Crear **`tmb-quiz-private/backend/config.local.php`** a partir del ejemplo y completar las cuatro claves EmailJS. Configurar el logotipo CID mediante el adjunto estático `logo.png`, o establecer `email_logo_url` con la URL HTTPS de `/quiz/email-assets/logo.png`.

No hace falta crear una base, importar SQL, instalar Composer ni programar una cola de correo. El paquete no contiene el antiguo worker. Si una instalación anterior tenía un proceso o tarea de reintento SMTP, detener esa tarea para que no continúe ejecutando el código anterior.

## 5. Verificar la instalación

1. Abrir **`https://DOMINIO/quiz/`**: comprobar imágenes, estilos y avance entre preguntas.
2. Abrir **`https://DOMINIO/quiz/api/session.php`**: debe devolver JSON con `csrfToken`, nunca una ruta de archivos ni un aviso PHP.
3. Responder un quiz con datos de prueba y consentimiento: debe aparecer el resultado después de la aceptación de EmailJS.
4. Verificar en Outlook el mensaje recibido por **deyanira.mariscalc@outlook.com**, desde **info@themoneybridge.com.mx**, con registro, resultado, las doce respuestas y el logotipo.
5. Comprobar escritorio y móvil. La configuración privada debe permanecer fuera de la raíz pública y los accesos directos a `api/private-root.php`/`bootstrap.php` deben devolver 403 con Apache.

Un GET a `submit.php` devuelve 405: ese endpoint solo acepta POST. Para un envío fallido, revisar los códigos del registro de errores PHP y el historial de EmailJS. El sitio no expone las respuestas completas del proveedor ni las claves.

## Actualizaciones

Guardar una copia de la instalación actual y la configuración privada. Generar otra entrega con `build:hosting`, verificarla y sustituir los archivos públicos y privados del código. Conservar **`config.local.php`**, la ruta privada adaptada de `api/private-root.php` y las sesiones de la instalación. No mezclar PHP de una entrega con backend de otra. El paquete no incluye `config.local.php`, por lo que una copia normal de archivos no lo reemplaza.

Los registros de la antigua base SQL no se modifican ni se migran. Los envíos nuevos quedan en el buzón y son procesados por EmailJS. No hay cola local persistente: el usuario debe reintentar ante errores, y los reintentos confirmados se deduplican únicamente dentro de su sesión vigente.

No se ha publicado automáticamente en la cuenta de HostGator. La entrega requiere configurar las claves y copiar los archivos a la cuenta real siguiendo estos pasos.
