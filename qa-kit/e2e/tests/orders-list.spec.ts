import { test, expect } from '../support/fixtures';
import { Api, uid } from '../support/api';

/** Creates N purchases for one unique client so searches are isolated from other tests' data. */
async function seedOrders(totals: number[], over: (i: number) => Record<string, unknown> = () => ({})) {
  const admin = await Api.as('admin');
  const client = await admin.createCustomer({ name: `ListCo ${uid()}` });
  const purchases = [];
  for (let i = 0; i < totals.length; i++) {
    const p = await admin.createProduct({ price: totals[i] });
    purchases.push(await admin.createOrder({ customer_id: client.id, items: [{ product_id: p.id, quantity: 1 }], ...over(i) }));
  }
  return { admin, client, purchases };
}

test.describe('purchase listing', () => {
  test('search by client name, purchase number and client email', async ({ page, loginAs }) => {
    const { client, purchases } = await seedOrders([10, 20, 30]);
    await loginAs('manager');
    await page.goto('/purchases');

    await page.getByTestId('purchases-search').fill(client.name);
    await expect(page.getByTestId('purchase-row')).toHaveCount(3);

    await page.getByTestId('purchases-search').fill(purchases[1].order_number);
    await expect(page.getByTestId('purchase-row')).toHaveCount(1);
    await expect(page.getByTestId('purchase-row').first()).toHaveAttribute('data-purchase-number', purchases[1].order_number);

    await page.getByTestId('purchases-search').fill(client.email);
    await expect(page.getByTestId('purchase-row')).toHaveCount(3);
  });

  test('filters by status and priority and keeps state in the URL', async ({ page, loginAs }) => {
    const { admin, client, purchases } = await seedOrders([10, 20, 30], (i) => ({ priority: i === 0 ? 'urgent' : 'normal' }));
    await admin.setStatus(purchases[2].id, 'confirmed');
    await loginAs('manager');
    await page.goto(`/purchases?search=${encodeURIComponent(client.name)}`);
    await expect(page.getByTestId('purchase-row')).toHaveCount(3);

    await page.getByTestId('purchases-filter-status').selectOption('confirmed');
    await expect(page.getByTestId('purchase-row')).toHaveCount(1);
    await expect(page).toHaveURL(/status=confirmed/);

    await page.getByTestId('purchases-filter-status').selectOption('');
    await page.getByTestId('purchases-filter-priority').selectOption('urgent');
    await expect(page.getByTestId('purchase-row')).toHaveCount(1);
    await expect(page.getByTestId('purchase-row').first()).toHaveAttribute('data-purchase-number', purchases[0].order_number);

    await page.reload();
    await expect(page.getByTestId('purchases-filter-priority')).toHaveValue('urgent');
    await expect(page.getByTestId('purchase-row')).toHaveCount(1);
  });

  test('sorts by total ascending and descending', async ({ page, loginAs }) => {
    const { client } = await seedOrders([30, 10, 20]);
    await loginAs('manager');
    await page.goto(`/purchases?search=${encodeURIComponent(client.name)}`);
    await expect(page.getByTestId('purchase-row')).toHaveCount(3);

    const totals = async () => (await page.getByTestId('purchase-row').evaluateAll((rows) => rows.map((r) => Number(r.getAttribute('data-total')))));
    await page.getByTestId('purchases-sort-total').click();
    await expect.poll(totals).toEqual([11, 22, 33]);
    await page.getByTestId('purchases-sort-total').click();
    await expect.poll(totals).toEqual([33, 22, 11]);
  });

  test('paginates on the server', async ({ page, loginAs }) => {
    const { client } = await seedOrders([10, 20, 30]);
    await loginAs('manager');
    await page.goto(`/purchases?search=${encodeURIComponent(client.name)}&per_page=2`);
    await expect(page.getByTestId('purchase-row')).toHaveCount(2);
    await expect(page.getByTestId('purchases-page-info')).toHaveText(/Page 1 of 2/);
    await page.getByTestId('purchases-next').click();
    await expect(page.getByTestId('purchases-page-info')).toHaveText(/Page 2 of 2/);
    await expect(page.getByTestId('purchase-row')).toHaveCount(1);
    await expect(page.getByTestId('purchases-next')).toBeDisabled();
  });

  test('empty state when nothing matches', async ({ page, loginAs }) => {
    await loginAs('manager');
    await page.goto(`/purchases?search=zzz-no-match-${uid()}`);
    await expect(page.getByTestId('purchases-empty')).toContainText('No purchases found.');
    await expect(page.getByTestId('purchase-row')).toHaveCount(0);
  });

  test('error state with retry', async ({ page, loginAs }) => {
    await loginAs('manager');
    await page.route('**/api/purchases?**', (r) => r.fulfill({ status: 500, json: { success: false, message: 'Server error', code: 'SERVER_ERROR', errors: {} } }));
    await page.goto('/purchases');
    await expect(page.getByTestId('purchases-error')).toContainText('Unable to load purchases.');
    await page.unroute('**/api/purchases?**');
    await page.getByTestId('purchases-retry').click();
    await expect(page.getByTestId('purchases-error')).toHaveCount(0);
    await expect(page.getByTestId('purchase-row').first()).toBeVisible();
  });

  test('loading skeleton is shown while the request is in flight', async ({ page, loginAs }) => {
    await loginAs('manager');
    await page.route('**/api/purchases?**', async (r) => { await new Promise((res) => setTimeout(res, 1200)); await r.continue(); });
    await page.goto('/purchases');
    await expect(page.getByTestId('purchases-skeleton')).toBeVisible();
    await expect(page.getByTestId('purchase-row').first()).toBeVisible({ timeout: 10_000 });
    await expect(page.getByTestId('purchases-skeleton')).toHaveCount(0);
  });

  test('typing in search is debounced (does not fire one request per keystroke)', async ({ page, loginAs }) => {
    await loginAs('manager');
    await page.goto('/purchases');
    await expect(page.getByTestId('purchase-row').first()).toBeVisible();
    let calls = 0;
    page.on('request', (r) => { if (r.url().includes('/api/purchases?') && r.method() === 'GET') calls++; });
    await page.getByTestId('purchases-search').pressSequentially('ORD-1000', { delay: 40 });
    await expect(page.getByTestId('purchases-search')).toHaveValue('ORD-1000');
    await page.waitForLoadState('networkidle');
    expect(calls).toBeLessThanOrEqual(2);
  });

  test('list API never returns more than 100 rows and rejects unknown sort columns', async () => {
    const admin = await Api.as('admin');
    const big = await admin.get('/purchases?per_page=1000');
    expect(big.status).toBe(200);
    expect(big.body.meta.per_page).toBeLessThanOrEqual(100);
    const bad = await admin.get('/purchases?sort=' + encodeURIComponent('id; DROP TABLE purchases'));
    expect(bad.status).toBe(422);
  });
});
