import { test, before, after } from 'node:test';
import assert from 'node:assert/strict';
import { spawn, spawnSync } from 'node:child_process';
import { existsSync, mkdirSync, mkdtempSync, readdirSync, readFileSync, renameSync, rmSync, writeFileSync } from 'node:fs';
import { resolve, sep } from 'node:path';
import { randomUUID } from 'node:crypto';
import { setTimeout } from 'node:timers/promises';
import { once } from 'node:events';
import { createServer } from 'node:net';

const php = process.env.PHP_BINARY || (existsSync('C:/xampp/php/php.exe') ? 'C:/xampp/php/php.exe' : 'php');
let directory, quizDirectory, sourceDirectory, server, base, serverError, logs = '';
function filesIn(root, prefix = '') {
  return readdirSync(resolve(root, prefix), { withFileTypes: true }).flatMap(entry => {
    const path = prefix ? `${prefix}/${entry.name}` : entry.name;
    return entry.isDirectory() ? filesIn(root, path) : [path];
  }).sort();
}
before(async () => {
  assert.ok(existsSync('artifacts/latest-hosting.json'), 'Ejecuta npm run build:hosting antes de esta prueba.');
  const release = JSON.parse(readFileSync('artifacts/latest-hosting.json', 'utf8'));
  sourceDirectory = release.directory;
  mkdirSync('.runtime', { recursive: true });
  directory = mkdtempSync(resolve('.runtime/hosting-test-'));
  quizDirectory = resolve(directory, 'public_html/quiz');
  mkdirSync(quizDirectory, { recursive: true });
  const listing = process.platform === 'win32'
    ? spawnSync('tar.exe', ['-tf', release.archive], { encoding: 'utf8' })
    : spawnSync('unzip', ['-Z1', release.archive], { encoding: 'utf8' });
  assert.equal(listing.status, 0, listing.stderr || listing.error?.message);
  for (const name of listing.stdout.trim().split(/\r?\n/)) {
    assert.ok(!/(^|\/)\.{1,2}(\/|$)|\\/.test(name), `Ruta incompatible en ZIP: ${name}`);
    assert.ok(!/^(\/|[a-z]:)/i.test(name), `Ruta absoluta en ZIP: ${name}`);
  }
  // Comprobar el ZIP real con un extractor distinto al utilizado para comprimir.
  const extraction = process.platform === 'win32'
    ? spawnSync('powershell.exe', ['-NoProfile', '-NonInteractive', '-Command',
      '$ErrorActionPreference = "Stop"; Add-Type -AssemblyName System.IO.Compression.FileSystem; [IO.Compression.ZipFile]::ExtractToDirectory($env:QUIZ_TEST_ARCHIVE, $env:QUIZ_TEST_EXTRACT)'], {
      encoding: 'utf8', env: { ...process.env, QUIZ_TEST_ARCHIVE: release.archive, QUIZ_TEST_EXTRACT: quizDirectory },
    })
    : spawnSync('unzip', ['-q', release.archive, '-d', quizDirectory], { encoding: 'utf8' });
  assert.equal(extraction.status, 0, extraction.stderr || extraction.error?.message);
  const probe = createServer();
  probe.listen(0, '127.0.0.1');
  await once(probe, 'listening');
  const port = probe.address().port;
  await new Promise(resolve => probe.close(resolve));
  base = `http://127.0.0.1:${port}`;
  // Usar PHP empaquetado sin router simulado. Claves vacías impiden correos reales.
  server = spawn(php, ['-d', 'display_errors=0', '-S', `127.0.0.1:${port}`, '-t', resolve(directory, 'public_html')], {
    env: { ...process.env, EMAILJS_SERVICE_ID: '', EMAILJS_TEMPLATE_ID: '', EMAILJS_PUBLIC_KEY: '', EMAILJS_PRIVATE_KEY: '', EMAIL_LOGO_URL: '' },
    stdio: ['ignore', 'ignore', 'pipe'],
  });
  server.stderr.on('data', data => { logs += data.toString(); });
  server.on('error', error => { serverError = error; });
  server.on('exit', code => { serverError = new Error(`PHP terminó: ${code}`); });
  for (let i = 0; i < 50; i++) {
    if (serverError) throw serverError;
    try {
      const response = await fetch(`${base}/quiz/`);
      await response.arrayBuffer();
      if (response.ok) return;
    } catch {}
    await setTimeout(100);
  }
  throw new Error('No inició el servidor PHP para el ZIP.');
});
after(async () => {
  if (server && server.exitCode === null) { const stopped = once(server, 'exit'); server.kill(); await stopped; }
  // Solo retirar la extracción temporal creada por la prueba, nunca una entrega.
  if (directory && directory.startsWith(resolve('.runtime') + sep)) rmSync(directory, { recursive: true, force: true });
});
test('el ZIP extraído contiene todos los archivos, incluidos ocultos, y excluye secretos y SQL', () => {
  const expected = filesIn(sourceDirectory);
  const actual = filesIn(quizDirectory).filter(path => !path.startsWith('tmb-quiz-private/.runtime/sessions/sess_'));
  assert.deepEqual(actual, expected);
  for (const path of expected) {
    assert.deepEqual(readFileSync(resolve(quizDirectory, path)), readFileSync(resolve(sourceDirectory, path)), path);
    assert.ok(!/config\.local\.php|\.env$|\.sql$|vendor\/|node_modules\/|tests\//.test(path), path);
  }
  for (const path of ['.htaccess', 'api/.htaccess', 'tmb-quiz-private/.htaccess', 'tmb-quiz-private/.runtime/sessions/.gitkeep', 'CONFIGURAR-EMAILJS.md']) assert.ok(expected.includes(path), path);
  assert.deepEqual(readFileSync(resolve(quizDirectory, 'email-assets/logo.png')), readFileSync('backend/email/assets/logo.png'));
  assert.ok(!expected.some(path => path.startsWith('public_html/') || path.startsWith('quiz/')));
  assert.deepEqual(readdirSync(resolve(directory, 'public_html')), ['quiz']);
  assert.deepEqual(readdirSync(directory), ['public_html']);
});
test('la página usa /quiz/api/ y sus recursos públicos existen', async () => {
  const page = await fetch(`${base}/quiz/`);
  assert.equal(page.status, 200);
  const html = await page.text();
  assert.match(html, /data-api="\/quiz\/api\/"/);
  const assets = [...html.matchAll(/(?:src|href)="(\/quiz\/_astro\/[^\"]+)"/g)];
  assert.ok(assets.length >= 3);
  for (const [, path] of assets) {
    const response = await fetch(base + path);
    assert.equal(response.status, 200, path);
    assert.ok((await response.arrayBuffer()).byteLength > 0, path);
  }
  const logo = await fetch(`${base}/quiz/email-assets/logo.png`);
  assert.equal(logo.status, 200);
  assert.ok((await logo.arrayBuffer()).byteLength > 0);
});
test('la instalación conserva CSRF y guarda únicamente metadatos en la sesión privada', async () => {
  const first = await fetch(`${base}/quiz/api/session.php`);
  assert.equal(first.status, 200);
  assert.match(first.headers.get('cache-control'), /no-store/);
  const token = (await first.json()).csrfToken;
  assert.match(token, /^[a-f0-9]{64}$/);
  const cookie = first.headers.get('set-cookie');
  assert.match(cookie, /HttpOnly/i);
  const second = await fetch(`${base}/quiz/api/session.php`, { headers: { Cookie: cookie.split(';')[0] } });
  assert.equal((await second.json()).csrfToken, token);
  const sessionId = cookie.match(/tmb_quiz_session=([^;]+)/)[1];
  assert.ok(existsSync(resolve(quizDirectory, 'tmb-quiz-private/.runtime/sessions', `sess_${sessionId}`)));
  assert.equal((await fetch(`${base}/quiz/api/submit.php`)).status, 405);
});
test('un registro válido alcanza la configuración privada sin simular un envío correcto', async () => {
  const identity = await fetch(`${base}/quiz/api/session.php`);
  const { csrfToken } = await identity.json();
  const cookie = identity.headers.get('set-cookie');
  const response = await fetch(`${base}/quiz/api/submit.php`, {
    method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken, Cookie: cookie.split(';')[0] },
    body: JSON.stringify({ requestId: randomUUID(), name: 'Participante de instalación', email: 'quiz-test-hosting@example.invalid', consent: true, website: '',
      answers: Array.from({ length: 12 }, (_, i) => ({ questionId: i + 1, answer: 'B' })) }),
  });
  assert.equal(response.status, 503);
  assert.match((await response.json()).message, /No pudimos confirmar/);
  assert.match(logs, /code=1005/);
  const sessionId = cookie.match(/tmb_quiz_session=([^;]+)/)[1];
  const metadata = readFileSync(resolve(quizDirectory, 'tmb-quiz-private/.runtime/sessions', `sess_${sessionId}`), 'utf8');
  for (const personal of ['Participante de instalación', 'quiz-test-hosting@example.invalid', 'questionId', 'answers', 'full_name']) assert.ok(!metadata.includes(personal));
  assert.ok(metadata.includes('email_requests'));
});
test('el backend se encuentra dentro de quiz y el paquete contiene bloqueos Apache', async () => {
  assert.ok(!existsSync(resolve(quizDirectory, 'tmb-quiz-private/backend/config.local.php')));
  assert.match(readFileSync(resolve(quizDirectory, 'tmb-quiz-private/.htaccess'), 'utf8'), /Require all denied/);
  assert.ok(readFileSync(resolve(quizDirectory, '.htaccess'), 'utf8').includes('RewriteRule ^tmb-quiz-private(?:/|$) - [F,END,NC]'));
  // PHP -S no interpreta .htaccess. El bloqueo HTTP se comprueba con Apache real.
  for (const path of ['/tmb-quiz-private/shared/quiz.json', '/quiz/backend/config.local.php', '/quiz/shared/quiz.json', '/quiz/.runtime/sessions/']) assert.equal((await fetch(base + path)).status, 404, path);
});
test('una instalación incompleta devuelve JSON y una causa sin filtrar rutas', async () => {
  const file = resolve(quizDirectory, 'tmb-quiz-private/backend/http.php');
  renameSync(file, file + '.test-backup');
  try {
    const response = await fetch(`${base}/quiz/api/session.php`);
    assert.equal(response.status, 503);
    const body = await response.json();
    assert.ok(!JSON.stringify(body).includes(directory));
    assert.match(logs, /setup: private_files_missing/);
  } finally { renameSync(file + '.test-backup', file); }
});
test('un directorio de sesiones inutilizable no entrega un token falso', async () => {
  const sessions = resolve(quizDirectory, 'tmb-quiz-private/.runtime/sessions');
  renameSync(sessions, sessions + '.test-backup');
  writeFileSync(sessions, 'archivo que impide crear el directorio');
  try {
    const response = await fetch(`${base}/quiz/api/session.php`);
    assert.equal(response.status, 503);
    assert.equal((await response.json()).csrfToken, undefined);
    assert.match(logs, /code=1001/);
  } finally {
    rmSync(sessions);
    renameSync(sessions + '.test-backup', sessions);
  }
});
