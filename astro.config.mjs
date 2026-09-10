import { defineConfig } from 'astro/config';
import { loadEnv } from 'vite';

const env = loadEnv(process.env.NODE_ENV || 'development', process.cwd(), 'PUBLIC_');
const rawBase = process.env.PUBLIC_BASE_PATH || env.PUBLIC_BASE_PATH || '/';
const base = `/${rawBase.split('/').filter(Boolean).join('/')}${rawBase === '/' ? '' : '/'}`;
export default defineConfig({
  output: 'static',
  devToolbar: { enabled: false },
  base,
  build: { format: 'directory' },
  vite: {
    server: {
      proxy: {
        [`${base.replace(/\/$/, '')}/api`]: {
          target: 'http://127.0.0.1:8080',
          changeOrigin: false,
          rewrite: (path) => path.replace(new RegExp(`^${base.replace(/\/$/, '')}/api`), '/api'),
        },
      },
    },
  },
});
