import { spawnSync } from 'node:child_process';
import { copyFileSync, cpSync, mkdirSync, readdirSync, readFileSync, writeFileSync } from 'node:fs';
import { resolve } from 'node:path';
import assert from 'node:assert/strict';

// Publicación en /quiz/. El paquete no necesita Node, Composer ni SQL en el hosting.
const astro = JSON.parse(readFileSync('node_modules/astro/package.json', 'utf8'));
const cli = resolve('node_modules/astro', astro.bin.astro);
for (const command of ['check', 'build']) {
  const result = spawnSync(process.execPath, [cli, command], {
    stdio: 'inherit', env: { ...process.env, PUBLIC_BASE_PATH: '/quiz/' },
  });
  if (result.error) throw result.error;
  if (result.status !== 0) process.exit(result.status ?? 1);
}
const html = readFileSync('dist/index.html', 'utf8');
assert.match(html, /data-api="\/quiz\/api\/"/, 'La API debe apuntar a /quiz/api/.');
assert.match(html, /src="\/quiz\/_astro\//, 'Los recursos deben apuntar a /quiz/_astro/.');

const releaseId = new Date().toISOString().replace(/[:.]/g, '-');
const release = resolve('artifacts', `hosting-${releaseId}`);
const directory = resolve(release, 'package');
// El ZIP contiene directamente el contenido de /quiz/: no modifica carpetas hermanas.
const publicDirectory = directory;
const privateDirectory = resolve(publicDirectory, 'tmb-quiz-private');
mkdirSync(publicDirectory, { recursive: true });
mkdirSync(resolve(privateDirectory, '.runtime/sessions'), { recursive: true });
cpSync('dist', publicDirectory, { recursive: true });
// Lista explícita de archivos privados: nunca incluir claves, sesiones o registros.
for (const file of ['backend/config.php', 'backend/config.example.php', 'backend/http.php', 'backend/quiz.php', 'backend/submission.php', 'backend/email/mailer.php', 'backend/email/report.php', 'backend/email/template.php', 'backend/email/assets/logo.png', 'shared/quiz.json']) {
  const destination = resolve(privateDirectory, file);
  mkdirSync(resolve(destination, '..'), { recursive: true });
  copyFileSync(file, destination);
}
mkdirSync(resolve(publicDirectory, 'email-assets'), { recursive: true });
copyFileSync('backend/email/assets/logo.png', resolve(publicDirectory, 'email-assets/logo.png'));
writeFileSync(resolve(privateDirectory, '.htaccess'), 'Options -Indexes\nRequire all denied\n');
writeFileSync(resolve(privateDirectory, '.runtime/sessions/.gitkeep'), '');
writeFileSync(resolve(publicDirectory, 'api/private-root.php'), `<?php
// /quiz/api → /quiz/tmb-quiz-private, independientemente de la raíz del dominio.
return dirname(__DIR__) . '/tmb-quiz-private';
`);
copyFileSync('docs/hosting.md', resolve(directory, 'INSTRUCCIONES-HOSTING.md'));
copyFileSync('docs/emailjs.md', resolve(directory, 'CONFIGURAR-EMAILJS.md'));
writeFileSync(resolve(directory, 'VERSION.json'), JSON.stringify({
  project: 'quiz-the-money-bridge', builtAt: releaseId, publicPath: '/quiz/', apiPath: '/quiz/api/',
  privatePath: '/quiz/tmb-quiz-private/', credentialsIncluded: false, databaseRequired: false,
}, null, 2) + '\n');
const archive = resolve(release, 'quiz-hosting.zip');
// Nombres explícitos evitan entradas ./ incompatibles con extractores de cPanel.
const entries = readdirSync(directory).sort();
const zip = process.platform === 'win32'
  ? spawnSync('tar.exe', ['-a', '-c', '-f', archive, '-C', directory, ...entries], { stdio: 'inherit' })
  : spawnSync('zip', ['-q', '-r', archive, ...entries], { cwd: directory, stdio: 'inherit' });
if (zip.error) throw zip.error;
if (zip.status !== 0) throw new Error('No se pudo crear el ZIP: se requiere tar.exe en Windows o zip en Linux/macOS.');
writeFileSync(resolve('artifacts/latest-hosting.json'), JSON.stringify({ directory, archive }, null, 2) + '\n');
console.log(`\nPaquete completo: ${archive}\nExtraer directamente dentro de /quiz/, incluyendo los archivos ocultos.\nSigue INSTRUCCIONES-HOSTING.md y CONFIGURAR-EMAILJS.md. Verifica con npm run test:hosting y npm run test:hosting:apache.`);
