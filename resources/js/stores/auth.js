import { defineStore } from 'pinia';
import { ref } from 'vue';

export const useAuthStore = defineStore('auth', () => {
  // Mock auth for frontend architecture
  const user = ref(JSON.parse(localStorage.getItem('user')) || null);
  const token = ref(localStorage.getItem('token') || null);
  
  const isAuthenticated = ref(!!token.value);

  const login = async (credentials) => {
    // Mock login
    if (credentials.email && credentials.password) {
      const mockUser = { id: 1, name: 'Admin', email: credentials.email };
      const mockToken = 'mock-jwt-token-123';
      
      user.value = mockUser;
      token.value = mockToken;
      isAuthenticated.value = true;
      
      localStorage.setItem('user', JSON.stringify(mockUser));
      localStorage.setItem('token', mockToken);
      
      return true;
    }
    throw new Error('Invalid credentials');
  };

  const logout = () => {
    user.value = null;
    token.value = null;
    isAuthenticated.value = false;
    localStorage.removeItem('user');
    localStorage.removeItem('token');
  };

  return { user, token, isAuthenticated, login, logout };
});
