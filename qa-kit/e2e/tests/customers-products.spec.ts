import { test, expect, money } from '../support/fixtures';
import { Api, exampleOrder, uid } from '../support/api';

test.describe('clients', () => {
  test('manager creates a client through the UI and finds it by search', async ({ page, loginAs }) => {
    const id = uid();
    await loginAs('manager');
    await page.goto('/clients/new');
    await page.getByTestId('client-name').fill(`UI Client ${id}`);
    await page.getByTestId('client-email').fill(`ui-${id}@example.test`);
    await page.getByTestId('client-phone').fill('+91 99999 11111');
    await page.getByTestId('client-submit').click();
    await expect(page.getByTestId('toast')).toBeVisible();

    await page.goto('/clients');
    await page.getByTestId('clients-search').fill(`UI Client ${id}`);
    await expect(page.getByTestId('client-row')).toHaveCount(1);
  });

  test('duplicate email shows an inline error from the backend', async ({ page, loginAs }) => {
    const admin = await Api.as('admin');
    const existing = await admin.createCustomer();
    await loginAs('manager');
    await page.goto('/clients/new');
    await page.getByTestId('client-name').fill('Dup Person');
    await page.getByTestId('client-email').fill(existing.email);
    await page.getByTestId('client-submit').click();
    await expect(page.getByTestId('field-error').first()).toBeVisible();
    await expect(page).toHaveURL(/\/clients\/new$/);
  });

  test('detail page shows purchase statistics', async ({ page, loginAs }) => {
    const admin = await Api.as('admin');
    const client = await admin.createCustomer();
    const p = await admin.createProduct({ price: 100 });
    const mk = () => admin.createOrder({ customer_id: client.id, items: [{ product_id: p.id, quantity: 1 }] }); // 110 each incl. 10% tax
    const o1 = await mk();
    const o2 = await mk();
    await admin.setStatus(o2.id, 'cancelled', 'test');

    await loginAs('manager');
    await page.goto(`/clients/${client.id}`);
    await expect(page.getByTestId('client-stat-total')).toContainText('2');
    await expect(page.getByTestId('client-stat-pending')).toContainText('1');
    await expect(page.getByTestId('client-stat-cancelled')).toContainText('1');
    await expect(page.getByTestId('client-stat-completed')).toContainText('0');
    await expect(page.getByTestId('client-stat-value')).toContainText(money(110)); // cancelled purchases excluded
    await expect(page.getByTestId('client-recent-purchases')).toContainText(o1.order_number);
  });

  test('staff can view but not create clients', async ({ page, loginAs }) => {
    await loginAs('staff');
    await page.goto('/clients');
    await expect(page.getByTestId('client-row').first()).toBeVisible();
    await expect(page.getByTestId('client-new')).toHaveCount(0);
  });

  test('client API: validation errors and soft-delete protection', async () => {
    const admin = await Api.as('admin');
    const bad = await admin.post('/clients', { name: '', email: 'not-an-email' });
    expect(bad.status).toBe(422);
    expect(bad.body.errors).toHaveProperty('name');
    expect(bad.body.errors).toHaveProperty('email');
    const { client } = await exampleOrder(admin);
    const del = await admin.del(`/clients/${client.id}`);
    expect([200, 204, 422]).toContain(del.status); // deleted/deactivated or blocked – but never a 500
    const after = await admin.get(`/purchases?search=${encodeURIComponent(client.name)}`);
    expect(after.status).toBe(200);
    expect(after.body.data.length).toBeGreaterThan(0); // history preserved
  });
});

test.describe('items', () => {
  test('admin creates a item through the UI', async ({ page, loginAs }) => {
    const id = uid().toUpperCase();
    await loginAs('admin');
    await page.goto('/items/new');
    await page.getByTestId('item-name').fill(`UI Item ${id}`);
    await page.getByTestId('item-sku').fill(`UI-${id}`);
    await page.getByTestId('item-price').fill('49.99');
    await page.getByTestId('item-stock').fill('25');
    await page.getByTestId('item-submit').click();
    await expect(page.getByTestId('toast')).toBeVisible();
    await page.goto('/items');
    await page.getByTestId('items-search').fill(`UI-${id}`);
    await expect(page.getByTestId('item-row')).toHaveCount(1);
    await expect(page.getByTestId('item-row')).toContainText('$49.99');
  });

  test('duplicate SKU is rejected', async ({ page, loginAs }) => {
    const admin = await Api.as('admin');
    const p = await admin.createProduct();
    await loginAs('admin');
    await page.goto('/items/new');
    await page.getByTestId('item-name').fill('Another');
    await page.getByTestId('item-sku').fill(p.sku);
    await page.getByTestId('item-price').fill('10');
    await page.getByTestId('item-stock').fill('1');
    await page.getByTestId('item-submit').click();
    await expect(page.getByTestId('field-error').first()).toBeVisible();
  });

  test('manager and staff cannot create items', async ({ page, loginAs }) => {
    await loginAs('manager');
    await page.goto('/items');
    await expect(page.getByTestId('item-row').first()).toBeVisible();
    await expect(page.getByTestId('item-new')).toHaveCount(0);
    const manager = await Api.as('manager');
    expect((await manager.post('/items', { name: 'x', sku: `X-${uid()}`, price: 1, stock_quantity: 1 })).status).toBe(403);
  });

  test('item API validation and stock rules', async () => {
    const admin = await Api.as('admin');
    const bad = await admin.post('/items', { name: 'x', sku: '', price: -5, stock_quantity: -1 });
    expect(bad.status).toBe(422);
    expect(Object.keys(bad.body.errors)).toEqual(expect.arrayContaining(['sku', 'price', 'stock_quantity']));
    const p = await admin.createProduct({ stock_quantity: 10 });
    const adj = await admin.patch(`/items/${p.id}/stock`, { delta: -3, reason: 'damaged' });
    expect(adj.status).toBe(200);
    expect(await admin.stock(p.id)).toBe(7);
    const tooMuch = await admin.patch(`/items/${p.id}/stock`, { delta: -100, reason: 'oops' });
    expect(tooMuch.status).toBe(422);
    expect(await admin.stock(p.id)).toBe(7);
  });

  test('inactive items cannot be ordered', async () => {
    const admin = await Api.as('admin');
    const client = await admin.createCustomer();
    const p = await admin.createProduct({ status: 'inactive' });
    const r = await admin.post('/purchases', { customer_id: client.id, order_date: '2026-10-09', priority: 'normal', status: 'draft', items: [{ product_id: p.id, quantity: 1 }] });
    expect(r.status).toBe(422);
  });
});
