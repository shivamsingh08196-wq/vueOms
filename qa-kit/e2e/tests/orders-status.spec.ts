import { test, expect } from '../support/fixtures';
import { Api, apiLogin, exampleOrder } from '../support/api';

const label = (s: string) => new RegExp(`^${s}$`, 'i');

async function optionValues(page: import('@playwright/test').Page) {
  return page.getByTestId('status-select').locator('option').evaluateAll((os) => os.map((o) => (o as HTMLOptionElement).value).filter(Boolean));
}

test.describe('purchase status workflow (UI)', () => {
  test('walks pending → confirmed → processing → ready → completed with history', async ({ page, loginAs }) => {
    const admin = await Api.as('admin');
    const { purchase } = await exampleOrder(admin);
    await loginAs('admin');
    await page.goto(`/purchases/${purchase.id}`);
    await expect(page.getByTestId('purchase-status')).toHaveText(label('pending'));

    for (const next of ['confirmed', 'processing', 'ready', 'completed']) {
      await page.getByTestId('action-status').click();
      await page.getByTestId('status-select').selectOption(next);
      await page.getByTestId('status-remarks').fill(`moving to ${next}`);
      await page.getByTestId('status-submit').click();
      await expect(page.getByTestId('purchase-status')).toHaveText(label(next));
      await expect(page.getByTestId('purchase-timeline')).toContainText(new RegExp(next, 'i'));
    }

    await expect(page.getByTestId('purchase-activity')).toContainText('moving to ready');
    // terminal: no more status changes or edits, but notes are still possible
    await expect(page.getByTestId('action-status')).toHaveCount(0);
    await expect(page.getByTestId('action-edit')).toHaveCount(0);
    await expect(page.getByTestId('action-cancel')).toHaveCount(0);
    await expect(page.getByTestId('action-note')).toBeVisible();
  });

  test('the status dialog only offers valid transitions', async ({ page, loginAs }) => {
    const admin = await Api.as('admin');
    const { purchase } = await exampleOrder(admin); // pending
    await loginAs('admin');
    await page.goto(`/purchases/${purchase.id}`);
    await page.getByTestId('action-status').click();
    expect((await optionValues(page)).sort()).toEqual(['cancelled', 'confirmed', 'draft'].sort());
  });

  test('cancelling a confirmed purchase requires a reason and restores stock', async ({ page, loginAs }) => {
    const admin = await Api.as('admin');
    const client = await admin.createCustomer();
    const item = await admin.createProduct({ price: 100, stock_quantity: 10 });
    const purchase = await admin.createOrder({ customer_id: client.id, items: [{ product_id: item.id, quantity: 4 }] });
    expect((await admin.setStatus(purchase.id, 'confirmed')).status).toBe(200);
    expect(await admin.stock(item.id)).toBe(6);

    await loginAs('admin');
    await page.goto(`/purchases/${purchase.id}`);
    await page.getByTestId('action-cancel').click();
    await page.getByTestId('confirm-ok').click(); // reason missing
    await expect(page.getByTestId('field-error').first()).toBeVisible();
    await expect(page.getByTestId('purchase-status')).not.toHaveText(label('cancelled'));

    await page.getByTestId('cancel-reason').fill('Client changed their mind');
    await page.getByTestId('confirm-ok').click();
    await expect(page.getByTestId('purchase-status')).toHaveText(label('cancelled'));
    expect(await admin.stock(item.id)).toBe(10);
  });

  test('confirming with insufficient stock shows an error and changes nothing', async ({ page, loginAs }) => {
    const admin = await Api.as('admin');
    const client = await admin.createCustomer();
    const item = await admin.createProduct({ stock_quantity: 2 });
    const purchase = await admin.createOrder({ customer_id: client.id, items: [{ product_id: item.id, quantity: 5 }] });
    await loginAs('admin');
    await page.goto(`/purchases/${purchase.id}`);
    await page.getByTestId('action-status').click();
    await page.getByTestId('status-select').selectOption('confirmed');
    await page.getByTestId('status-submit').click();
    await expect(page.getByTestId('toast')).toContainText(/insufficient stock/i);
    await expect(page.getByTestId('purchase-status')).toHaveText(label('pending'));
    expect(await admin.stock(item.id)).toBe(2);
  });

  test('assigning an purchase updates the assignee and the activity feed', async ({ page, loginAs }) => {
    const admin = await Api.as('admin');
    const { purchase } = await exampleOrder(admin);
    await loginAs('manager');
    await page.goto(`/purchases/${purchase.id}`);
    await page.getByTestId('action-assign').click();
    await page.getByTestId('assign-select').selectOption({ label: 'Second Staff' });
    await page.getByTestId('assign-submit').click();
    await expect(page.getByTestId('purchase-assignee')).toContainText('Second Staff');
    await expect(page.getByTestId('purchase-activity')).toContainText(/assign/i);
  });

  test('adding a note shows it in the activity feed', async ({ page, loginAs }) => {
    const admin = await Api.as('admin');
    const { purchase } = await exampleOrder(admin);
    await loginAs('manager');
    await page.goto(`/purchases/${purchase.id}`);
    await page.getByTestId('action-note').click();
    await page.getByTestId('note-input').fill('<b>bold?</b> Call client tomorrow');
    await page.getByTestId('note-submit').click();
    const feed = page.getByTestId('purchase-activity');
    await expect(feed).toContainText('Call client tomorrow');
    await expect(feed).toContainText('<b>bold?</b>'); // rendered as text, not HTML (XSS safety)
  });

  test('edit conflict: saving a stale version shows a conflict message', async ({ browser }) => {
    const admin = await Api.as('admin');
    const { purchase } = await exampleOrder(admin, { status: 'draft' });
    const ctx = await browser.newContext();
    const page = await ctx.newPage();
    const { token, user } = await apiLogin('admin');
    await page.addInitScript(([t, u]) => localStorage.setItem('vms-auth', JSON.stringify({ state: { token: t, user: u }, version: 0 })), [token, user] as const);
    await page.goto(`/purchases/${purchase.id}/edit`);
    await expect(page.getByTestId('edit-priority')).toBeVisible();

    // someone else edits first
    const r = await admin.put(`/purchases/${purchase.id}`, { priority: 'urgent', version: purchase.version });
    expect(r.status).toBe(200);

    await page.getByTestId('edit-priority').selectOption('low');
    await page.getByTestId('edit-save').click();
    await expect(page.getByTestId('toast')).toContainText(/modified by another user|reload/i);
    await ctx.close();
  });
});

test.describe('purchase status workflow (API)', () => {
  const invalid: [string, string][] = [['draft', 'ready'], ['draft', 'completed'], ['pending', 'processing'], ['confirmed', 'draft'], ['confirmed', 'ready']];
  for (const [from, to] of invalid) {
    test(`${from} → ${to} is rejected with 422 and leaves the purchase unchanged`, async () => {
      const admin = await Api.as('admin');
      const { purchase } = await exampleOrder(admin, { status: from === 'draft' ? 'draft' : 'pending' });
      if (from === 'confirmed') await admin.setStatus(purchase.id, 'confirmed');
      const r = await admin.setStatus(purchase.id, to);
      expect(r.status).toBe(422);
      expect(r.body.code).toBe('INVALID_STATUS_TRANSITION');
      const after = await admin.get(`/purchases/${purchase.id}`);
      expect(after.body.data.status).toBe(from);
    });
  }

  test('terminal states cannot be left', async () => {
    const admin = await Api.as('admin');
    const { purchase } = await exampleOrder(admin);
    await admin.setStatus(purchase.id, 'cancelled', 'test');
    for (const to of ['pending', 'processing', 'draft', 'completed']) {
      expect((await admin.setStatus(purchase.id, to)).status).toBe(422);
    }
  });

  test('staff may advance an assigned purchase but not cancel or confirm', async () => {
    const admin = await Api.as('admin');
    const staff = await Api.as('staff');
    const { purchase } = await exampleOrder(admin, { assigned_to: staff.session.user.id });
    expect((await staff.setStatus(purchase.id, 'confirmed')).status).toBe(403);
    expect((await admin.setStatus(purchase.id, 'confirmed')).status).toBe(200);
    expect((await staff.setStatus(purchase.id, 'cancelled', 'no')).status).toBe(403);
    expect((await staff.setStatus(purchase.id, 'processing')).status).toBe(200);
    expect((await staff.setStatus(purchase.id, 'ready')).status).toBe(200);
  });

  test('every status change is recorded in the history', async () => {
    const admin = await Api.as('admin');
    const { purchase } = await exampleOrder(admin);
    await admin.setStatus(purchase.id, 'confirmed', 'Client confirmed the purchase.');
    const detail = await admin.get(`/purchases/${purchase.id}`);
    const h = detail.body.data.status_history;
    const entry = h.find((x: any) => x.to_status === 'confirmed');
    expect(entry).toBeTruthy();
    expect(entry.from_status).toBe('pending');
    expect(entry.remarks).toBe('Client confirmed the purchase.');
    expect(entry.user?.name ?? entry.changed_by?.name).toBeTruthy();
  });
});
