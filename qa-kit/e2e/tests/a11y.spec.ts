import AxeBuilder from '@axe-core/playwright';
import { test, expect } from '../support/fixtures';
import { Api, exampleOrder } from '../support/api';

async function critical(page: import('@playwright/test').Page) {
  const results = await new AxeBuilder({ page }).withTags(['wcag2a', 'wcag2aa']).analyze();
  return results.violations
    .filter((v) => v.impact === 'critical' || v.impact === 'serious')
    .map((v) => `${v.id} (${v.impact}): ${v.nodes.slice(0, 3).map((n) => n.target.join(' ')).join(', ')}`);
}

test.describe('accessibility smoke (no critical/serious axe violations)', () => {
  test('login', async ({ page }) => {
    await page.goto('/login');
    await expect(page.getByTestId('login-submit')).toBeVisible();
    expect(await critical(page)).toEqual([]);
  });

  test('purchases list', async ({ page, loginAs }) => {
    await loginAs('manager');
    await page.goto('/purchases');
    await expect(page.getByTestId('purchase-row').first()).toBeVisible();
    expect(await critical(page)).toEqual([]);
  });

  test('purchase detail', async ({ page, loginAs }) => {
    const admin = await Api.as('admin');
    const { purchase } = await exampleOrder(admin);
    await loginAs('manager');
    await page.goto(`/purchases/${purchase.id}`);
    await expect(page.getByTestId('purchase-number')).toBeVisible();
    expect(await critical(page)).toEqual([]);
  });

  test('wizard first step and status dialog', async ({ page, loginAs }) => {
    const admin = await Api.as('admin');
    const { purchase } = await exampleOrder(admin);
    await loginAs('manager');
    await page.goto('/purchases/new');
    await expect(page.getByTestId('wizard-step-1')).toBeVisible();
    expect(await critical(page)).toEqual([]);
    await page.goto(`/purchases/${purchase.id}`);
    await page.getByTestId('action-status').click();
    await expect(page.getByTestId('status-select')).toBeVisible();
    expect(await critical(page)).toEqual([]);
  });

  test('modal closes with Escape and returns focus', async ({ page, loginAs }) => {
    const admin = await Api.as('admin');
    const { purchase } = await exampleOrder(admin);
    await loginAs('manager');
    await page.goto(`/purchases/${purchase.id}`);
    await page.getByTestId('action-status').click();
    await expect(page.getByTestId('status-select')).toBeVisible();
    await page.keyboard.press('Escape');
    await expect(page.getByTestId('status-select')).toHaveCount(0);
    await expect(page.getByTestId('action-status')).toBeFocused();
  });
});
