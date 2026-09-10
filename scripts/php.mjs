import { spawn } from 'node:child_process';
import { existsSync } from 'node:fs';

export const php = process.env.PHP_BINARY || (process.platform === 'win32' && existsSync('C:/xampp/php/php.exe') ? 'C:/xampp/php/php.exe' : 'php');
const command = process.argv[2];
const commands = {
  setup: ['scripts/setup-db.php'],
  test: ['tests/quiz.php'],
  public: ['-S', '127.0.0.1:8080', '-t', 'public'],
  dist: ['-S', '127.0.0.1:8080', '-t', 'dist'],
};
if (command && commands[command]) {
  if (command === 'dist' && !existsSync('dist/index.html')) { console.error('Ejecuta npm run build primero.'); process.exit(1); }
  const child = spawn(php, commands[command], { stdio: 'inherit' });
  child.on('error', () => { console.error('PHP no está disponible. Configura PHP_BINARY con la ruta a PHP de XAMPP.'); process.exit(1); });
  child.on('exit', (code) => process.exit(code ?? 0));
  process.on('SIGINT', () => child.kill());
  process.on('SIGTERM', () => child.kill());
}
