import { test, expect } from '../support/fixtures';
import { Api, exampleOrder } from '../support/api';

test.describe('purchase detail — content', () => {
  test('shows summary, client info, items, timeline and activity', async ({ page, loginAs }) => {
    const admin = await Api.as('admin');
    const { purchase, client, a, b } = await exampleOrder(admin);
    await loginAs('manager');
    await page.goto(`/purchases/${purchase.id}`);

    await expect(page.getByTestId('purchase-number')).toHaveText(purchase.order_number);
    await expect(page.getByTestId('purchase-client-name')).toHaveText(client.name);
    await expect(page.getByTestId('purchase-client-email')).toHaveText(client.email);
    await expect(page.getByTestId('purchase-items-table')).toContainText(a.sku);
    await expect(page.getByTestId('purchase-items-table')).toContainText(b.sku);
    await expect(page.getByTestId('purchase-item-row')).toHaveCount(2);
    await expect(page.getByTestId('purchase-timeline')).toBeVisible();
    await expect(page.getByTestId('purchase-activity')).toBeVisible();
    for (const t of ['action-edit', 'action-status', 'action-assign', 'action-note', 'action-cancel']) {
      await expect(page.getByTestId(t)).toBeVisible();
    }
    await expect(page.getByTestId('action-delete')).toHaveCount(0); // Manager cannot delete
  });

  test('admin can delete a draft purchase after confirming', async ({ page, loginAs }) => {
    const admin = await Api.as('admin');
    const { purchase } = await exampleOrder(admin, { status: 'draft' });
    await loginAs('admin');
    await page.goto(`/purchases/${purchase.id}`);
    await page.getByTestId('action-delete').click();
    await page.getByTestId('confirm-ok').click();
    await expect(page).toHaveURL(/\/purchases$/);
    expect((await admin.get(`/purchases/${purchase.id}`)).status).toBe(404);
  });

  test('unknown purchase id shows a not-found/error state, not a blank page', async ({ page, loginAs }) => {
    await loginAs('manager');
    await page.goto('/purchases/99999999');
    await expect(page.getByTestId('not-found-page').or(page.getByTestId('purchase-detail-error'))).toBeVisible();
  });

  test('loading skeleton then content', async ({ page, loginAs }) => {
    const admin = await Api.as('admin');
    const { purchase } = await exampleOrder(admin);
    await loginAs('manager');
    await page.route(`**/api/purchases/${purchase.id}`, async (r) => { await new Promise((res) => setTimeout(res, 1000)); await r.continue(); });
    await page.goto(`/purchases/${purchase.id}`);
    await expect(page.getByTestId('purchase-detail-skeleton')).toBeVisible();
    await expect(page.getByTestId('purchase-number')).toBeVisible({ timeout: 10_000 });
  });
});

test.describe('purchase detail — staff', () => {
  test('staff sees only the actions they are allowed to use', async ({ page, loginAs }) => {
    const admin = await Api.as('admin');
    const staff = await Api.as('staff');
    const { purchase } = await exampleOrder(admin, { assigned_to: staff.session.user.id });
    await admin.setStatus(purchase.id, 'confirmed');

    await loginAs('staff');
    await page.goto(`/purchases/${purchase.id}`);
    await expect(page.getByTestId('purchase-number')).toBeVisible();
    for (const t of ['action-delete', 'action-cancel', 'action-assign', 'action-edit']) {
      await expect(page.getByTestId(t)).toHaveCount(0);
    }
    await page.getByTestId('action-status').click();
    const values = await page.getByTestId('status-select').locator('option').evaluateAll((os) => os.map((o) => (o as HTMLOptionElement).value).filter(Boolean));
    expect(values).toEqual(['processing']);
    await page.getByTestId('status-select').selectOption('processing');
    await page.getByTestId('status-submit').click();
    await expect(page.getByTestId('purchase-status')).toHaveText(/processing/i);
  });

  test('staff can add a note to an assigned purchase', async ({ page, loginAs }) => {
    const admin = await Api.as('admin');
    const staff = await Api.as('staff');
    const { purchase } = await exampleOrder(admin, { assigned_to: staff.session.user.id });
    await loginAs('staff');
    await page.goto(`/purchases/${purchase.id}`);
    await page.getByTestId('action-note').click();
    await page.getByTestId('note-input').fill('Packed and ready for dispatch');
    await page.getByTestId('note-submit').click();
    await expect(page.getByTestId('purchase-activity')).toContainText('Packed and ready for dispatch');
  });

  test('staff opening an unassigned purchase gets the forbidden page', async ({ page, loginAs }) => {
    const admin = await Api.as('admin');
    const { purchase } = await exampleOrder(admin);
    await loginAs('staff');
    await page.goto(`/purchases/${purchase.id}`);
    await expect(page.getByTestId('forbidden-page')).toBeVisible();
  });
});
