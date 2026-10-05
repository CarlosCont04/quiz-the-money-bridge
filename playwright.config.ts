import { defineConfig, devices } from '@playwright/test';
export default defineConfig({
  testDir: './tests/e2e',
  fullyParallel: false,
  workers: 1,
  reporter: 'list',
  timeout: 45000,
  use: { baseURL: 'http://127.0.0.1:4321', trace: 'retain-on-failure' },
  projects: [
    { name: 'desktop', use: { ...devices['Desktop Chrome'], viewport: { width: 1440, height: 1000 } } },
    { name: 'mobile', use: { ...devices['iPhone 13'], defaultBrowserType: 'chromium' } },
  ],
  // Las pruebas siempre arrancan un router local con EmailJS simulado.
  webServer: { command: 'node scripts/dev.mjs --test-email', url: 'http://127.0.0.1:4321', reuseExistingServer: false, timeout: 30000, env: { PUBLIC_BASE_PATH: '/', EMAILJS_SERVICE_ID: 'service_test', EMAILJS_TEMPLATE_ID: 'template_test', EMAILJS_PUBLIC_KEY: 'public_test', EMAILJS_PRIVATE_KEY: 'private_test', EMAIL_LOGO_URL: '' } },
});
