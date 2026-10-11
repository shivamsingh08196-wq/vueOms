<script setup>
import { ref, computed, onMounted } from 'vue';
import api from '../api/client';

const logs = ref([]);
const isLoading = ref(true);
const errorMessage = ref('');
const actionFilter = ref('');
const searchQuery = ref('');

const fetchLogs = async () => {
  isLoading.value = true;
  errorMessage.value = '';
  try {
    const params = new URLSearchParams();
    if (actionFilter.value) params.append('action', actionFilter.value);
    const url = `/api/audit-logs${params.toString() ? '?' + params.toString() : ''}`;
    const data = await api.get(url);
    logs.value = Array.isArray(data) ? data : (data.data || []);
  } catch (err) {
    errorMessage.value = err.message || 'Failed to load audit logs.';
  } finally {
    isLoading.value = false;
  }
};

const filteredLogs = computed(() => {
  if (!searchQuery.value.trim()) return logs.value;
  const q = searchQuery.value.toLowerCase();
  return logs.value.filter(log =>
    log.action?.toLowerCase().includes(q) ||
    log.user?.name?.toLowerCase().includes(q) ||
    log.user?.email?.toLowerCase().includes(q) ||
    String(log.model_id).includes(q)
  );
});

const formatActionBadge = (action) => {
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

    <!-- Filters -->
    <div class="flex flex-col sm:flex-row gap-3">
      <div class="relative flex-1">
        <input
          v-model="searchQuery"
          type="text"
          placeholder="Search by action, user, or record ID..."
          class="w-full rounded-lg border border-[var(--color-line)] bg-white px-4 py-2 text-sm text-[var(--color-ink)] placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-[var(--color-evergreen)]"
        />
      </div>
      <div class="w-full sm:w-64">
        <select
          v-model="actionFilter"
          @change="fetchLogs"
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

    <!-- Logs Table -->
    <div class="rounded-xl border border-[var(--color-line)] bg-white overflow-hidden shadow-sm">
      <div v-if="isLoading" class="p-8 text-center text-sm text-[var(--color-muted)]">
        <div class="animate-spin inline-block w-6 h-6 border-[3px] border-current border-t-transparent text-[var(--color-evergreen)] rounded-full mb-2"></div>
        <div>Loading audit trail...</div>
      </div>

      <div v-else-if="errorMessage" class="p-8 text-center text-sm text-red-600">
        <p>{{ errorMessage }}</p>
        <button @click="fetchLogs" class="mt-2 text-sm font-medium underline text-[var(--color-evergreen)]">Retry</button>
      </div>

      <div v-else-if="filteredLogs.length === 0" class="p-8 text-center text-sm text-[var(--color-muted)]">
        No audit log records found.
      </div>

      <div v-else class="overflow-x-auto">
        <table class="w-full text-left text-sm" data-testid="audit-table">
          <thead class="bg-gray-50 border-b border-[var(--color-line)] text-xs uppercase text-[var(--color-muted)]">
            <tr>
              <th class="px-6 py-3 font-semibold">Timestamp</th>
              <th class="px-6 py-3 font-semibold">Operator</th>
              <th class="px-6 py-3 font-semibold">Action</th>
              <th class="px-6 py-3 font-semibold">Target Model</th>
              <th class="px-6 py-3 font-semibold">Mutation Details</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-[var(--color-line)]">
            <tr v-for="log in filteredLogs" :key="log.id" class="hover:bg-gray-50 transition-colors">
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
      </div>
    </div>
  </div>
</template>
