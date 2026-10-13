import { test, expect } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';
import { randomUUID } from 'node:crypto';

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

for (const [answer, score, key, title, color, nextStep] of [
  ['A', 0, 'red', 'Tu dinero necesita atención', 'Rojo', 'Me gustaría recibir orientación para ordenar mis finanzas, fortalecer mi ahorro y revisar mis protecciones.'],
  ['B', 12, 'yellow', 'Tu dinero puede dar más', 'Amarillo', 'Me gustaría recibir orientación para fortalecer mi estrategia financiera y conectar mi ahorro, protección e inversiones.'],
  ['C', 24, 'green', 'Tu dinero está listo para crecer', 'Verde', 'Me gustaría conversar sobre cómo optimizar mi estrategia, mejorar mis inversiones y seguir construyendo patrimonio.'],
] as const) {
  test(`completes ${key}, confirms EmailJS acceptance and opens accessible result`, async ({ page }, testInfo) => {
    await page.goto('./');
    for (let id = 1; id <= 12; id++) {
      await page.locator(`label:has(input[name="question-${id}"][value="${answer}"])`).click();
      await page.getByRole('button', { name: 'Siguiente', exact: true }).click();
    }
    await expect(page.locator('#progress-label')).toHaveText('100% completado');
    expect((await new AxeBuilder({ page }).analyze()).violations).toEqual([]);
    await page.getByLabel('Nombre completo').fill('Prueba automatizada');
    await page.getByLabel('Correo electrónico', { exact: true }).fill(`quiz-test-${randomUUID()}@example.invalid`);
    await page.locator('[name="consent"]').check();
    const requestPromise = page.waitForRequest((request) => request.url().endsWith('/api/submit.php'));
    await page.getByRole('button', { name: 'Ver mi resultado', exact: true }).click();
    const request = await requestPromise;
    expect(request.postDataJSON().answers).toHaveLength(12);
    await expect(page.getByRole('dialog', { name: title })).toBeVisible();
    await expect(page.locator('#result-score')).toHaveText(String(score));
    await expect(page.getByText('Gracias por responder el quiz', { exact: true })).toBeVisible();
    const contact = page.getByRole('link', { name: /Conversar con The Money Bridge por WhatsApp con Deyanira Mariscal/ });
    await expect(contact).toHaveAttribute('target', '_blank');
    await expect(contact).toHaveAttribute('rel', 'noopener noreferrer');
    const whatsapp = new URL((await contact.getAttribute('href'))!);
    expect(whatsapp.origin).toBe('https://wa.me');
    expect(whatsapp.pathname).toBe('/525520934484');
    expect(whatsapp.searchParams.get('text')).toBe(`Hola, Deyanira Mariscal. Respondí el quiz financiero de The Money Bridge y obtuve semáforo ${color} (${score} de 24 puntos). ${nextStep}`);
    expect(whatsapp.searchParams.get('text')).not.toContain('Prueba automatizada');
    expect(whatsapp.searchParams.get('text')).not.toContain('@');
    // Interceptar WhatsApp: validar el clic sin abrir una conversación real.
    await page.context().route('https://wa.me/**', route => route.fulfill({ contentType: 'text/html', body: '<p>WhatsApp simulado</p>' }));
    const popupPromise = page.waitForEvent('popup');
    await contact.click();
    const popup = await popupPromise;
    await expect(popup).toHaveURL(whatsapp.href);
    await popup.close();
    await page.context().unroute('https://wa.me/**');
    await expect(page.locator('#result-dialog')).toBeVisible();
    expect((await new AxeBuilder({ page }).analyze()).violations).toEqual([]);
    if (key === 'green') await page.screenshot({ path: `test-results/result-${testInfo.project.name}.png` });
    await page.keyboard.press('Escape');
    await expect(page.locator('#result-dialog')).not.toBeVisible();
    await expect(page.locator('#reopen-result')).toBeFocused();
    await page.getByRole('button', { name: 'Ver mi resultado', exact: true }).click();
    await expect(page.locator('#result-dialog')).toBeVisible();
    await expect(contact).toHaveAttribute('href', whatsapp.href);
    await page.getByRole('button', { name: 'Cerrar resultado' }).click();
    await page.getByRole('button', { name: 'Volver a responder' }).click();
    await expect(page.locator('#step-label')).toHaveText('PREGUNTA 01 DE 12');
    await expect(page.locator('#progress-label')).toHaveText('0% completado');
  });
}

test('API enforces CSRF, validation, trusted scoring and idempotency', async ({ request }) => {
  const id = randomUUID();
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
  expect((await request.post('api/submit.php', { headers, data: { ...payload, name: 'Otro nombre' } })).status()).toBe(409);
});

test('network failure preserves answers and allows retry', async ({ page }) => {
  await page.goto('./');
  for (let id = 1; id <= 12; id++) {
    await page.locator(`label:has(input[name="question-${id}"][value="B"])`).click();
    await page.getByRole('button', { name: 'Siguiente', exact: true }).click();
  }
  await page.getByLabel('Nombre completo').fill('Prueba automatizada');
  await page.getByLabel('Correo electrónico', { exact: true }).fill('quiz-test-network@example.invalid');
  await page.locator('[name="consent"]').check();
  await page.route('**/api/submit.php', (route) => route.abort());
  await page.getByRole('button', { name: 'Ver mi resultado', exact: true }).click();
  await expect(page.locator('#form-status')).toContainText('conservamos tus respuestas');
  await expect(page.locator('#result-dialog')).not.toBeVisible();
  await expect(page.getByLabel('Nombre completo')).toHaveValue('Prueba automatizada');
  await page.getByRole('button', { name: 'Anterior' }).click();
  await expect(page.locator('[name="question-12"][value="B"]')).toBeChecked();
  await page.unroute('**/api/submit.php');
  await page.getByRole('button', { name: 'Siguiente', exact: true }).click();
  await page.getByRole('button', { name: 'Ver mi resultado', exact: true }).click();
  await expect(page.getByRole('dialog', { name: 'Tu dinero puede dar más' })).toBeVisible();
});

test('provider rejection preserves registration and never announces success', async ({ page }) => {
  await page.goto('./');
  for (let id = 1; id <= 12; id++) {
    await page.locator(`label:has(input[name="question-${id}"][value="B"])`).click();
    await page.getByRole('button', { name: 'Siguiente', exact: true }).click();
  }
  await page.getByLabel('Nombre completo').fill('Prueba automatizada');
  await page.getByLabel('Correo electrónico', { exact: true }).fill('quiz-test-provider@example.invalid');
  await page.locator('[name="consent"]').check();
  await page.route('**/api/submit.php', route => route.fulfill({ status: 503, contentType: 'application/json', body: JSON.stringify({ message: 'No pudimos confirmar el envío. Tus respuestas siguen aquí.' }) }));
  await page.getByRole('button', { name: 'Ver mi resultado', exact: true }).click();
  await expect(page.locator('#form-status')).toContainText('No pudimos confirmar');
  await expect(page.locator('#quiz-complete')).not.toBeVisible();
  await expect(page.locator('#result-dialog')).not.toBeVisible();
  await expect(page.getByLabel('Correo electrónico', { exact: true })).toHaveValue('quiz-test-provider@example.invalid');
  await page.unroute('**/api/submit.php');
  await page.getByRole('button', { name: 'Ver mi resultado', exact: true }).click();
  await expect(page.getByRole('dialog', { name: 'Tu dinero puede dar más' })).toBeVisible();
});
