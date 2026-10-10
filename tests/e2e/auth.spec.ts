import { test, expect } from '@playwright/test';

test('can log in and see dashboard', async ({ page }) => {
  await page.goto('/');

  // Should redirect to login
  await expect(page).toHaveURL(/.*\/login/);

  // Fill login form
  await page.fill('input[type="email"]', 'admin@vueoms.com');
  await page.fill('input[type="password"]', 'password');
  await page.click('button[type="submit"]');

  // Should redirect to dashboard or purchases
  await expect(page).toHaveURL(/.*\/dashboard/);
  
  // Wait for some text to appear
  await expect(page.locator('body')).toContainText(/Purchases|Dashboard/i);
});
