import { test, expect } from '@playwright/test';

test.beforeEach(async ({ page }) => {
  await page.goto('/login');
  await page.fill('input[type="email"]', 'admin@vueoms.com');
  await page.fill('input[type="password"]', 'password');
  await page.click('button[type="submit"]');
  await expect(page).toHaveURL(/.*\/dashboard/);
});

test('can navigate to items, view list, and add a new item', async ({ page }) => {
  await page.click('text=Items');
  await expect(page).toHaveURL(/.*\/items/);

  // Table should show existing seeded items or table header
  await expect(page.locator('h1')).toContainText('Items');
  await expect(page.locator('table')).toBeVisible();

  // Click Add Item
  await page.click('button:has-text("Add Item")');

  // Drawer should open
  await expect(page.locator('text=Add New Item')).toBeVisible();

  // Fill in form
  const uniqueName = `Test Item ${Date.now()}`;
  await page.fill('input[placeholder*="Mechanical Keyboard"]', uniqueName);
  await page.fill('input[placeholder="99.99"]', '49.99');
  await page.fill('input[placeholder="50"]', '25');

  // Submit
  await page.click('button:has-text("Create Item")');

  // Verify item is added and visible in list
  await expect(page.locator(`text=${uniqueName}`)).toBeVisible();
});
