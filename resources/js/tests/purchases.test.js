import { describe, it, expect, beforeEach } from 'vitest';
import { setActivePinia, createPinia } from 'pinia';
import { usePurchasesStore } from '../stores/purchases.js';

describe('Purchases Store', () => {
  beforeEach(() => {
    setActivePinia(createPinia());
  });

  it('manages purchase detail drawer visibility and selected id', () => {
    const store = usePurchasesStore();
    expect(store.isDetailDrawerOpen).toBe(false);
    expect(store.selectedPurchaseId).toBeNull();

    store.openPurchaseDetail(42);
    expect(store.isDetailDrawerOpen).toBe(true);
    expect(store.selectedPurchaseId).toBe(42);

    store.closePurchaseDetail();
    expect(store.isDetailDrawerOpen).toBe(false);
    expect(store.selectedPurchaseId).toBeNull();
  });

  it('manages create stepper modal visibility', () => {
    const store = usePurchasesStore();
    expect(store.isCreateStepperOpen).toBe(false);

    store.openCreateStepper();
    expect(store.isCreateStepperOpen).toBe(true);

    store.closeCreateStepper();
    expect(store.isCreateStepperOpen).toBe(false);
  });
});
