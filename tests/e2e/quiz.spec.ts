import { test, expect } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';
import { execFileSync } from 'node:child_process';
import { existsSync } from 'node:fs';
import { randomUUID } from 'node:crypto';

const php = process.env.PHP_BINARY || (existsSync('C:/xampp/php/php.exe') ? 'C:/xampp/php/php.exe' : 'php');
const savedRequests: string[] = [];
function record(id: string) { return JSON.parse(execFileSync(php, ['tests/db-record.php', 'read', id], { encoding: 'utf8' })); }
test.afterEach(() => { for (const id of savedRequests.splice(0)) execFileSync(php, ['tests/db-record.php', 'delete', id]); });

test('responsive landing, keyboard navigation and accessibility', async ({ page }, testInfo) => {
  await page.goto('./');
  await expect(page.getByRole('heading', { level: 1 })).toContainText('usando bien');
  await expect(page.locator('#quiz-form')).toBeVisible();
  expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBeTruthy();
  expect((await new AxeBuilder({ page }).analyze()).violations).toEqual([]);
  await page.screenshot({ path: `test-results/landing-${testInfo.project.name}.png`, fullPage: true });
  await page.getByRole('link', { name: 'Descubrir mi semáforo financiero' }).click();
  await page.getByRole('button', { name: 'Siguiente', exact: true }).click();
  await expect(page.locator('#step-label')).toHaveText('PREGUNTA 01 DE 12');
  const first = page.locator('[name="question-1"]').first();
  await first.focus(); await page.keyboard.press('Space');
  await page.getByRole('button', { name: 'Siguiente', exact: true }).click();
  await expect(page.locator('#step-label')).toHaveText('PREGUNTA 02 DE 12');
  await page.getByRole('button', { name: 'Anterior' }).click();
  await expect(first).toBeChecked();
});

for (const [answer, score, key, title] of [
  ['A', 0, 'red', 'Tu dinero necesita atención'],
  ['B', 12, 'yellow', 'Tu dinero puede dar más'],
  ['C', 24, 'green', 'Tu dinero está listo para crecer'],
] as const) {
  test(`completes ${key}, stores SQL rows and opens accessible result`, async ({ page }, testInfo) => {
    await page.goto('./');
    for (let id = 1; id <= 12; id++) {
      await page.locator(`label:has(input[name="question-${id}"][value="${answer}"])`).click();
      await page.getByRole('button', { name: 'Siguiente', exact: true }).click();
    }
    await expect(page.locator('#progress-label')).toHaveText('100% completado');
    expect((await new AxeBuilder({ page }).analyze()).violations).toEqual([]);
    await page.getByLabel('Nombre completo').fill('Prueba automatizada');
    await page.getByLabel('Correo electrónico').fill(`quiz-test-${randomUUID()}@example.invalid`);
    await page.locator('[name="consent"]').check();
    const requestPromise = page.waitForRequest((request) => request.url().endsWith('/api/submit.php'));
    await page.getByRole('button', { name: 'Ver mi resultado', exact: true }).click();
    const request = await requestPromise;
    const id = request.postDataJSON().requestId;
    savedRequests.push(id);
    await expect(page.getByRole('dialog', { name: title })).toBeVisible();
    await expect(page.locator('#result-score')).toHaveText(String(score));
    await expect(page.getByText('Gracias por responder el quiz', { exact: true })).toBeVisible();
    const saved = record(id);
    expect(Number(saved.total_score)).toBe(score);
    expect(saved.result_key).toBe(key);
    expect(Number(saved.answer_count)).toBe(12);
    expect(Number(saved.answer_score)).toBe(score);
    expect((await new AxeBuilder({ page }).analyze()).violations).toEqual([]);
    if (key === 'green') await page.screenshot({ path: `test-results/result-${testInfo.project.name}.png` });
    await page.keyboard.press('Escape');
    await expect(page.locator('#result-dialog')).not.toBeVisible();
    await expect(page.locator('#reopen-result')).toBeFocused();
    await page.getByRole('button', { name: 'Ver mi resultado', exact: true }).click();
    await expect(page.locator('#result-dialog')).toBeVisible();
    await page.getByRole('button', { name: 'Cerrar resultado' }).click();
    await page.getByRole('button', { name: 'Volver a responder' }).click();
    await expect(page.locator('#step-label')).toHaveText('PREGUNTA 01 DE 12');
    await expect(page.locator('#progress-label')).toHaveText('0% completado');
  });
}

test('API enforces CSRF, validation, trusted scoring and idempotency', async ({ request }) => {
  const id = randomUUID(); savedRequests.push(id);
  const payload = { requestId: id, name: 'Prueba automatizada', email: `quiz-test-${id}@example.invalid`, consent: true, score: 24, result: 'green', answers: Array.from({ length: 12 }, (_, i) => ({ questionId: i + 1, answer: 'A' })) };
  expect((await request.get('api/submit.php')).status()).toBe(405);
  expect((await request.post('api/submit.php', { data: payload })).status()).toBe(403);
  const session = await (await request.get('api/session.php')).json();
  const headers = { 'X-CSRF-Token': session.csrfToken };
  expect((await request.post('api/submit.php', { headers, data: { ...payload, consent: false } })).status()).toBe(422);
  expect((await request.post('api/submit.php', { headers, data: { ...payload, answers: payload.answers.slice(1) } })).status()).toBe(422);
  expect((await request.post('api/submit.php', { headers, data: '{broken', })).status()).toBe(415);
  const first = await request.post('api/submit.php', { headers, data: payload });
  expect(first.status()).toBe(201);
  expect((await first.json()).result.score).toBe(0);
  const retry = await request.post('api/submit.php', { headers, data: payload });
  expect(retry.status()).toBe(200);
  expect((await retry.json()).result.key).toBe('red');
  expect(Number(record(id).answer_count)).toBe(12);
  expect((await request.post('api/submit.php', { headers, data: { ...payload, name: 'Otro nombre' } })).status()).toBe(409);
});

test('network failure preserves answers and allows retry', async ({ page }) => {
  await page.goto('./');
  for (let id = 1; id <= 12; id++) {
    await page.locator(`label:has(input[name="question-${id}"][value="B"])`).click();
    await page.getByRole('button', { name: 'Siguiente', exact: true }).click();
  }
  await page.getByLabel('Nombre completo').fill('Prueba automatizada');
  await page.getByLabel('Correo electrónico').fill('quiz-test-network@example.invalid');
  await page.locator('[name="consent"]').check();
  await page.route('**/api/submit.php', (route) => route.abort());
  await page.getByRole('button', { name: 'Ver mi resultado', exact: true }).click();
  await expect(page.locator('#form-status')).toContainText('conservamos tus respuestas');
  await expect(page.locator('#result-dialog')).not.toBeVisible();
  await expect(page.getByLabel('Nombre completo')).toHaveValue('Prueba automatizada');
  await page.getByRole('button', { name: 'Anterior' }).click();
  await expect(page.locator('[name="question-12"][value="B"]')).toBeChecked();
});
