# Quiz The Money Bridge

Proyecto independiente del sitio principal y del evento de The Money Bridge. Incluye el quiz de Deyanira Mariscal, CEO de The Money Bridge, con 12 preguntas, registro de nombre y correo, almacenamiento en SQL y resultado en una ventana emergente.

## Ejecutar en este equipo

Requiere Node.js 22.12 o posterior, PHP 8.2 con `pdo_mysql` y `mbstring`, y MySQL 8 o MariaDB 10.4 de XAMPP.

```powershell
npm.cmd install
# Iniciar MySQL desde XAMPP.
npm.cmd run db:setup
npm.cmd run dev
```

Abrir **http://127.0.0.1:4321/**. Un solo comando de desarrollo inicia Astro/Vite en 4321 y PHP en 8080; Vite redirige `/api/` al servidor PHP. `PHP_BINARY` permite indicar una instalación diferente de PHP. En Windows se detecta `C:/xampp/php/php.exe`.

La configuración local de este equipo utiliza `127.0.0.1:3308`, base `quiz_money_bridge`. Las credenciales están en `backend/config.local.php`, excluido de Git. En un clon nuevo, copiar `backend/config.example.php` a `backend/config.local.php` y ajustar puerto y credenciales. También se admiten `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER` y `DB_PASSWORD` como variables de entorno; tienen prioridad sobre el archivo local.

`npm run db:setup` crea únicamente la base configurada y las tablas que no existan; conserva los registros existentes. En un servidor con una base ya provisionada, importar `database/schema.sql` dentro de esa base.

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

Iniciar Apache y MySQL en XAMPP. La URL puede requerir el puerto configurado de Apache. El `.htaccess` de la raíz publica `dist/` y bloquea los directorios internos. Requiere `mod_rewrite` y `AllowOverride All`. Si el proyecto se publica en un dominio propio, compilar con base `/` y configurar el `DocumentRoot` directamente en `dist/`. Conservar `backend/` y `shared/` como directorios hermanos de `dist/`: los endpoints PHP los utilizan en ejecución. PHP necesita permisos de escritura en `.runtime/sessions/`.

Usar HTTPS y un usuario SQL propio con permisos `SELECT`, `INSERT` sobre las tablas para el servicio público. Reservar las credenciales de creación de esquema para instalación y mantenimiento. La base y las sesiones no deben ser públicas. El límite de 30 intentos por sesión cada 15 minutos es una protección básica; para una campaña pública, configurar límites adicionales en el servidor frontal. No hay un panel público de prospectos.

## Contenido y cálculo

`shared/quiz.json` es la única fuente del contenido. Conserva las 12 preguntas, opciones y tres descripciones de `QUIZ.docx`. La puntuación se determina en PHP: A = 0, B = 1, C = 2; máximo 24. No se muestran puntos junto a las respuestas.

| Puntuación | Semáforo | Resultado |
| --- | --- | --- |
| 0–8 | Rojo | Tu dinero necesita atención |
| 9–16 | Amarillo | Tu dinero puede dar más |
| 17–24 | Verde | Tu dinero está listo para crecer |

El resultado aparece después de confirmar la transacción SQL y contiene “Gracias por responder el quiz”. Se puede cerrar con Escape, reabrir o iniciar otro quiz. Si hay un fallo, las respuestas permanecen en memoria para reintentar. Recargar o cerrar la página descarta respuestas todavía no enviadas. El sitio no envía correos ni suscribe a campañas; el enlace de contacto abre el cliente de correo del visitante.

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

El servidor valida las 12 respuestas únicas, calcula el puntaje desde sus valores, utiliza sentencias preparadas PDO y guarda las dos tablas en una transacción. El UUID único permite repetir un envío sin duplicarlo. El puntaje y el color enviados por un cliente se ignoran. No se exponen correos ni respuestas mediante endpoints de consulta. Los errores públicos no incluyen detalles SQL y los logs de aplicación omiten datos personales.

`quiz_submissions` conserva nombre, correo, puntaje, semáforo, resultado calculado, versiones de quiz/aceptación, fecha y hashes del envío/sesión. `quiz_answers` conserva una fila por pregunta. Para revisión interna, consultar estas tablas con phpMyAdmin o una herramienta SQL autorizada. El administrador puede atender solicitudes de eliminación usando el correo registrado; la eliminación de una participación elimina sus respuestas por clave foránea.

El texto “Uso de tus datos” explica la captura y enlaza al contacto que figura en el sitio del evento. Antes de una publicación comercial, The Money Bridge debe proporcionar su aviso de privacidad institucional y definir su política de conservación para integrar esa información.

## Verificación

```powershell
npm.cmd test
npm.cmd run build
$env:PLAYWRIGHT_BROWSERS_PATH = Join-Path (Get-Location) '.runtime/browsers'
npx.cmd playwright install chromium
npm.cmd run test:e2e
```

Las pruebas PHP cubren todos los puntajes de 0 a 24, límites de color, datos inválidos y manipulación del resultado. Playwright verifica escritorio y móvil, validación, retroceso, tres resultados, accesibilidad con axe, reintentos, CSRF y persistencia real en SQL. Las pruebas utilizan correos `quiz-test-…@example.invalid` y eliminan exclusivamente sus registros al terminar. Requieren una base de pruebas o la base local de desarrollo, nunca una base de producción.

Para verificar la versión compilada que sirve Apache, establecer `E2E_BASE_URL=http://localhost/quiz-the-money-bridge/` antes de ejecutar las pruebas. En ese modo no se inicia el servidor de desarrollo.

## Git y GitHub

El repositorio remoto configurado es `https://github.com/CarlosCont04/quiz-the-money-bridge.git`. Las imágenes originales se gestionan con Git LFS; código, CSS, JSON y lockfile se gestionan con Git. `dist`, credenciales, sesiones, navegadores y resultados de pruebas están excluidos.

```powershell
git lfs install
git clone https://github.com/CarlosCont04/quiz-the-money-bridge.git
cd quiz-the-money-bridge
git lfs pull
npm.cmd ci
```

Referencias de implementación: [instalación oficial de Astro](https://docs.astro.build/en/install-and-setup/) y [sentencias preparadas de PHP PDO](https://www.php.net/manual/en/pdo.prepared-statements.php).
