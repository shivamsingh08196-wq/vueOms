import { test, expect } from '../support/fixtures';
import { Api, exampleOrder, uid } from '../support/api';

test.describe('role-based UI', () => {
  test('staff sees only permitted navigation and no create button', async ({ page, loginAs }) => {
    await loginAs('staff');
    await page.goto('/purchases');
    await expect(page.getByTestId('nav-purchases')).toBeVisible();
    await expect(page.getByTestId('nav-clients')).toBeVisible();
    await expect(page.getByTestId('nav-items')).toBeVisible();
    for (const id of ['nav-users', 'nav-audit', 'nav-dashboard', 'purchases-new']) {
      await expect(page.getByTestId(id)).toHaveCount(0);
    }
  });

  test('manager has dashboard + purchases but no users/audit', async ({ page, loginAs }) => {
    await loginAs('manager');
    await page.goto('/dashboard');
    await expect(page.getByTestId('nav-dashboard')).toBeVisible();
    await expect(page.getByTestId('nav-purchases')).toBeVisible();
    await expect(page.getByTestId('nav-users')).toHaveCount(0);
    await expect(page.getByTestId('nav-audit')).toHaveCount(0);
  });

  test('admin sees everything', async ({ page, loginAs }) => {
    await loginAs('admin');
    await page.goto('/dashboard');
    for (const id of ['nav-dashboard', 'nav-purchases', 'nav-clients', 'nav-items', 'nav-users', 'nav-audit']) {
      await expect(page.getByTestId(id)).toBeVisible();
    }
  });

  for (const [role, path] of [['staff', '/users'], ['staff', '/audit-logs'], ['staff', '/dashboard'], ['staff', '/purchases/new'], ['manager', '/users'], ['manager', '/audit-logs']] as const) {
    test(`${role} opening ${path} gets the forbidden page`, async ({ page, loginAs }) => {
      await loginAs(role);
      await page.goto(path);
      await expect(page.getByTestId('forbidden-page')).toBeVisible();
    });
  }
});

test.describe('backend enforces authorization independently of the UI', () => {
  test('staff is rejected by the API on restricted actions', async () => {
    const admin = await Api.as('admin');
    const staff = await Api.as('staff');
    const { purchase } = await exampleOrder(admin);
    expect((await staff.post('/purchases', {})).status).toBe(403);
    expect((await staff.del(`/purchases/${purchase.id}`)).status).toBe(403);
    expect((await staff.post('/items', { name: 'x', sku: `X-${uid()}`, price: 1, stock_quantity: 1 })).status).toBe(403);
    expect((await staff.get('/users')).status).toBe(403);
    expect((await staff.get('/audit-logs')).status).toBe(403);
    expect((await staff.get('/dashboard/summary')).status).toBe(403);
  });

  test('manager cannot manage users, audit logs, items or delete purchases', async () => {
    const admin = await Api.as('admin');
    const manager = await Api.as('manager');
    const { purchase } = await exampleOrder(admin);
    expect((await manager.get('/users')).status).toBe(403);
    expect((await manager.get('/audit-logs')).status).toBe(403);
    expect((await manager.post('/items', { name: 'x', sku: `X-${uid()}`, price: 1, stock_quantity: 1 })).status).toBe(403);
    expect((await manager.del(`/purchases/${purchase.id}`)).status).toBe(403);
  });

  test('staff only sees purchases assigned to them', async () => {
    const admin = await Api.as('admin');
    const staff = await Api.as('staff');
    const { purchase: mine } = await exampleOrder(admin, { assigned_to: staff.session.user.id });
    const { purchase: other } = await exampleOrder(admin);
    expect((await staff.get(`/purchases/${mine.id}`)).status).toBe(200);
    expect((await staff.get(`/purchases/${other.id}`)).status).toBe(403);
    const list = await staff.get('/purchases?per_page=100');
    const ids: number[] = list.body.data.map((o: any) => o.id);
    expect(ids).toContain(mine.id);
    expect(ids).not.toContain(other.id);
  });
});
