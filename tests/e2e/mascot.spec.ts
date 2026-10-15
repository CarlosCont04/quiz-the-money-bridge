import { test, expect, type Page } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';

async function expectReservedSpace(page: Page, selector = '#site-viewport') {
  const pig = await page.locator('#floating-pig').boundingBox();
  const content = await page.locator(selector).boundingBox();
  const dock = await page.locator('.mascot-dock').boundingBox();
  expect(pig).not.toBeNull();
  expect(content).not.toBeNull();
  expect(dock).not.toBeNull();
  expect(pig!.y).toBeGreaterThanOrEqual(dock!.y);
  expect(pig!.y + pig!.height).toBeLessThanOrEqual(page.viewportSize()!.height);
  expect(pig!.x + pig!.width).toBeLessThanOrEqual(page.viewportSize()!.width);
  expect(content!.y + content!.height).toBeLessThanOrEqual(dock!.y + 1);
}

test('el cerdito permanece visible sin superponerse al contenido en móvil, tablet y escritorio', async ({ page }, testInfo) => {
  await page.emulateMedia({ reducedMotion: 'reduce' });
  await page.goto('./');
  await expect(page.locator('#floating-pig')).toBeVisible();
  await expect.poll(() => page.locator('#floating-pig').evaluate((image: HTMLImageElement) => image.complete && image.naturalWidth > 0)).toBeTruthy();
  await expect(page.locator('#floating-pig')).toHaveAttribute('alt', '');
  await expect(page.locator('.mascot-dock')).toHaveAttribute('aria-hidden', 'true');
  for (const [width, height] of [[320, 640], [390, 844], [768, 1024], [1024, 700], [1440, 900], [844, 390]]) {
    await page.setViewportSize({ width, height });
    for (const fraction of [0, 0.5, 1]) {
      await page.locator('#site-viewport').evaluate((viewport, position) => viewport.scrollTo({ top: position * (viewport.scrollHeight - viewport.clientHeight), behavior: 'instant' }), fraction);
      await expectReservedSpace(page);
      expect(await page.locator('#site-viewport').evaluate(viewport => viewport.scrollWidth <= viewport.clientWidth)).toBeTruthy();
    }
  }
  await page.setViewportSize(testInfo.project.name === 'mobile' ? { width: 390, height: 844 } : { width: 1440, height: 1000 });
  await page.locator('#site-viewport').evaluate(viewport => viewport.scrollTo({ top: 0, behavior: 'instant' }));
  await page.screenshot({ path: `test-results/cerdito-${testInfo.project.name}.png` });
  expect((await new AxeBuilder({ page }).analyze()).violations).toEqual([]);
});

test('el cerdito conserva su espacio durante el aviso de privacidad y respeta movimiento reducido', async ({ page }, testInfo) => {
  await page.emulateMedia({ reducedMotion: 'reduce' });
  await page.goto('./');
  await expect(page.locator('#floating-pig')).toHaveCSS('animation-name', 'none');
  await expect(page.locator('.mascot-dock')).toHaveCSS('pointer-events', 'none');
  await page.getByRole('button', { name: 'Uso de datos', exact: true }).click();
  await expect(page.locator('#privacy-dialog')).toBeVisible();
  await expectReservedSpace(page, '#privacy-dialog');
  const backdropBottom = await page.locator('#privacy-dialog').evaluate(dialog => getComputedStyle(dialog, '::backdrop').bottom);
  const dockHeight = await page.locator('.mascot-dock').evaluate(dock => getComputedStyle(dock).height);
  expect(backdropBottom).toBe(dockHeight);
  await page.screenshot({ path: `test-results/cerdito-privacidad-${testInfo.project.name}.png` });
  expect((await new AxeBuilder({ page }).analyze()).violations).toEqual([]);
  await page.getByRole('button', { name: 'Entendido' }).click();
  await expect(page.locator('#privacy-dialog')).not.toBeVisible();
  await expect(page.locator('#site-viewport')).toHaveCSS('overflow-y', 'auto');
});

test('los títulos combinan acentos rosas con la paleta original', async ({ page }) => {
  await page.goto('./');
  await expect(page.locator('#hero-title span')).toHaveCSS('color', 'rgb(184, 63, 115)');
  await expect(page.locator('#how-title span')).toHaveCSS('color', 'rgb(184, 63, 115)');
  await expect(page.locator('#quiz-title span')).toHaveCSS('color', 'rgb(184, 63, 115)');
  await expect(page.locator('#hero-title')).toHaveCSS('color', 'rgb(25, 37, 91)');
});
