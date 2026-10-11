import { test, expect } from '@playwright/test';

test.beforeEach(async ({ page }) => {
  await page.goto('/login');
  await page.fill('input[type="email"]', 'admin@vueoms.com');
  await page.fill('input[type="password"]', 'password');
  await page.click('button[type="submit"]');
  await expect(page).toHaveURL(/.*\/dashboard/);
});

test('can navigate to clients, view list, and add a new client', async ({ page }) => {
  await page.click('text=Clients');
  await expect(page).toHaveURL(/.*\/clients/);

  // Table should show existing clients
  await expect(page.locator('h1')).toContainText('Clients');
  await expect(page.locator('table')).toBeVisible();

  // Click Add Client
  await page.click('button:has-text("Add Client")');

  // Drawer should open
  await expect(page.locator('text=Add New Client')).toBeVisible();

  // Fill in form
  const uniqueEmail = `testclient${Date.now()}@example.com`;
  await page.fill('input[placeholder*="Acme Corporation"]', 'Test Client Company');
  await page.fill('input[placeholder*="contact@acme.com"]', uniqueEmail);
  await page.fill('input[placeholder*="+1"]', '1234567890');

  // Submit
  await page.click('button:has-text("Create Client")');

  // Verify client is added and visible in list
  await expect(page.locator(`text=${uniqueEmail}`)).toBeVisible();
});
