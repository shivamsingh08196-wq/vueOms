<script setup>
import { ref, watch, onMounted } from 'vue';
import api from '../api/client';

// Server-side State
const logs = ref([]);
const isLoading = ref(true);
const errorMessage = ref('');
const searchQuery = ref('');
const actionFilter = ref('');
const currentPage = ref(1);
const perPage = ref(10);
const sortColumn = ref('created_at');
const sortDirection = ref('desc');
const totalRecords = ref(0);
const lastPage = ref(1);
const fromRecord = ref(0);
const toRecord = ref(0);

let debounceTimer = null;

const fetchLogs = async () => {
  isLoading.value = true;
  errorMessage.value = '';
  try {
    const params = new URLSearchParams({
      page: String(currentPage.value),
      per_page: String(perPage.value),
      sort: sortColumn.value,
      direction: sortDirection.value,
    });
    if (searchQuery.value.trim()) {
      params.append('search', searchQuery.value.trim());
    }
    if (actionFilter.value) {
      params.append('action', actionFilter.value);
    }

    const res = await api.get(`/api/audit-logs?${params.toString()}`);
    if (res && res.data) {
      logs.value = res.data;
      totalRecords.value = res.total ?? res.meta?.total ?? res.data.length;
      lastPage.value = res.last_page ?? res.meta?.last_page ?? 1;
      fromRecord.value = res.from ?? res.meta?.from ?? (logs.value.length ? 1 : 0);
      toRecord.value = res.to ?? res.meta?.to ?? logs.value.length;
    } else {
      logs.value = Array.isArray(res) ? res : [];
      totalRecords.value = logs.value.length;
      lastPage.value = 1;
    }
  } catch (err) {
    errorMessage.value = err.message || 'Failed to load audit logs.';
  } finally {
    isLoading.value = false;
  }
};

const handleSearchInput = () => {
  clearTimeout(debounceTimer);
  debounceTimer = setTimeout(() => {
    currentPage.value = 1;
    fetchLogs();
  }, 300);
};

const handleFilterChange = () => {
  currentPage.value = 1;
  fetchLogs();
};

const toggleSort = (col) => {
  if (sortColumn.value === col) {
    sortDirection.value = sortDirection.value === 'asc' ? 'desc' : 'asc';
  } else {
    sortColumn.value = col;
    sortDirection.value = 'desc';
  }
  currentPage.value = 1;
  fetchLogs();
};

const goToPage = (p) => {
  if (p < 1 || p > lastPage.value || p === currentPage.value) return;
  currentPage.value = p;
  fetchLogs();
};

watch(perPage, () => {
  currentPage.value = 1;
  fetchLogs();
});

const formatActionBadge = (action) => {
  if (!action) return 'bg-gray-100 text-gray-800';
  if (action.includes('create')) return 'bg-emerald-100 text-emerald-800';
  if (action.includes('update') || action.includes('edit')) return 'bg-blue-100 text-blue-800';
  if (action.includes('delete')) return 'bg-rose-100 text-rose-800';
  if (action.includes('status')) return 'bg-amber-100 text-amber-800';
  if (action.includes('assign')) return 'bg-purple-100 text-purple-800';
  return 'bg-gray-100 text-gray-800';
};

const formatChanges = (changes) => {
  if (!changes) return null;
  if (typeof changes === 'string') {
    try {
      return JSON.parse(changes);
    } catch {
      return changes;
    }
  }
  return changes;
};

onMounted(() => {
  fetchLogs();
});
</script>

<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
      <div>
        <h1 class="text-2xl font-bold tracking-tight text-[var(--color-ink)]">System Audit Logs</h1>
        <p class="text-sm text-[var(--color-muted)] mt-1">Immutable record of data mutations, lifecycle transitions, and administrative operations.</p>
      </div>
      <button
        @click="fetchLogs"
        class="inline-flex items-center gap-2 rounded-lg border border-[var(--color-line)] bg-white px-3 py-2 text-xs font-semibold text-[var(--color-ink)] shadow-sm hover:bg-gray-50"
      >
        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
        </svg>
        Refresh Logs
      </button>
    </div>

    <!-- Controls: Search, Filter, Limit -->
    <div class="flex flex-col sm:flex-row gap-3 items-center justify-between">
      <div class="flex flex-col sm:flex-row gap-3 w-full sm:max-w-xl">
        <div class="relative flex-1">
          <input
            v-model="searchQuery"
            @input="handleSearchInput"
            type="text"
            placeholder="Search by action, user, or record ID..."
            class="w-full rounded-lg border border-[var(--color-line)] bg-white px-4 py-2 text-sm text-[var(--color-ink)] placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-[var(--color-evergreen)]"
          />
        </div>
        <div class="w-full sm:w-56">
          <select
            v-model="actionFilter"
            @change="handleFilterChange"
            class="w-full rounded-lg border border-[var(--color-line)] bg-white px-3 py-2 text-sm text-[var(--color-ink)] focus:outline-none focus:ring-2 focus:ring-[var(--color-evergreen)]"
          >
            <option value="">All Actions</option>
            <option value="status_changed">Status Changed</option>
            <option value="assigned">Assigned</option>
            <option value="created">Created</option>
            <option value="updated">Updated</option>
            <option value="deleted">Deleted</option>
          </select>
        </div>
      </div>

      <div class="flex items-center gap-3 w-full sm:w-auto justify-end">
        <label class="text-xs text-[var(--color-muted)] font-medium">Rows per page:</label>
        <select
          v-model.number="perPage"
          class="rounded-lg border border-[var(--color-line)] bg-white px-3 py-1.5 text-xs text-[var(--color-ink)] focus:outline-none focus:ring-2 focus:ring-[var(--color-evergreen)]"
        >
          <option :value="10">10</option>
          <option :value="25">25</option>
          <option :value="50">50</option>
        </select>
      </div>
    </div>

    <!-- Logs Table -->
    <div class="rounded-xl border border-[var(--color-line)] bg-white overflow-hidden shadow-sm">
      <div v-if="isLoading" class="p-12 text-center text-sm text-[var(--color-muted)]">
        <div class="animate-spin inline-block w-6 h-6 border-[3px] border-current border-t-transparent text-[var(--color-evergreen)] rounded-full mb-2"></div>
        <div>Loading audit trail from server...</div>
      </div>

      <div v-else-if="errorMessage" class="p-8 text-center text-sm text-red-600">
        <p>{{ errorMessage }}</p>
        <button @click="fetchLogs" class="mt-2 text-sm font-medium underline text-[var(--color-evergreen)]">Retry</button>
      </div>

      <div v-else-if="logs.length === 0" class="p-12 text-center text-sm text-[var(--color-muted)]">
        No audit log records found.
      </div>

      <div v-else class="overflow-x-auto">
        <table class="w-full text-left text-sm" data-testid="audit-table">
          <thead class="bg-gray-50 border-b border-[var(--color-line)] text-xs uppercase text-[var(--color-muted)]">
            <tr>
              <th @click="toggleSort('created_at')" class="px-6 py-3 font-semibold cursor-pointer hover:text-[var(--color-ink)] select-none">
                <div class="flex items-center gap-1.5">
                  <span>Timestamp</span>
                  <span v-if="sortColumn === 'created_at'">{{ sortDirection === 'asc' ? '↑' : '↓' }}</span>
                </div>
              </th>
              <th class="px-6 py-3 font-semibold">Operator</th>
              <th @click="toggleSort('action')" class="px-6 py-3 font-semibold cursor-pointer hover:text-[var(--color-ink)] select-none">
                <div class="flex items-center gap-1.5">
                  <span>Action</span>
                  <span v-if="sortColumn === 'action'">{{ sortDirection === 'asc' ? '↑' : '↓' }}</span>
                </div>
              </th>
              <th class="px-6 py-3 font-semibold">Target Model</th>
              <th class="px-6 py-3 font-semibold">Mutation Details</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-[var(--color-line)]">
            <tr v-for="log in logs" :key="log.id" class="hover:bg-gray-50 transition-colors">
              <td class="px-6 py-4 text-xs font-mono text-[var(--color-muted)] whitespace-nowrap">
                {{ new Date(log.created_at).toLocaleString() }}
              </td>
              <td class="px-6 py-4 font-medium text-[var(--color-ink)] whitespace-nowrap">
                <div v-if="log.user" class="flex items-center gap-2">
                  <span class="inline-block h-6 w-6 rounded-full bg-gray-200 text-center text-xs font-bold leading-6 text-gray-700">
                    {{ log.user.name.charAt(0) }}
                  </span>
                  <span>{{ log.user.name }}</span>
                </div>
                <span v-else class="text-xs text-gray-400">System</span>
              </td>
              <td class="px-6 py-4 whitespace-nowrap">
                <span :class="['px-2.5 py-1 rounded-full text-xs font-semibold uppercase font-mono', formatActionBadge(log.action)]">
                  {{ log.action }}
                </span>
              </td>
              <td class="px-6 py-4 text-xs text-[var(--color-muted)] font-mono whitespace-nowrap">
                {{ log.model_type?.split('\\').pop() }} #{{ log.model_id }}
              </td>
              <td class="px-6 py-4 text-xs">
                <div v-if="formatChanges(log.changes)" class="bg-gray-50 p-2 rounded border border-gray-100 font-mono text-gray-700 max-w-md overflow-x-auto">
                  <div v-for="(val, key) in formatChanges(log.changes)" :key="key" class="truncate">
                    <span class="font-semibold text-gray-900">{{ key }}:</span>
                    <span class="ml-1 text-gray-600">{{ typeof val === 'object' ? JSON.stringify(val) : val }}</span>
                  </div>
                </div>
                <span v-else class="text-gray-400 italic">No change metadata</span>
              </td>
            </tr>
          </tbody>
        </table>

        <!-- Server Pagination Bar -->
        <div class="px-6 py-3.5 bg-gray-50 border-t border-[var(--color-line)] flex flex-col sm:flex-row items-center justify-between gap-3 text-xs text-[var(--color-muted)]">
          <div>
            Showing <span class="font-semibold text-gray-800">{{ fromRecord }}</span> to <span class="font-semibold text-gray-800">{{ toRecord }}</span> of <span class="font-semibold text-gray-800">{{ totalRecords }}</span> records
          </div>
          <div class="flex items-center gap-1">
            <button
              @click="goToPage(currentPage - 1)"
              :disabled="currentPage <= 1"
              class="px-3 py-1.5 rounded border border-[var(--color-line)] bg-white hover:bg-gray-50 disabled:opacity-40 disabled:cursor-not-allowed font-medium"
            >
              Previous
            </button>
            <div class="px-2 font-semibold text-gray-700">
              Page {{ currentPage }} of {{ lastPage }}
            </div>
            <button
              @click="goToPage(currentPage + 1)"
              :disabled="currentPage >= lastPage"
              class="px-3 py-1.5 rounded border border-[var(--color-line)] bg-white hover:bg-gray-50 disabled:opacity-40 disabled:cursor-not-allowed font-medium"
            >
              Next
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
