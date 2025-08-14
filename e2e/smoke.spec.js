const { test, expect } = require('@playwright/test');

test('healthz is OK', async ({ page }) => {
  await page.goto('/healthz', { waitUntil: 'domcontentloaded' });
  const body = await page.textContent('body');
  expect(body.trim()).toBe('HEALTH OK');
});

test('no severe console errors on healthz', async ({ page }) => {
  const severe = [];
  page.on('console', msg => { if (msg.type() === 'error') severe.push(msg.text()); });
  await page.goto('/healthz', { waitUntil: 'domcontentloaded' });
  expect(severe.join('\n')).toBe('');
});
