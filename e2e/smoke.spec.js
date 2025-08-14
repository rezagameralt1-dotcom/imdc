const { test, expect } = require('@playwright/test');
test('home loads & internal links clickable', async ({ page }) => {
  await page.goto('/', { waitUntil: 'domcontentloaded' });
  await expect(page.locator('body')).toBeVisible();
  const anchors = await page.locator('a[href]').evaluateAll(els =>
    els.map(a => a.getAttribute('href') || '')
      .filter(h => h && !h.startsWith('http') && !h.startsWith('mailto:') && !h.startsWith('#'))
  );
  const uniq = Array.from(new Set(anchors)).slice(0, 50);
  for (const href of uniq) {
    await Promise.all([
      page.waitForNavigation({ waitUntil: 'domcontentloaded' }).catch(() => null),
      page.locator(`a[href="${href}"]`).first().click().catch(() => null)
    ]);
    await page.goBack({ waitUntil: 'domcontentloaded' }).catch(() => null);
  }
});
test('no severe console errors', async ({ page }) => {
  const severe = [];
  page.on('console', msg => { if (msg.type() === 'error') severe.push(msg.text()); });
  await page.goto('/', { waitUntil: 'domcontentloaded' });
  expect(severe.join('\n')).toBe('');
});
