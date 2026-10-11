<script setup>
import { ref, onMounted } from 'vue';
import api from '../api/client';

const purchasesCount = ref(0);
const clientsCount = ref(0);
const itemsCount = ref(0);
const isLoading = ref(true);

onMounted(async () => {
  try {
    const [purchases, clients, items] = await Promise.all([
      api.get('/api/purchases').catch(() => []),
      api.get('/api/clients').catch(() => []),
      api.get('/api/items').catch(() => [])
    ]);

    purchasesCount.value = Array.isArray(purchases) ? purchases.length : (purchases.data?.length || 0);
    clientsCount.value = Array.isArray(clients) ? clients.length : (clients.data?.length || 0);
    itemsCount.value = Array.isArray(items) ? items.length : (items.data?.length || 0);
  } finally {
    isLoading.value = false;
  }
});
</script>

<template>
  <div>
    <div class="sm:flex sm:items-center sm:justify-between mb-8">
      <div>
        <h1 class="text-2xl font-bold text-[var(--color-ink)]">Dashboard</h1>
        <p class="mt-1 text-sm text-[var(--color-muted)]">Overview of your vendor management system activity.</p>
      </div>
    </div>

    <!-- Stats Cards -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
      <div class="bg-white p-6 rounded-[var(--radius-card)] shadow-sm border border-[var(--color-line)] flex items-center justify-between">
        <div>
          <h3 class="text-sm font-medium text-[var(--color-muted)]">Total Purchases</h3>
          <p class="text-3xl font-bold text-[var(--color-evergreen)] mt-2">
            <span v-if="isLoading" class="text-gray-300">...</span>
            <span v-else>{{ purchasesCount }}</span>
          </p>
        </div>
        <div class="w-12 h-12 rounded-lg bg-teal-50 text-[var(--color-evergreen)] flex items-center justify-center">
          <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
          </svg>
        </div>
      </div>

      <div class="bg-white p-6 rounded-[var(--radius-card)] shadow-sm border border-[var(--color-line)] flex items-center justify-between">
        <div>
          <h3 class="text-sm font-medium text-[var(--color-muted)]">Active Clients</h3>
          <p class="text-3xl font-bold text-[var(--color-evergreen)] mt-2">
            <span v-if="isLoading" class="text-gray-300">...</span>
            <span v-else>{{ clientsCount }}</span>
          </p>
        </div>
        <div class="w-12 h-12 rounded-lg bg-teal-50 text-[var(--color-evergreen)] flex items-center justify-center">
          <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
          </svg>
        </div>
      </div>

      <div class="bg-white p-6 rounded-[var(--radius-card)] shadow-sm border border-[var(--color-line)] flex items-center justify-between">
        <div>
          <h3 class="text-sm font-medium text-[var(--color-muted)]">Total Items</h3>
          <p class="text-3xl font-bold text-[var(--color-evergreen)] mt-2">
            <span v-if="isLoading" class="text-gray-300">...</span>
            <span v-else>{{ itemsCount }}</span>
          </p>
        </div>
        <div class="w-12 h-12 rounded-lg bg-teal-50 text-[var(--color-evergreen)] flex items-center justify-center">
          <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
          </svg>
        </div>
      </div>
    </div>

    <!-- Quick Navigation Links -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
      <router-link to="/purchases" class="bg-white p-5 rounded-[var(--radius-card)] border border-[var(--color-line)] hover:border-[var(--color-evergreen)] transition-all shadow-sm group">
        <h4 class="font-semibold text-[var(--color-ink)] group-hover:text-[var(--color-evergreen)] flex items-center justify-between">
          <span>Manage Purchases</span>
          <span>&rarr;</span>
        </h4>
        <p class="text-xs text-[var(--color-muted)] mt-1">View purchases Kanban board and create orders.</p>
      </router-link>

      <router-link to="/clients" class="bg-white p-5 rounded-[var(--radius-card)] border border-[var(--color-line)] hover:border-[var(--color-evergreen)] transition-all shadow-sm group">
        <h4 class="font-semibold text-[var(--color-ink)] group-hover:text-[var(--color-evergreen)] flex items-center justify-between">
          <span>Manage Clients</span>
          <span>&rarr;</span>
        </h4>
        <p class="text-xs text-[var(--color-muted)] mt-1">Browse, create, and update client records.</p>
      </router-link>

      <router-link to="/items" class="bg-white p-5 rounded-[var(--radius-card)] border border-[var(--color-line)] hover:border-[var(--color-evergreen)] transition-all shadow-sm group">
        <h4 class="font-semibold text-[var(--color-ink)] group-hover:text-[var(--color-evergreen)] flex items-center justify-between">
          <span>Manage Inventory Items</span>
          <span>&rarr;</span>
        </h4>
        <p class="text-xs text-[var(--color-muted)] mt-1">Update stock levels, pricing, and catalog items.</p>
      </router-link>
    </div>
  </div>
</template>
