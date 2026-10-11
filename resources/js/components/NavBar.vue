<script setup>
import { useAuthStore } from '../stores/auth';
import { useRouter } from 'vue-router';

const auth = useAuthStore();
const router = useRouter();

const logout = () => {
  auth.logout();
  router.push('/login');
};
</script>

<template>
  <nav class="bg-white border-b border-[var(--color-line)] shadow-sm">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
      <div class="flex h-16 justify-between items-center">
        <div class="flex">
          <div class="flex flex-shrink-0 items-center">
            <span class="text-[var(--color-evergreen)] font-bold text-xl">vueVMS</span>
          </div>
          <div class="hidden sm:ml-6 sm:flex sm:space-x-6">
            <router-link to="/dashboard" class="inline-flex items-center border-b-2 border-transparent px-1 pt-1 text-sm font-medium text-[var(--color-muted)] hover:border-[var(--color-line)] hover:text-[var(--color-ink)]" active-class="border-[var(--color-evergreen)] text-[var(--color-ink)]">Dashboard</router-link>
            <router-link to="/purchases" class="inline-flex items-center border-b-2 border-transparent px-1 pt-1 text-sm font-medium text-[var(--color-muted)] hover:border-[var(--color-line)] hover:text-[var(--color-ink)]" active-class="border-[var(--color-evergreen)] text-[var(--color-ink)]">Purchases</router-link>
            <router-link to="/clients" class="inline-flex items-center border-b-2 border-transparent px-1 pt-1 text-sm font-medium text-[var(--color-muted)] hover:border-[var(--color-line)] hover:text-[var(--color-ink)]" active-class="border-[var(--color-evergreen)] text-[var(--color-ink)]">Clients</router-link>
            <router-link to="/items" class="inline-flex items-center border-b-2 border-transparent px-1 pt-1 text-sm font-medium text-[var(--color-muted)] hover:border-[var(--color-line)] hover:text-[var(--color-ink)]" active-class="border-[var(--color-evergreen)] text-[var(--color-ink)]">Items</router-link>
            <router-link to="/reports" v-if="auth.isAdmin || auth.isManager || auth.can('reports.view')" class="inline-flex items-center border-b-2 border-transparent px-1 pt-1 text-sm font-medium text-[var(--color-muted)] hover:border-[var(--color-line)] hover:text-[var(--color-ink)]" active-class="border-[var(--color-evergreen)] text-[var(--color-ink)]">Reports</router-link>
            <router-link to="/users" v-if="auth.isAdmin || auth.can('users.manage')" class="inline-flex items-center border-b-2 border-transparent px-1 pt-1 text-sm font-medium text-[var(--color-muted)] hover:border-[var(--color-line)] hover:text-[var(--color-ink)]" active-class="border-[var(--color-evergreen)] text-[var(--color-ink)]">Users</router-link>
            <router-link to="/audit-logs" v-if="auth.isAdmin || auth.can('audit.view')" class="inline-flex items-center border-b-2 border-transparent px-1 pt-1 text-sm font-medium text-[var(--color-muted)] hover:border-[var(--color-line)] hover:text-[var(--color-ink)]" active-class="border-[var(--color-evergreen)] text-[var(--color-ink)]">Audit Logs</router-link>
          </div>
        </div>
        <div class="flex items-center gap-4">
          <div class="text-sm text-[var(--color-muted)] hidden md:block">
            Press <kbd class="px-2 py-1 bg-gray-100 rounded text-xs">Ctrl</kbd> + <kbd class="px-2 py-1 bg-gray-100 rounded text-xs">K</kbd>
          </div>
          <button @click="logout" v-if="auth.isAuthenticated" class="text-sm font-medium text-[var(--color-muted)] hover:text-[var(--color-ink)]">
            Log out
          </button>
        </div>
      </div>
    </div>
  </nav>
</template>
