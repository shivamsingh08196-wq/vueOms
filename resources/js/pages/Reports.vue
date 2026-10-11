<script setup>
import { ref, onMounted } from 'vue';
import api from '../api/client';

const reports = ref(null);
const isLoading = ref(true);
const errorMessage = ref('');

const fetchReports = async () => {
  isLoading.value = true;
  errorMessage.value = '';
  try {
    const data = await api.get('/api/reports');
    reports.value = data;
  } catch (err) {
    errorMessage.value = err.message || 'Failed to generate operational reports.';
  } finally {
    isLoading.value = false;
  }
};

const formatCurrency = (val) => {
  return new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(val || 0);
};

const printReport = () => {
  window.print();
};

onMounted(() => {
  fetchReports();
});
</script>

<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
      <div>
        <h1 class="text-2xl font-bold tracking-tight text-[var(--color-ink)]">Analytics & Reports</h1>
        <p class="text-sm text-[var(--color-muted)] mt-1">Financial performance, order velocity metrics, and client volume breakdown.</p>
      </div>
      <div class="flex items-center gap-3">
        <button
          @click="printReport"
          class="inline-flex items-center gap-2 rounded-lg border border-[var(--color-line)] bg-white px-3 py-2 text-xs font-semibold text-[var(--color-ink)] shadow-sm hover:bg-gray-50"
        >
          <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
          </svg>
          Print / Export PDF
        </button>
        <button
          @click="fetchReports"
          class="inline-flex items-center gap-2 rounded-lg bg-[var(--color-evergreen)] px-3 py-2 text-xs font-semibold text-white shadow-sm hover:opacity-90"
        >
          <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
          </svg>
          Refresh Data
        </button>
      </div>
    </div>

    <div v-if="isLoading" class="p-12 text-center text-sm text-[var(--color-muted)]">
      <div class="animate-spin inline-block w-8 h-8 border-[3px] border-current border-t-transparent text-[var(--color-evergreen)] rounded-full mb-3"></div>
      <div>Compiling system analytics...</div>
    </div>

    <div v-else-if="errorMessage" class="p-8 text-center text-sm text-red-600 bg-red-50 rounded-xl border border-red-200">
      <p>{{ errorMessage }}</p>
      <button @click="fetchReports" class="mt-2 text-sm font-medium underline text-[var(--color-evergreen)]">Retry</button>
    </div>

    <div v-else-if="reports" class="space-y-6">
      <!-- KPI Overview Cards -->
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="p-5 rounded-xl border border-[var(--color-line)] bg-white shadow-sm">
          <div class="text-xs font-semibold uppercase tracking-wider text-[var(--color-muted)]">Total Revenue</div>
          <div class="text-2xl font-bold text-[var(--color-ink)] mt-2">{{ formatCurrency(reports.summary?.total_revenue) }}</div>
          <div class="text-xs text-emerald-600 font-medium mt-1">Confirmed & completed orders</div>
        </div>
        <div class="p-5 rounded-xl border border-[var(--color-line)] bg-white shadow-sm">
          <div class="text-xs font-semibold uppercase tracking-wider text-[var(--color-muted)]">Total Purchases</div>
          <div class="text-2xl font-bold text-[var(--color-ink)] mt-2">{{ reports.summary?.total_purchases || 0 }}</div>
          <div class="text-xs text-[var(--color-muted)] mt-1">Across all statuses</div>
        </div>
        <div class="p-5 rounded-xl border border-[var(--color-line)] bg-white shadow-sm">
          <div class="text-xs font-semibold uppercase tracking-wider text-[var(--color-muted)]">Total Clients</div>
          <div class="text-2xl font-bold text-[var(--color-ink)] mt-2">{{ reports.summary?.total_clients || 0 }}</div>
          <div class="text-xs text-[var(--color-muted)] mt-1">Active customer accounts</div>
        </div>
        <div class="p-5 rounded-xl border border-[var(--color-line)] bg-white shadow-sm">
          <div class="text-xs font-semibold uppercase tracking-wider text-[var(--color-muted)]">Total Catalog Items</div>
          <div class="text-2xl font-bold text-[var(--color-ink)] mt-2">{{ reports.summary?.total_items || 0 }}</div>
          <div class="text-xs text-[var(--color-muted)] mt-1">Products in inventory</div>
        </div>
      </div>

      <!-- Breakdown Grid -->
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Status Distribution -->
        <div class="p-6 rounded-xl border border-[var(--color-line)] bg-white shadow-sm space-y-4">
          <h2 class="text-base font-bold text-[var(--color-ink)]">Purchases by Status</h2>
          <div class="space-y-3">
            <div
              v-for="statusRow in reports.purchases_by_status"
              :key="statusRow.status"
              class="flex items-center justify-between p-3 rounded-lg border border-[var(--color-line)] bg-gray-50/50"
            >
              <div class="flex items-center gap-2">
                <span class="capitalize font-semibold text-sm text-[var(--color-ink)]">{{ statusRow.status }}</span>
                <span class="text-xs bg-gray-200 px-2 py-0.5 rounded text-gray-700 font-bold">{{ statusRow.count }}</span>
              </div>
              <div class="text-sm font-semibold text-[var(--color-ink)]">
                {{ formatCurrency(statusRow.revenue) }}
              </div>
            </div>
          </div>
        </div>

        <!-- Top Clients -->
        <div class="p-6 rounded-xl border border-[var(--color-line)] bg-white shadow-sm space-y-4">
          <h2 class="text-base font-bold text-[var(--color-ink)]">Top Clients by Order Count</h2>
          <div class="space-y-3">
            <div
              v-for="client in reports.top_clients"
              :key="client.id"
              class="flex items-center justify-between p-3 rounded-lg border border-[var(--color-line)] bg-gray-50/50"
            >
              <div>
                <div class="font-semibold text-sm text-[var(--color-ink)]">{{ client.name }}</div>
                <div class="text-xs text-[var(--color-muted)]">{{ client.email }}</div>
              </div>
              <div class="text-xs bg-emerald-100 text-emerald-800 font-bold px-3 py-1 rounded-full">
                {{ client.purchases_count }} orders
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
