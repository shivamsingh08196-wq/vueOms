import { test, expect } from '@playwright/test';

test.beforeEach(async ({ page }) => {
  await page.goto('/login');
  await page.fill('input[type="email"]', 'admin@vueoms.com');
  await page.fill('input[type="password"]', 'password');
  await page.click('button[type="submit"]');
  await expect(page).toHaveURL(/.*\/dashboard/);
});

test('admin can navigate to users, view list, and open create drawer', async ({ page }) => {
  await page.click('text=Users');
  await expect(page).toHaveURL(/.*\/users/);

  // Table should show existing seeded users
  await expect(page.locator('h1')).toContainText('User & Role Management');
  await expect(page.locator('table[data-testid="users-table"]')).toBeVisible();

  // Click Add User
  await page.click('button[data-testid="add-user-btn"]');

  // Drawer should open
  await expect(page.locator('text=Add New User')).toBeVisible();

  // Fill form
  const uniqueEmail = `testops${Date.now()}@vueoms.com`;
  await page.fill('input[placeholder="John Doe"]', 'Test Ops Person');
  await page.fill('input[placeholder="john@example.com"]', uniqueEmail);
  await page.fill('input[placeholder*="Minimum 6 characters"]', 'secret123');

  // Submit
  await page.click('button:has-text("Create User")');

  // Verify user appears in table
  await expect(page.locator(`text=${uniqueEmail}`)).toBeVisible();
});

test('admin can navigate to audit logs and view mutation records', async ({ page }) => {
  await page.click('text=Audit Logs');
  await expect(page).toHaveURL(/.*\/audit-logs/);
  await expect(page.locator('h1')).toContainText('System Audit Logs');
});

test('admin can navigate to reports and view revenue metrics', async ({ page }) => {
  await page.click('text=Reports');
  await expect(page).toHaveURL(/\/reports/);
  await expect(page.getByRole('heading', { level: 1, name: 'Reports' })).toBeVisible();
  await expect(page.getByText('Total Revenue')).toBeVisible();
});
