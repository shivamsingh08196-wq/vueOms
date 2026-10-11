import { describe, it, expect, beforeEach } from 'vitest';
import { setActivePinia, createPinia } from 'pinia';
import { useAuthStore } from '../stores/auth.js';

describe('Auth Store', () => {
  beforeEach(() => {
    setActivePinia(createPinia());
    localStorage.clear();
  });

  it('initializes with unauthenticated state when localStorage is empty', () => {
    const auth = useAuthStore();
    expect(auth.isAuthenticated).toBe(false);
    expect(auth.user).toBeNull();
    expect(auth.token).toBeNull();
  });

  it('authenticates user upon successful login', async () => {
    const auth = useAuthStore();
    const success = await auth.login({ email: 'admin@vueoms.com', password: 'password' });
    
    expect(success).toBe(true);
    expect(auth.isAuthenticated).toBe(true);
    expect(auth.user.email).toBe('admin@vueoms.com');
    expect(auth.token).toBe('mock-jwt-token-123');
  });

  it('clears credentials on logout', async () => {
    const auth = useAuthStore();
    await auth.login({ email: 'admin@vueoms.com', password: 'password' });
    expect(auth.isAuthenticated).toBe(true);

    await auth.logout();
    expect(auth.isAuthenticated).toBe(false);
    expect(auth.user).toBeNull();
    expect(auth.token).toBeNull();
  });
});
