import { spawnSync } from 'node:child_process';
import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
const astro = JSON.parse(readFileSync('node_modules/astro/package.json', 'utf8'));
const cli = resolve('node_modules/astro', astro.bin.astro);
for (const command of ['check', 'build']) {
  const result = spawnSync(process.execPath, [cli, command], {
    stdio: 'inherit', env: { ...process.env, PUBLIC_BASE_PATH: '/quiz-the-money-bridge/' },
  });
  if (result.error) throw result.error;
  if (result.status !== 0) process.exit(result.status ?? 1);
}
