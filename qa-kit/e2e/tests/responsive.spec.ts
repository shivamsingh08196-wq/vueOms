import { test, expect } from '../support/fixtures';
import { Api, exampleOrder } from '../support/api';

// Runs in the "mobile" project (Pixel 5, 393px wide).
const noHorizontalScroll = async (page: import('@playwright/test').Page) => {
  const { scrollWidth, innerWidth } = await page.evaluate(() => ({ scrollWidth: document.documentElement.scrollWidth, innerWidth: window.innerWidth }));
  expect(scrollWidth, 'page must not scroll horizontally').toBeLessThanOrEqual(innerWidth + 1);
};

test.describe('responsive layout (mobile)', () => {
  test('login fits the screen', async ({ page }) => {
    await page.goto('/login');
    await expect(page.getByTestId('login-submit')).toBeVisible();
    await noHorizontalScroll(page);
  });

  for (const path of ['/dashboard', '/purchases', '/purchases/new', '/clients', '/items']) {
    test(`${path} has no horizontal overflow and a collapsible menu`, async ({ page, loginAs }) => {
      await loginAs('admin');
      await page.goto(path);
      await expect(page.getByTestId('page-title')).toBeVisible();
      await noHorizontalScroll(page);
      await expect(page.getByTestId('nav-toggle')).toBeVisible();
      await expect(page.getByTestId('nav-purchases')).toBeHidden();
      await page.getByTestId('nav-toggle').click();
      await expect(page.getByTestId('nav-purchases')).toBeVisible();
    });
  }

  test('purchase detail is usable on mobile', async ({ page, loginAs }) => {
    const admin = await Api.as('admin');
    const { purchase } = await exampleOrder(admin);
    await loginAs('admin');
    await page.goto(`/purchases/${purchase.id}`);
    await expect(page.getByTestId('purchase-number')).toBeVisible();
    await noHorizontalScroll(page);
    await page.getByTestId('action-status').scrollIntoViewIfNeeded();
    await expect(page.getByTestId('action-status')).toBeVisible();
    const box = await page.getByTestId('action-status').boundingBox();
    expect(box!.height).toBeGreaterThanOrEqual(36); // comfortable touch target
  });
});
