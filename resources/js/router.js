import { createRouter, createWebHistory } from 'vue-router';
import { useAuthStore } from './stores/auth';
import MainLayout from './layouts/MainLayout.vue';

const routes = [
  {
    path: '/login',
    name: 'Login',
    component: () => import('./pages/Login.vue'),
    meta: { guestOnly: true }
  },
  {
    path: '/',
    component: MainLayout,
    meta: { requiresAuth: true },
    children: [
      {
        path: '',
        redirect: '/dashboard'
      },
      {
        path: 'dashboard',
        name: 'Dashboard',
        component: () => import('./pages/Dashboard.vue')
      },
      {
        path: 'clients',
        name: 'Clients',
        component: () => import('./pages/ClientsList.vue')
      },
      {
        path: 'items',
        name: 'Items',
        component: () => import('./pages/ItemsList.vue')
      },
      {
        path: 'purchases',
        name: 'Purchases',
        component: () => import('./pages/PurchasesKanban.vue')
      },
      {
        path: 'reports',
        name: 'Reports',
        component: () => import('./pages/Reports.vue')
      },
      {
        path: 'users',
        name: 'Users',
        component: () => import('./pages/UsersList.vue')
      },
      {
        path: 'audit-logs',
        name: 'AuditLogs',
        component: () => import('./pages/AuditLogs.vue')
      }
    ]
  }
];

const router = createRouter({
  history: createWebHistory(),
  routes,
});

router.beforeEach((to, from, next) => {
  const authStore = useAuthStore();
  const isAuthenticated = authStore.isAuthenticated;

  if (to.meta.requiresAuth && !isAuthenticated) {
    next('/login');
  } else if (to.meta.guestOnly && isAuthenticated) {
    next('/dashboard');
  } else {
    next();
  }
});

export default router;
