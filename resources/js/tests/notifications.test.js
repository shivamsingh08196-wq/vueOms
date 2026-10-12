import { describe, it, expect, beforeEach } from 'vitest';
import { setActivePinia, createPinia } from 'pinia';
import { useNotificationsStore } from '../stores/notifications.js';

describe('Notifications Store', () => {
  beforeEach(() => {
    setActivePinia(createPinia());
  });

  it('initializes with default empty notifications state', () => {
    const store = useNotificationsStore();
    expect(store.notifications).toEqual([]);
    expect(store.unreadCount).toBe(0);
    expect(store.isLoading).toBe(false);
  });
});

