import { test, before, after } from 'node:test';
import assert from 'node:assert/strict';
import { spawn, spawnSync } from 'node:child_process';
import { existsSync, mkdirSync, mkdtempSync, readFileSync, writeFileSync, rmSync } from 'node:fs';
import { resolve, sep } from 'node:path';
import { randomUUID } from 'node:crypto';
import { setTimeout } from 'node:timers/promises';
import { once } from 'node:events';
import { createServer } from 'node:net';

const php = process.env.PHP_BINARY || (existsSync('C:/xampp/php/php.exe') ? 'C:/xampp/php/php.exe' : 'php');
const quiz = JSON.parse(readFileSync(new URL('../shared/quiz.json', import.meta.url), 'utf8'));
const fakeEnv = { EMAILJS_SERVICE_ID: 'service_test', EMAILJS_TEMPLATE_ID: 'template_test', EMAILJS_PUBLIC_KEY: 'public_test', EMAILJS_PRIVATE_KEY: 'private_test', EMAIL_LOGO_URL: '' };
let directory, server, base, serverError;
const mode = value => writeFileSync(resolve(directory, 'mode'), value);
const deliveries = () => {
  const path = resolve(directory, 'requests.jsonl');
  return existsSync(path) ? readFileSync(path, 'utf8').trim().split('\n').filter(Boolean).map(JSON.parse) : [];
};
async function session() {
  const response = await fetch(`${base}session.php`);
  assert.equal(response.status, 200);
  const cookie = response.headers.get('set-cookie');
  assert.match(cookie, /HttpOnly/i);
  assert.match(cookie, /SameSite=Lax/i);
  return { token: (await response.json()).csrfToken, cookie: cookie.split(';')[0] };
}
const payload = answer => ({
  requestId: randomUUID(), name: 'PRUEBA José & María', email: 'quiz-test@example.invalid', consent: true, website: '',
  answers: Array.from({ length: 12 }, (_, i) => ({ questionId: i + 1, answer })),
});
const submit = (identity, body, headers = {}) => fetch(`${base}submit.php`, {
  method: 'POST', headers: { 'Content-Type': 'application/json', Cookie: identity.cookie, 'X-CSRF-Token': identity.token, ...headers },
  body: typeof body === 'string' ? body : JSON.stringify(body),
});
before(async () => {
  mkdirSync('.runtime', { recursive: true });
  directory = mkdtempSync(resolve('.runtime/emailjs-test-'));
  mode('ok');
  const probe = createServer();
  probe.listen(0, '127.0.0.1');
  await once(probe, 'listening');
  const port = probe.address().port;
  await new Promise(resolve => probe.close(resolve));
  base = `http://127.0.0.1:${port}/api/`;
  server = spawn(php, ['-d', 'display_errors=0', '-S', `127.0.0.1:${port}`, '-t', 'public', 'tests/emailjs-router.php'],
    { env: { ...process.env, ...fakeEnv, QUIZ_TEST_DIRECTORY: directory }, stdio: ['ignore', 'ignore', 'pipe'] });
  server.stderr.on('data', () => {});
  server.on('error', error => { serverError = error; });
  server.on('exit', code => { serverError = new Error(`PHP terminó: ${code}`); });
  for (let i = 0; i < 50; i++) {
    if (serverError) throw serverError;
    try { await session(); return; } catch { await setTimeout(100); }
  }
  throw new Error('No inició PHP de pruebas.');
});
after(async () => {
  if (server && server.exitCode === null) { const stopped = once(server, 'exit'); server.kill(); await stopped; }
  if (directory && directory.startsWith(resolve('.runtime') + sep)) rmSync(directory, { recursive: true, force: true });
});
test('envía registro, doce respuestas y tres resultados, ignorando destinatario y puntaje del cliente', async () => {
  for (const [answer, key, score] of [['A', 'red', 0], ['B', 'yellow', 12], ['C', 'green', 24]]) {
    const input = payload(answer);
    const response = await submit(await session(), { ...input, score: 0, result: 'red', to_email: 'intruso@example.com' });
    assert.equal(response.status, 201);
    const body = await response.json();
    assert.equal(body.result.key, key);
    assert.equal(body.result.score, score);
    const email = deliveries().at(-1), params = email.template_params;
    assert.equal(email.service_id, 'service_test');
    assert.equal(email.accessToken, 'private_test');
    assert.equal(params.to_email, 'deyanira.mariscalc@outlook.com');
    assert.equal(params.from_email, 'info@themoneybridge.com.mx');
    assert.equal(params.reply_to, input.email);
    assert.equal(params.request_id, input.requestId);
    assert.match(params.html_content, /José &amp; María/);
    for (const question of quiz.questions) {
      assert.ok(params.html_content.includes(question.title));
      assert.ok(params.html_content.includes(question.options[['A', 'B', 'C'].indexOf(answer)]));
    }
    assert.ok(params.html_content.includes(body.result.title));
    assert.ok(params.html_content.includes(input.requestId));
    assert.ok(Buffer.byteLength(JSON.stringify(params)) < 50000);
    assert.ok(!JSON.stringify(body).includes('private_test'));
  }
});
test('reintentos iguales y concurrentes no envían otra notificación en la misma sesión', async () => {
  const identity = await session(), input = payload('B'), count = deliveries().length;
  const replies = await Promise.all([submit(identity, input), submit(identity, input)]);
  assert.deepEqual(replies.map(reply => reply.status).sort(), [200, 201]);
  assert.equal((await submit(identity, { ...input, answers: [...input.answers].reverse() })).status, 200);
  assert.equal((await submit(identity, { ...input, name: 'Otro participante' })).status, 409);
  assert.equal(deliveries().length, count + 1);
});
test('fallos y respuestas inesperadas no se anuncian como enviados y permiten reintentar', async () => {
  for (const failure of ['failure', 'unexpected', 'timeout']) {
    const identity = await session(), input = payload('B');
    mode(failure);
    const response = await submit(identity, input);
    assert.equal(response.status, 503);
    assert.ok(!(await response.text()).includes('provider-private-detail'));
    mode('ok');
    assert.equal((await submit(identity, input)).status, 201);
    const count = deliveries().length;
    assert.equal((await submit(identity, input)).status, 200);
    assert.equal(deliveries().length, count);
  }
});
test('un límite de EmailJS devuelve 429 y permite un nuevo intento', async () => {
  const identity = await session(), input = payload('B');
  mode('rate');
  const response = await submit(identity, input);
  assert.equal(response.status, 429);
  assert.equal(response.headers.get('retry-after'), '1');
  mode('ok');
  assert.equal((await submit(identity, input)).status, 201);
});
test('CSRF, formatos y respuestas inválidas nunca llegan al proveedor', async () => {
  const identity = await session(), input = payload('B'), count = deliveries().length;
  assert.equal((await fetch(`${base}submit.php`)).status, 405);
  assert.equal((await submit(identity, input, { 'X-CSRF-Token': 'invalid' })).status, 403);
  assert.equal((await submit(identity, input, { 'Sec-Fetch-Site': 'cross-site' })).status, 403);
  assert.equal((await submit(identity, input, { 'Content-Type': 'text/plain' })).status, 415);
  assert.equal((await submit(identity, 'x'.repeat(17000))).status, 413);
  assert.equal((await submit(identity, '{invalid')).status, 422);
  for (const invalid of [{ consent: false }, { name: '<script>' }, { email: 'invalid' }, { website: 'bot' }, { answers: input.answers.slice(1) }, { answers: input.answers.map(() => ({ questionId: 1, answer: 'B' })) }]) {
    assert.equal((await submit(identity, { ...input, ...invalid })).status, 422);
  }
  assert.equal(deliveries().length, count);
});
test('el límite de 30 intentos por sesión protege el envío', async () => {
  const identity = await session(), count = deliveries().length;
  for (let i = 0; i < 30; i++) assert.equal((await submit(identity, {})).status, 422);
  const response = await submit(identity, payload('B'));
  assert.equal(response.status, 429);
  assert.equal(response.headers.get('retry-after'), '900');
  assert.equal(deliveries().length, count);
});
test('sin claves no se invoca EmailJS ni se anuncia éxito', () => {
  const code = `require 'backend/email/mailer.php';
    try { sendQuizEmail([], [], '', false, function () { exit(2); }); exit(3); }
    catch (RuntimeException $error) { echo $error->getMessage(); }`;
  const check = spawnSync(php, ['-r', code], { encoding: 'utf8', env: { ...process.env,
    EMAILJS_SERVICE_ID: '', EMAILJS_TEMPLATE_ID: '', EMAILJS_PUBLIC_KEY: '', EMAILJS_PRIVATE_KEY: '' } });
  assert.equal(check.status, 0, check.stderr);
  assert.equal(check.stdout, 'Falta configurar EmailJS.');
});
