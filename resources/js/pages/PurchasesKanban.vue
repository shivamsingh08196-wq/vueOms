<script setup>
import { ref, watch, computed, onMounted } from 'vue';
import { usePurchasesStore } from '../stores/purchases';
import SlideOverDrawer from '../components/SlideOverDrawer.vue';
import PurchaseDetail from '../components/PurchaseDetail.vue';
import PurchaseCreateStepper from '../components/PurchaseCreateStepper.vue';
import { storeToRefs } from 'pinia';
import api from '../api/client';

const store = usePurchasesStore();
const { isDetailDrawerOpen, isCreateStepperOpen, selectedPurchaseId } = storeToRefs(store);

// View Mode: 'kanban' (default) or 'table'
const viewMode = ref('kanban');

// Server-side State
const purchases = ref([]);
const isLoading = ref(true);
const errorMessage = ref('');
const searchQuery = ref('');
const statusFilter = ref('');
const priorityFilter = ref('');
const currentPage = ref(1);
const perPage = ref(10);
const sortColumn = ref('created_at');
const sortDirection = ref('desc');
const totalRecords = ref(0);
const lastPage = ref(1);
const fromRecord = ref(0);
const toRecord = ref(0);

// Status Change Modal State
const isStatusModalOpen = ref(false);
const statusModalPurchase = ref(null);
const newStatusValue = ref('confirmed');
const statusRemarks = ref('');
const isSavingStatus = ref(false);
const statusError = ref('');

// Drag & Drop State
const draggedPurchase = ref(null);
const dragOverColumn = ref(null);

// Map each kanban column id → the status value to assign when dropped there
const columnStatusMap = {
  pending: 'pending',
  in_progress: 'confirmed',
  completed: 'completed',
  cancelled: 'cancelled',
};

const onDragStart = (purchase, event) => {
  draggedPurchase.value = purchase;
  event.dataTransfer.effectAllowed = 'move';
  event.dataTransfer.setData('text/plain', String(purchase.id));
  // Make the dragged element semi-transparent
  event.target.style.opacity = '0.5';
};

const onDragEnd = (event) => {
  event.target.style.opacity = '1';
  draggedPurchase.value = null;
  dragOverColumn.value = null;
};

const onDragOver = (columnId, event) => {
  event.preventDefault();
  event.dataTransfer.dropEffect = 'move';
  dragOverColumn.value = columnId;
};

const onDragLeave = (columnId, event) => {
  // Only clear if we're leaving the column container itself, not entering a child
  if (!event.currentTarget.contains(event.relatedTarget)) {
    if (dragOverColumn.value === columnId) {
      dragOverColumn.value = null;
    }
  }
};

const onDrop = async (columnId, event) => {
  event.preventDefault();
  dragOverColumn.value = null;

  const purchase = draggedPurchase.value;
  if (!purchase) return;

  const newStatus = columnStatusMap[columnId];
  if (!newStatus) return;

  // Don't do anything if dropped on the same column it came from
  const currentStatus = (purchase.status || 'pending').toLowerCase();
  const sourceColumnId = Object.entries(columnStatusMap).find(([, s]) => {
    if (s === currentStatus) return true;
    // Handle statuses that share a column
    if (columnId === 'in_progress' && ['confirmed', 'processing', 'ready'].includes(currentStatus)) return false;
    return false;
  });

  // Check if the purchase is already in the target column
  const targetColumn = columns.value.find(c => c.id === columnId);
  if (targetColumn && targetColumn.purchases.some(p => p.id === purchase.id)) {
    draggedPurchase.value = null;
    return;
  }

  try {
    await api.post(`/api/purchases/${purchase.id}/status`, {
      status: newStatus,
      remarks: `Status changed to ${newStatus} via kanban drag-and-drop`,
    });
    await fetchPurchases();
  } catch (err) {
    errorMessage.value = err.message || `Failed to move purchase to ${columnId}.`;
  } finally {
    draggedPurchase.value = null;
  }
};

let debounceTimer = null;

const fetchPurchases = async () => {
  isLoading.value = true;
  errorMessage.value = '';
  try {
    const params = new URLSearchParams({
      page: String(currentPage.value),
      per_page: String(perPage.value),
      sort: sortColumn.value,
      direction: sortDirection.value,
    });
    if (viewMode.value === 'kanban') {
      params.append('view', 'kanban');
    }
    if (searchQuery.value.trim()) {
      params.append('search', searchQuery.value.trim());
    }
    if (statusFilter.value && statusFilter.value !== 'all') {
      params.append('status', statusFilter.value);
    }
    if (priorityFilter.value && priorityFilter.value !== 'all') {
      params.append('priority', priorityFilter.value);
    }

    const res = await api.get(`/api/purchases?${params.toString()}`);
    if (res && res.data) {
      purchases.value = res.data;
      totalRecords.value = res.total ?? res.meta?.total ?? res.data.length;
      lastPage.value = res.last_page ?? res.meta?.last_page ?? 1;
      fromRecord.value = res.from ?? res.meta?.from ?? (purchases.value.length ? 1 : 0);
      toRecord.value = res.to ?? res.meta?.to ?? purchases.value.length;
    } else {
      purchases.value = Array.isArray(res) ? res : [];
      totalRecords.value = purchases.value.length;
      lastPage.value = 1;
    }
  } catch (err) {
    errorMessage.value = err.message || 'Failed to load purchases.';
  } finally {
    isLoading.value = false;
  }
};

const handleSearchInput = () => {
  clearTimeout(debounceTimer);
  debounceTimer = setTimeout(() => {
    currentPage.value = 1;
    fetchPurchases();
  }, 300);
};

const handleFilterChange = () => {
  currentPage.value = 1;
  fetchPurchases();
};

const toggleSort = (col) => {
  if (sortColumn.value === col) {
    sortDirection.value = sortDirection.value === 'asc' ? 'desc' : 'asc';
  } else {
    sortColumn.value = col;
    sortDirection.value = 'desc';
  }
  currentPage.value = 1;
  fetchPurchases();
};

const goToPage = (p) => {
  if (p < 1 || p > lastPage.value || p === currentPage.value) return;
  currentPage.value = p;
  fetchPurchases();
};

watch(perPage, () => {
  currentPage.value = 1;
  fetchPurchases();
});

watch(viewMode, () => {
  currentPage.value = 1;
  fetchPurchases();
});

// Kanban columns computation
const columns = computed(() => [
  {
    id: 'pending',
    name: 'Pending',
    purchases: purchases.value.filter(p => !p.status || p.status.toLowerCase() === 'pending' || p.status.toLowerCase() === 'draft')
  },
  {
    id: 'in_progress',
    name: 'In Progress / Processing',
    purchases: purchases.value.filter(p => ['confirmed', 'processing', 'ready'].includes(p.status?.toLowerCase()))
  },
  {
    id: 'completed',
    name: 'Completed / Shipped',
    purchases: purchases.value.filter(p => ['completed', 'shipped'].includes(p.status?.toLowerCase()))
  },
  {
    id: 'cancelled',
    name: 'Cancelled',
    purchases: purchases.value.filter(p => p.status?.toLowerCase() === 'cancelled')
  }
]);

const openStatusModal = (purchase, e) => {
  if (e) e.stopPropagation();
  statusModalPurchase.value = purchase;
  newStatusValue.value = purchase.status === 'pending' ? 'confirmed' : 'processing';
  statusRemarks.value = '';
  statusError.value = '';
  isStatusModalOpen.value = true;
};

const closeStatusModal = () => {
  isStatusModalOpen.value = false;
  statusModalPurchase.value = null;
};

const saveStatusChange = async () => {
  if (!statusModalPurchase.value) return;
  isSavingStatus.value = true;
  statusError.value = '';
  try {
    await api.post(`/api/purchases/${statusModalPurchase.value.id}/status`, {
      status: newStatusValue.value,
      remarks: statusRemarks.value ? statusRemarks.value.trim() : null,
    });
    closeStatusModal();
    await fetchPurchases();
  } catch (err) {
    statusError.value = err.message || 'Failed to update status.';
  } finally {
    isSavingStatus.value = false;
  }
};

const handleCreated = () => {
  fetchPurchases();
};

onMounted(() => {
  fetchPurchases();
});
</script>

<template>
  <div class="h-full flex flex-col space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
      <div>
        <h1 class="text-2xl font-bold tracking-tight text-[var(--color-ink)]">Purchases & Orders</h1>
        <p class="text-sm text-[var(--color-muted)] mt-1">Manage, filter, track order lifecycle, and assign purchases.</p>
      </div>
      <div class="flex items-center gap-3">
        <!-- View Mode Switch -->
        <div class="bg-gray-100 p-1 rounded-lg flex items-center border border-[var(--color-line)]">
          <button
            type="button"
            @click="viewMode = 'table'"
            :class="[
              viewMode === 'table' ? 'bg-white shadow-sm text-[var(--color-ink)] font-bold' : 'text-gray-500 hover:text-gray-700',
              'px-3 py-1.5 rounded-md text-xs transition-all flex items-center gap-1.5'
            ]"
          >
            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16" />
            </svg>
            Table View
          </button>
          <button
            type="button"
            @click="viewMode = 'kanban'"
            :class="[
              viewMode === 'kanban' ? 'bg-white shadow-sm text-[var(--color-ink)] font-bold' : 'text-gray-500 hover:text-gray-700',
              'px-3 py-1.5 rounded-md text-xs transition-all flex items-center gap-1.5'
            ]"
          >
            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17V7m0 10a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2h2a2 2 0 012 2m0 10a2 2 0 002 2h2a2 2 0 002-2M9 7a2 2 0 012-2h2a2 2 0 012 2m0 10V7m0 10a2 2 0 002 2h2a2 2 0 002-2V7a2 2 0 00-2-2h-2a2 2 0 00-2 2" />
            </svg>
            Kanban Board
          </button>
        </div>

        <button
          type="button"
          @click="store.openCreateStepper"
          data-testid="create-purchase-btn"
          class="inline-flex items-center gap-2 rounded-lg bg-[var(--color-evergreen)] px-4 py-2 text-sm font-semibold text-white shadow-sm hover:opacity-95 transition-opacity"
        >
          <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
          </svg>
          Create Purchase
        </button>
      </div>
    </div>

    <!-- Controls: Search, Filters, Limit -->
    <div class="flex flex-col lg:flex-row gap-3 items-stretch lg:items-center justify-between">
      <div class="flex flex-col sm:flex-row gap-3 flex-1">
        <!-- Search -->
        <div class="relative flex-1">
          <input
            v-model="searchQuery"
            @input="handleSearchInput"
            type="text"
            placeholder="Search by purchase #, client name, email..."
            class="w-full rounded-lg border border-[var(--color-line)] bg-white px-4 py-2 text-sm text-[var(--color-ink)] placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-[var(--color-evergreen)]"
          />
          <div class="absolute inset-y-0 right-0 flex items-center pr-3 pointer-events-none text-gray-400">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
            </svg>
          </div>
        </div>

        <!-- Status Filter (Table Mode) -->
        <div v-if="viewMode === 'table'" class="w-full sm:w-44">
          <select
            v-model="statusFilter"
            @change="handleFilterChange"
            class="w-full rounded-lg border border-[var(--color-line)] bg-white px-3 py-2 text-sm text-[var(--color-ink)] focus:outline-none focus:ring-2 focus:ring-[var(--color-evergreen)]"
          >
            <option value="">All Statuses</option>
            <option value="pending">Status: Pending</option>
            <option value="confirmed">Status: Confirmed</option>
            <option value="processing">Status: Processing</option>
            <option value="ready">Status: Ready</option>
            <option value="completed">Status: Completed</option>
            <option value="cancelled">Status: Cancelled</option>
          </select>
        </div>

        <!-- Priority Filter -->
        <div class="w-full sm:w-36">
          <select
            v-model="priorityFilter"
            @change="handleFilterChange"
            class="w-full rounded-lg border border-[var(--color-line)] bg-white px-3 py-2 text-sm text-[var(--color-ink)] focus:outline-none focus:ring-2 focus:ring-[var(--color-evergreen)]"
          >
            <option value="">All Priorities</option>
            <option value="normal">Normal</option>
            <option value="high">High</option>
            <option value="urgent">Urgent</option>
            <option value="low">Low</option>
          </select>
        </div>
      </div>

      <!-- Limit (Table Mode) -->
      <div v-if="viewMode === 'table'" class="flex items-center gap-3 justify-end">
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

    <!-- Loading State -->
    <div v-if="isLoading" class="p-12 text-center text-sm text-[var(--color-muted)] bg-white rounded-xl border border-[var(--color-line)]">
      <div class="animate-spin inline-block w-6 h-6 border-[3px] border-current border-t-transparent text-[var(--color-evergreen)] rounded-full mb-2"></div>
      <div>Loading purchases from server...</div>
    </div>

    <!-- Error State -->
    <div v-else-if="errorMessage" class="p-8 text-center text-sm text-red-600 bg-white rounded-xl border border-red-200">
      <p>{{ errorMessage }}</p>
      <button @click="fetchPurchases" class="mt-2 text-sm font-medium underline text-[var(--color-evergreen)]">Retry</button>
    </div>

    <!-- TABLE VIEW -->
    <div v-else-if="viewMode === 'table'" class="rounded-xl border border-[var(--color-line)] bg-white overflow-hidden shadow-sm">
      <div v-if="purchases.length === 0" class="p-12 text-center text-sm text-[var(--color-muted)]">
        No purchases found matching your criteria.
      </div>

      <div v-else class="overflow-x-auto">
        <table class="w-full text-left text-sm" data-testid="purchases-table">
          <thead class="bg-gray-50 border-b border-[var(--color-line)] text-xs uppercase text-[var(--color-muted)]">
            <tr>
              <th @click="toggleSort('purchase_number')" class="px-6 py-3 font-semibold cursor-pointer hover:text-[var(--color-ink)] select-none">
                <div class="flex items-center gap-1.5">
                  <span>Purchase #</span>
                  <span v-if="sortColumn === 'purchase_number'">{{ sortDirection === 'asc' ? '↑' : '↓' }}</span>
                </div>
              </th>
              <th class="px-6 py-3 font-semibold">Client</th>
              <th @click="toggleSort('purchase_date')" class="px-6 py-3 font-semibold cursor-pointer hover:text-[var(--color-ink)] select-none">
                <div class="flex items-center gap-1.5">
                  <span>Date</span>
                  <span v-if="sortColumn === 'purchase_date'">{{ sortDirection === 'asc' ? '↑' : '↓' }}</span>
                </div>
              </th>
              <th @click="toggleSort('status')" class="px-6 py-3 font-semibold cursor-pointer hover:text-[var(--color-ink)] select-none">
                <div class="flex items-center gap-1.5">
                  <span>Status</span>
                  <span v-if="sortColumn === 'status'">{{ sortDirection === 'asc' ? '↑' : '↓' }}</span>
                </div>
              </th>
              <th @click="toggleSort('priority')" class="px-6 py-3 font-semibold cursor-pointer hover:text-[var(--color-ink)] select-none">
                <div class="flex items-center gap-1.5">
                  <span>Priority</span>
                  <span v-if="sortColumn === 'priority'">{{ sortDirection === 'asc' ? '↑' : '↓' }}</span>
                </div>
              </th>
              <th @click="toggleSort('total_amount')" class="px-6 py-3 font-semibold cursor-pointer hover:text-[var(--color-ink)] select-none">
                <div class="flex items-center gap-1.5">
                  <span>Total</span>
                  <span v-if="sortColumn === 'total_amount'">{{ sortDirection === 'asc' ? '↑' : '↓' }}</span>
                </div>
              </th>
              <th @click="toggleSort('created_at')" class="px-6 py-3 font-semibold cursor-pointer hover:text-[var(--color-ink)] select-none">
                <div class="flex items-center gap-1.5">
                  <span>Created</span>
                  <span v-if="sortColumn === 'created_at'">{{ sortDirection === 'asc' ? '↑' : '↓' }}</span>
                </div>
              </th>
              <th class="px-6 py-3 font-semibold text-right">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-[var(--color-line)]">
            <tr
              v-for="p in purchases"
              :key="p.id"
              @click="store.openPurchaseDetail(p.id)"
              class="hover:bg-gray-50 transition-colors cursor-pointer"
            >
              <td class="px-6 py-4 font-mono font-bold text-[var(--color-evergreen)]">
                #{{ p.purchase_number || p.id }}
              </td>
              <td class="px-6 py-4">
                <div class="font-medium text-[var(--color-ink)]">{{ p.client?.name || 'Client #' + p.client_id }}</div>
                <div v-if="p.client?.email" class="text-xs text-[var(--color-muted)]">{{ p.client.email }}</div>
                <div class="text-[11px] text-gray-500 flex items-center gap-1.5 mt-1">
                  <span class="inline-block w-1.5 h-1.5 rounded-full" :class="p.assignee ? 'bg-[var(--color-evergreen)]' : 'bg-gray-300'"></span>
                  <span>{{ p.assignee?.name ? 'Assigned: ' + p.assignee.name : 'Unassigned' }}</span>
                </div>
              </td>
              <td class="px-6 py-4 text-xs font-mono text-[var(--color-muted)]">
                {{ p.purchase_date }}
              </td>
              <td class="px-6 py-4">
                <span
                  :class="[
                    p.status === 'confirmed' ? 'bg-blue-100 text-blue-800' :
                    p.status === 'completed' ? 'bg-emerald-100 text-emerald-800' :
                    p.status === 'cancelled' ? 'bg-rose-100 text-rose-800' :
                    'bg-amber-100 text-amber-800',
                    'inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold uppercase'
                  ]"
                >
                  {{ p.status }}
                </span>
              </td>
              <td class="px-6 py-4">
                <span
                  :class="[
                    p.priority === 'urgent' ? 'text-red-700 bg-red-50' :
                    p.priority === 'high' ? 'text-amber-700 bg-amber-50' :
                    'text-gray-700 bg-gray-50',
                    'px-2 py-0.5 rounded text-[11px] font-semibold uppercase'
                  ]"
                >
                  {{ p.priority || 'normal' }}
                </span>
              </td>
              <td class="px-6 py-4 font-bold text-[var(--color-ink)]">
                ${{ Number(p.total_amount || 0).toFixed(2) }}
              </td>
              <td class="px-6 py-4 text-xs font-mono text-[var(--color-muted)]">
                {{ p.created_at ? new Date(p.created_at).toLocaleDateString() : '—' }}
              </td>
              <td class="px-6 py-4 text-right space-x-2 whitespace-nowrap" @click.stop>
                <button
                  @click="openStatusModal(p, $event)"
                  class="text-xs font-semibold px-2 py-1 rounded bg-gray-100 hover:bg-gray-200 text-gray-700"
                >
                  Status
                </button>
                <button
                  @click="store.openPurchaseDetail(p.id)"
                  class="text-xs font-medium text-[var(--color-evergreen)] hover:underline"
                >
                  View
                </button>
              </td>
            </tr>
          </tbody>
        </table>

        <!-- Server Pagination Bar -->
        <div class="px-6 py-3.5 bg-gray-50 border-t border-[var(--color-line)] flex flex-col sm:flex-row items-center justify-between gap-3 text-xs text-[var(--color-muted)]">
          <div>
            Showing <span class="font-semibold text-gray-800">{{ fromRecord }}</span> to <span class="font-semibold text-gray-800">{{ toRecord }}</span> of <span class="font-semibold text-gray-800">{{ totalRecords }}</span> purchases
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

    <!-- KANBAN BOARD VIEW -->
    <div v-else class="flex-1 overflow-x-auto">
      <div class="inline-flex h-full items-start space-x-4 pb-4">
        <div
          v-for="col in columns"
          :key="col.id"
          @dragover="onDragOver(col.id, $event)"
          @dragleave="onDragLeave(col.id, $event)"
          @drop="onDrop(col.id, $event)"
          :class="[
            'w-80 flex-shrink-0 rounded-xl p-4 border min-h-[500px] transition-all duration-200',
            dragOverColumn === col.id
              ? 'bg-emerald-50 border-[var(--color-evergreen)] border-2 ring-2 ring-[var(--color-evergreen)]/20'
              : 'bg-gray-50 border-[var(--color-line)]'
          ]"
        >
          <div class="flex items-center justify-between mb-3">
            <h3 class="text-sm font-bold text-[var(--color-ink)]">{{ col.name }}</h3>
            <span class="text-xs bg-gray-200 text-gray-700 font-bold px-2 py-0.5 rounded-full">{{ col.purchases.length }}</span>
          </div>

          <div v-if="col.purchases.length === 0" class="p-6 text-center text-xs text-gray-400 border border-dashed border-gray-200 rounded-lg">
            <template v-if="dragOverColumn === col.id">
              <span class="text-[var(--color-evergreen)] font-semibold">Drop here to move</span>
            </template>
            <template v-else>
              No purchases in this stage
            </template>
          </div>

          <div v-else class="space-y-3">
            <div
              v-for="p in col.purchases"
              :key="p.id"
              draggable="true"
              @dragstart="onDragStart(p, $event)"
              @dragend="onDragEnd($event)"
              @click="store.openPurchaseDetail(p.id)"
              class="bg-white p-4 rounded-xl shadow-sm border border-[var(--color-line)] cursor-grab hover:border-[var(--color-evergreen)] hover:shadow-md transition-all active:cursor-grabbing"
            >
              <div class="flex justify-between items-start mb-2">
                <span class="font-bold text-[var(--color-evergreen)] font-mono text-sm">#{{ p.purchase_number || p.id }}</span>
                <span class="text-sm font-bold text-[var(--color-ink)]">${{ Number(p.total_amount || 0).toFixed(2) }}</span>
              </div>
              <p class="text-sm font-medium text-[var(--color-ink)]">{{ p.client?.name || `Client #${p.client_id || 'N/A'}` }}</p>
              <div class="mt-1.5 text-[11px] text-gray-500 flex items-center gap-1.5">
                <span class="inline-block w-1.5 h-1.5 rounded-full" :class="p.assignee ? 'bg-[var(--color-evergreen)]' : 'bg-gray-300'"></span>
                <span>{{ p.assignee?.name ? 'Assigned: ' + p.assignee.name : 'Unassigned' }}</span>
              </div>
              <div class="mt-3 flex items-center justify-between text-xs text-gray-400">
                <span>{{ p.purchase_date }}</span>
                <span class="uppercase font-semibold text-[10px] px-2 py-0.5 rounded bg-gray-100 text-gray-700">{{ p.priority || 'normal' }}</span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Purchase Status Change Modal -->
    <div v-if="isStatusModalOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40">
      <div class="bg-white rounded-xl shadow-xl max-w-md w-full p-6 space-y-4 border border-[var(--color-line)]">
        <h3 class="text-base font-bold text-[var(--color-ink)]">
          Update Status for #{{ statusModalPurchase?.purchase_number }}
        </h3>

        <div v-if="statusError" class="p-2.5 bg-red-50 text-xs text-red-600 rounded-lg border border-red-200">
          {{ statusError }}
        </div>

        <div>
          <label class="block text-xs font-semibold uppercase text-gray-500 mb-1">New Status</label>
          <select
            v-model="newStatusValue"
            class="w-full rounded-lg border border-[var(--color-line)] px-3 py-2 text-sm text-[var(--color-ink)]"
          >
            <option value="pending">Pending</option>
            <option value="confirmed">Confirmed (Reserves Stock)</option>
            <option value="processing">Processing</option>
            <option value="ready">Ready</option>
            <option value="completed">Completed</option>
            <option value="cancelled">Cancelled (Restores Stock)</option>
          </select>
        </div>

        <div>
          <label class="block text-xs font-semibold uppercase text-gray-500 mb-1">Remarks / Note</label>
          <textarea
            v-model="statusRemarks"
            rows="2"
            placeholder="Reason or operational remark..."
            class="w-full rounded-lg border border-[var(--color-line)] px-3 py-2 text-sm text-[var(--color-ink)]"
          ></textarea>
        </div>

        <div class="flex justify-end gap-3 pt-2">
          <button
            type="button"
            @click="closeStatusModal"
            class="px-4 py-2 text-xs font-semibold rounded-lg border border-gray-200 hover:bg-gray-50"
          >
            Cancel
          </button>
          <button
            type="button"
            :disabled="isSavingStatus"
            @click="saveStatusChange"
            class="px-4 py-2 text-xs font-semibold rounded-lg bg-[var(--color-evergreen)] text-white hover:opacity-90 disabled:opacity-50"
          >
            {{ isSavingStatus ? 'Updating...' : 'Save Status' }}
          </button>
        </div>
      </div>
    </div>

    <!-- Modals / Drawers -->
    <SlideOverDrawer v-model="isDetailDrawerOpen" title="Purchase Details">
      <PurchaseDetail v-if="selectedPurchaseId" :purchaseId="selectedPurchaseId" @updated="fetchPurchases" />
    </SlideOverDrawer>

    <PurchaseCreateStepper v-if="isCreateStepperOpen" @close="store.closeCreateStepper" @created="handleCreated" />
  </div>
</template>
