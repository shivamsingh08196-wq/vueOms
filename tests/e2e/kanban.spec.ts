import { test, expect } from '@playwright/test';

test.beforeEach(async ({ page }) => {
  await page.goto('/login');
  await page.fill('input[type="email"]', 'admin@vueoms.com');
  await page.fill('input[type="password"]', 'password');
  await page.click('button[type="submit"]');
  await expect(page).toHaveURL(/.*\/dashboard/);
});

test('can navigate to purchases and see kanban board', async ({ page }) => {
  // Navigate to purchases
  await page.click('text=Purchases');
  await expect(page).toHaveURL(/.*\/purchases/);

  // Check for kanban columns
  await expect(page.locator('text=Pending')).toBeVisible();
  
  // Create button should be visible
  await expect(page.locator('button:has-text("Create Purchase")')).toBeVisible();
});
