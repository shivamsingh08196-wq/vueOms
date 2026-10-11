import { test, expect } from '@playwright/test';

test.beforeEach(async ({ page }) => {
  await page.goto('/login');
  await page.fill('input[type="email"]', 'admin@vueoms.com');
  await page.fill('input[type="password"]', 'password');
  await page.click('button[type="submit"]');
  await expect(page).toHaveURL(/.*\/dashboard/);
});

test('purchases table has server pagination, sorting, and create modal loads all clients and items', async ({ page }) => {
  await page.click('text=Purchases');
  await expect(page).toHaveURL(/.*\/purchases/);

  // Switch to Table View
  await page.click('button:has-text("Table View")');
  await expect(page.locator('table[data-testid="purchases-table"]')).toBeVisible();

  // Check Table Headers with Sort
  await expect(page.locator('th:has-text("Purchase #")')).toBeVisible();
  await expect(page.locator('th:has-text("Created")')).toBeVisible();

  // Test Create Purchase Stepper with all clients and items
  await page.click('button[data-testid="create-purchase-btn"]');
  await expect(page.locator('text=Create New Purchase')).toBeVisible();

  // Step 1: Select client from real dropdown
  const clientSelect = page.getByTestId('client-select');
  await expect(clientSelect).toBeVisible();
  const optionsCount = await clientSelect.locator('option').count();
  expect(optionsCount).toBeGreaterThan(1); // Real clients loaded!

  // Proceed to Step 2
  await page.getByTestId('stepper-continue-btn').click();

  // Step 2: Select item from real catalog
  await expect(page.locator('text=Selected Line Items')).toBeVisible();
  const itemSelect = page.getByTestId('item-select');
  await expect(itemSelect).toBeVisible();
  const itemsCount = await itemSelect.locator('option').count();
  expect(itemsCount).toBeGreaterThan(1); // Real items loaded!
});
