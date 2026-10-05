import { test, before, after } from 'node:test';
import assert from 'node:assert/strict';
import { spawn, spawnSync } from 'node:child_process';
import { existsSync, mkdirSync, mkdtempSync, readFileSync, renameSync, rmSync, writeFileSync } from 'node:fs';
import { resolve, sep } from 'node:path';
import { once } from 'node:events';
import { createServer } from 'node:net';
import { setTimeout } from 'node:timers/promises';
import { userInfo } from 'node:os';

const windows = process.platform === 'win32';
const binary = process.env.APACHE_BINARY || (windows ? 'C:/xampp/apache/bin/httpd.exe' : '/usr/sbin/apache2');
const serverRoot = process.env.APACHE_SERVER_ROOT || (windows ? 'C:/xampp/apache' : '/etc/apache2');
const modules = process.env.APACHE_MODULES_DIR || (windows ? `${serverRoot}/modules` : '/usr/lib/apache2/modules');
const normalize = path => path.replaceAll('\\', '/');
let directory, quizDirectory, configPath, server, base, failure, stderr = '';
const deniedPaths = [
  'tmb-quiz-private/', 'tmb-quiz-private/backend/config.local.php',
  'tmb-quiz-private/backend/config.example.php', 'tmb-quiz-private/backend/email/template.php',
  'tmb-quiz-private/shared/quiz.json', 'tmb-quiz-private/.runtime/sessions/sess_fixture',
  'tmb-quiz-private/.htaccess', 'api/private-root.php', 'api/bootstrap.php',
  'CONFIGURAR-EMAILJS.md', 'INSTRUCCIONES-HOSTING.md', 'VERSION.json',
];
async function assertDenied(paths) {
  for (const path of paths) {
    const response = await fetch(`${base}/quiz/${path}`, { redirect: 'manual' });
    const body = await response.text();
    assert.equal(response.status, 403, path);
    assert.ok(!body.includes('fixture-only'), path);
  }
}
before(async () => {
  assert.ok(existsSync(binary), 'Se requiere Apache 2.4. Configura APACHE_BINARY si está en otra ruta.');
  const release = JSON.parse(readFileSync('artifacts/latest-hosting.json', 'utf8'));
  mkdirSync('.runtime', { recursive: true });
  directory = mkdtempSync(resolve('.runtime/hosting-apache-'));
  quizDirectory = resolve(directory, 'public_html/quiz');
  mkdirSync(quizDirectory, { recursive: true });
  const extraction = windows
    ? spawnSync('powershell.exe', ['-NoProfile', '-NonInteractive', '-Command',
      '$ErrorActionPreference = "Stop"; Add-Type -AssemblyName System.IO.Compression.FileSystem; [IO.Compression.ZipFile]::ExtractToDirectory($env:QUIZ_TEST_ARCHIVE, $env:QUIZ_TEST_EXTRACT)'], {
      encoding: 'utf8', env: { ...process.env, QUIZ_TEST_ARCHIVE: release.archive, QUIZ_TEST_EXTRACT: quizDirectory },
    })
    : spawnSync('unzip', ['-q', release.archive, '-d', quizDirectory], { encoding: 'utf8' });
  assert.equal(extraction.status, 0, extraction.stderr || extraction.error?.message);
  // Fixtures sin credenciales reales: comprobar que el servidor nunca los publique.
  writeFileSync(resolve(quizDirectory, 'tmb-quiz-private/backend/config.local.php'), '<?php return ["key" => "fixture-only"];');
  writeFileSync(resolve(quizDirectory, 'tmb-quiz-private/.runtime/sessions/sess_fixture'), 'fixture-only');

  const probe = createServer();
  probe.listen(0, '127.0.0.1');
  await once(probe, 'listening');
  const port = probe.address().port;
  await new Promise(resolve => probe.close(resolve));
  base = `http://127.0.0.1:${port}`;
  const compiled = spawnSync(binary, ['-l'], { encoding: 'utf8', windowsHide: true });
  assert.equal(compiled.status, 0, compiled.stderr);
  const moduleLines = ['authz_core', 'rewrite', 'mime', 'dir', 'headers'].map(name =>
    `LoadModule ${name}_module "${normalize(resolve(modules, `mod_${name}.so`))}"`);
  if (!windows) {
    moduleLines.unshift(`LoadModule mpm_event_module "${normalize(resolve(modules, 'mod_mpm_event.so'))}"`);
    if (!compiled.stdout.includes('mod_unixd.c') && existsSync(resolve(modules, 'mod_unixd.so'))) {
      moduleLines.push(`LoadModule unixd_module "${normalize(resolve(modules, 'mod_unixd.so'))}"`);
    }
  }
  const types = windows ? resolve(serverRoot, 'conf/mime.types') : '/etc/mime.types';
  configPath = resolve(directory, 'apache-test.conf');
  const root = normalize(resolve(directory, 'public_html'));
  writeFileSync(configPath, `ServerRoot "${normalize(serverRoot)}"
Listen 127.0.0.1:${port}
ServerName 127.0.0.1
PidFile "${normalize(resolve(directory, 'apache-test.pid'))}"
ErrorLog "${normalize(resolve(directory, 'apache-test.log'))}"
LogLevel warn
${moduleLines.join('\n')}
${windows ? '' : `User "#${userInfo().uid}"\nGroup "#${userInfo().gid}"`}
TypesConfig "${normalize(types)}"
DocumentRoot "${root}"
<Directory />
  AllowOverride None
  Require all denied
</Directory>
<Directory "${root}">
  AllowOverride All
  Options -Indexes
  Require all granted
</Directory>
`);
  const syntax = spawnSync(binary, ['-t', '-f', configPath], { encoding: 'utf8', windowsHide: true });
  assert.equal(syntax.status, 0, syntax.stderr);
  // Configuración y PID propios: no tocar httpd.conf ni el servicio existente.
  server = spawn(binary, ['-f', configPath, '-DFOREGROUND'], { windowsHide: true, stdio: ['ignore', 'ignore', 'pipe'] });
  server.stderr.on('data', data => { stderr += data.toString(); });
  server.on('error', error => { failure = error; });
  server.on('exit', code => { failure = new Error(`Apache de pruebas terminó (${code}): ${stderr}`); });
  let lastStatus;
  for (let i = 0; i < 100; i++) {
    if (failure) throw failure;
    try {
      const response = await fetch(`${base}/quiz/`);
      lastStatus = response.status;
      await response.arrayBuffer();
      if (response.ok) return;
    } catch {}
    await setTimeout(100);
  }
  const log = resolve(directory, 'apache-test.log');
  throw new Error(`No inició Apache de pruebas (HTTP ${lastStatus}): ${stderr}\n${existsSync(log) ? readFileSync(log, 'utf8').slice(-5000) : ''}`);
});
after(async () => {
  if (server && server.exitCode === null) {
    const stopped = once(server, 'exit');
    spawnSync(binary, ['-k', 'shutdown', '-f', configPath], { windowsHide: true, stdio: 'ignore' });
    await Promise.race([stopped, setTimeout(5000)]);
    if (server.exitCode === null) { server.kill(); await stopped; }
  }
  if (directory && directory.startsWith(resolve('.runtime') + sep)) rmSync(directory, { recursive: true, force: true });
});
test('Apache sirve /quiz/ y bloquea backend, claves, sesiones y localizador con 403', async () => {
  const response = await fetch(`${base}/quiz/`);
  assert.equal(response.status, 200);
  assert.match(await response.text(), /data-api="\/quiz\/api\/"/);
  await assertDenied(deniedPaths);
  await assertDenied(['%74mb-quiz-private/shared/quiz.json', 'TMB-QUIZ-PRIVATE/shared/quiz.json']);
  const head = await fetch(`${base}/quiz/tmb-quiz-private/backend/config.local.php`, { method: 'HEAD' });
  assert.equal(head.status, 403);
});
test('el bloqueo de quiz protege incluso si falta el .htaccess privado', async () => {
  const file = resolve(quizDirectory, 'tmb-quiz-private/.htaccess');
  renameSync(file, file + '.test-backup');
  try { await assertDenied(deniedPaths.slice(0, 7)); }
  finally { renameSync(file + '.test-backup', file); }
});
test('Require all denied protege el backend sin las reglas del .htaccess padre', async () => {
  const file = resolve(quizDirectory, '.htaccess');
  renameSync(file, file + '.test-backup');
  try { await assertDenied(deniedPaths.slice(0, 7)); }
  finally { renameSync(file + '.test-backup', file); }
});
