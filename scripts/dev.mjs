import { spawn } from 'node:child_process';
import { existsSync } from 'node:fs';
const php = process.env.PHP_BINARY || (process.platform === 'win32' && existsSync('C:/xampp/php/php.exe') ? 'C:/xampp/php/php.exe' : 'php');
const children = [
  spawn(php, ['-S', '127.0.0.1:8080', '-t', 'public'], { stdio: 'inherit' }),
  spawn(process.execPath, ['scripts/astro-dev.mjs'], { stdio: 'inherit' }),
];
let closing = false;
function stop(code = 0) { if (closing) return; closing = true; children.forEach((child) => child.kill()); process.exitCode = code; }
children.forEach((child) => { child.on('error', (error) => { console.error(error.message); stop(1); }); child.on('exit', (code) => stop(code ?? 0)); });
process.on('SIGINT', () => stop());
process.on('SIGTERM', () => stop());
