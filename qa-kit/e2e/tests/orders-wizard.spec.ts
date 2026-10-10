import { test, expect, money } from '../support/fixtures';
import { Api, uid } from '../support/api';

test.describe('create purchase wizard', () => {
  test('creates the assessment example purchase and the server total is 363', async ({ page, loginAs }) => {
    const admin = await Api.as('admin');
    const client = await admin.createCustomer();
    const a = await admin.createProduct({ price: 100, stock_quantity: 100 });
    const b = await admin.createProduct({ price: 50, stock_quantity: 100 });

    await loginAs('manager');
    await page.goto('/purchases/new');

    // Step 1 – client
    await expect(page.getByTestId('wizard-step-1')).toBeVisible();
    await page.getByTestId('wizard-client-search').fill(client.name);
    await page.getByTestId('wizard-client-option').first().click();
    await page.getByTestId('wizard-next').click();

    // Step 2 – items
    await expect(page.getByTestId('wizard-step-2')).toBeVisible();
    await page.getByTestId('wizard-item-search').fill(a.sku);
    await page.getByTestId('wizard-item-option').first().click();
    await page.getByTestId('wizard-qty-0').fill('2');
    await page.getByTestId('wizard-item-search').fill(b.sku);
    await page.getByTestId('wizard-item-option').first().click();
    await page.getByTestId('wizard-qty-1').fill('3');
    await page.getByTestId('wizard-next').click();

    // Step 3 – pricing (server-calculated preview)
    await expect(page.getByTestId('wizard-step-3')).toBeVisible();
    await page.getByTestId('wizard-discount-type').selectOption('fixed');
    await page.getByTestId('wizard-discount-value').fill('20');
    await expect(page.getByTestId('wizard-preview-total')).toHaveText(money(363));
    await page.getByTestId('wizard-next').click();

    // Step 4 – assignment & priority
    await expect(page.getByTestId('wizard-step-4')).toBeVisible();
    await page.getByTestId('wizard-priority').selectOption('high');
    await page.getByTestId('wizard-assignee').selectOption({ label: 'Staff User' });
    await page.getByTestId('wizard-status').selectOption('pending');
    await page.getByTestId('wizard-notes').fill('Created by Playwright');
    await page.getByTestId('wizard-next').click();

    // Step 5 – review & submit
    await expect(page.getByTestId('wizard-step-5')).toBeVisible();
    await expect(page.getByTestId('wizard-review-client')).toContainText(client.name);
    await expect(page.getByTestId('wizard-review-total')).toHaveText(money(363));
    await page.getByTestId('wizard-submit').click();

    await expect(page).toHaveURL(/\/purchases\/\d+$/);
    await expect(page.getByTestId('purchase-number')).toHaveText(/ORD-\d+/);
    await expect(page.getByTestId('purchase-total')).toHaveText(money(363));
    await expect(page.getByTestId('purchase-status')).toHaveText(/pending/i);
    await expect(page.getByTestId('purchase-priority')).toHaveText(/high/i);
    await expect(page.getByTestId('purchase-assignee')).toContainText('Staff User');
    await expect(page.getByTestId('purchase-item-row')).toHaveCount(2);
  });

  test('cannot leave step 1 without a client', async ({ page, loginAs }) => {
    await loginAs('manager');
    await page.goto('/purchases/new');
    await page.getByTestId('wizard-next').click();
    await expect(page.getByTestId('field-error').first()).toContainText('Client is required.');
    await expect(page.getByTestId('wizard-step-1')).toBeVisible();
  });

  test('cannot leave step 2 without items, or with quantity 0', async ({ page, loginAs }) => {
    const admin = await Api.as('admin');
    const client = await admin.createCustomer();
    const p = await admin.createProduct();
    await loginAs('manager');
    await page.goto('/purchases/new');
    await page.getByTestId('wizard-client-search').fill(client.name);
    await page.getByTestId('wizard-client-option').first().click();
    await page.getByTestId('wizard-next').click();

    await page.getByTestId('wizard-next').click();
    await expect(page.getByTestId('field-error').first()).toContainText('Purchase must contain at least one item.');

    await page.getByTestId('wizard-item-search').fill(p.sku);
    await page.getByTestId('wizard-item-option').first().click();
    await page.getByTestId('wizard-qty-0').fill('0');
    await page.getByTestId('wizard-next').click();
    await expect(page.getByTestId('field-error').first()).toContainText('Quantity must be greater than 0.');
    await expect(page.getByTestId('wizard-step-2')).toBeVisible();
  });

  test('a tampered total in the request is ignored by the server', async ({ page, loginAs }) => {
    const admin = await Api.as('admin');
    const client = await admin.createCustomer();
    const p = await admin.createProduct({ price: 100 });
    await loginAs('manager');
    await page.route('**/api/purchases', async (route) => {
      if (route.request().method() !== 'POST') return route.continue();
      const body = route.request().postDataJSON();
      await route.continue({ postData: JSON.stringify({ ...body, total: 1, subtotal: 1, tax_amount: 0 }) });
    });
    await page.goto('/purchases/new');
    await page.getByTestId('wizard-client-search').fill(client.name);
    await page.getByTestId('wizard-client-option').first().click();
    await page.getByTestId('wizard-next').click();
    await page.getByTestId('wizard-item-search').fill(p.sku);
    await page.getByTestId('wizard-item-option').first().click();
    await page.getByTestId('wizard-qty-0').fill('1');
    await page.getByTestId('wizard-next').click();
    await page.getByTestId('wizard-next').click();
    await page.getByTestId('wizard-status').selectOption('draft');
    await page.getByTestId('wizard-next').click();
    await page.getByTestId('wizard-submit').click();
    await expect(page).toHaveURL(/\/purchases\/\d+$/);
    await expect(page.getByTestId('purchase-total')).toHaveText(money(110)); // 100 + 10% tax
  });

  test('duplicate item selection is merged, not duplicated', async ({ page, loginAs }) => {
    const admin = await Api.as('admin');
    const client = await admin.createCustomer();
    const p = await admin.createProduct({ name: `Dup ${uid()}` });
    await loginAs('manager');
    await page.goto('/purchases/new');
    await page.getByTestId('wizard-client-search').fill(client.name);
    await page.getByTestId('wizard-client-option').first().click();
    await page.getByTestId('wizard-next').click();
    for (let i = 0; i < 2; i++) {
      await page.getByTestId('wizard-item-search').fill(p.sku);
      await page.getByTestId('wizard-item-option').first().click();
    }
    await expect(page.getByTestId('wizard-qty-1')).toHaveCount(0);
    await expect(page.getByTestId('wizard-qty-0')).toHaveValue(/^[2-9]\d*$/); // incremented
  });
});
