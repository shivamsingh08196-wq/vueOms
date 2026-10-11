<script setup>
import { ref, watch, onMounted } from 'vue';
import api from '../api/client';
import SlideOverDrawer from '../components/SlideOverDrawer.vue';

// Server-side State
const items = ref([]);
const isLoading = ref(true);
const errorMessage = ref('');
const searchQuery = ref('');
const stockFilter = ref('');
const currentPage = ref(1);
const perPage = ref(10);
const sortColumn = ref('created_at');
const sortDirection = ref('desc');
const totalRecords = ref(0);
const lastPage = ref(1);
const fromRecord = ref(0);
const toRecord = ref(0);

// Drawer / Form state
const isDrawerOpen = ref(false);
const editingItem = ref(null);
const isSaving = ref(false);
const formError = ref('');
const form = ref({
  name: '',
  sku: '',
  description: '',
  price: '',
  stock: '',
});

let debounceTimer = null;

const fetchItems = async () => {
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
    if (stockFilter.value) {
      params.append('stock_status', stockFilter.value);
    }

    const res = await api.get(`/api/items?${params.toString()}`);
    if (res && res.data) {
      items.value = res.data;
      totalRecords.value = res.total ?? res.meta?.total ?? res.data.length;
      lastPage.value = res.last_page ?? res.meta?.last_page ?? 1;
      fromRecord.value = res.from ?? res.meta?.from ?? (items.value.length ? 1 : 0);
      toRecord.value = res.to ?? res.meta?.to ?? items.value.length;
    } else {
      items.value = Array.isArray(res) ? res : [];
      totalRecords.value = items.value.length;
      lastPage.value = 1;
    }
  } catch (err) {
    errorMessage.value = err.message || 'Failed to load items.';
  } finally {
    isLoading.value = false;
  }
};

const handleSearchInput = () => {
  clearTimeout(debounceTimer);
  debounceTimer = setTimeout(() => {
    currentPage.value = 1;
    fetchItems();
  }, 300);
};

const handleFilterChange = () => {
  currentPage.value = 1;
  fetchItems();
};

const toggleSort = (col) => {
  if (sortColumn.value === col) {
    sortDirection.value = sortDirection.value === 'asc' ? 'desc' : 'asc';
  } else {
    sortColumn.value = col;
    sortDirection.value = 'desc';
  }
  currentPage.value = 1;
  fetchItems();
};

const goToPage = (p) => {
  if (p < 1 || p > lastPage.value || p === currentPage.value) return;
  currentPage.value = p;
  fetchItems();
};

watch(perPage, () => {
  currentPage.value = 1;
  fetchItems();
});

const openCreateDrawer = () => {
  editingItem.value = null;
  formError.value = '';
  form.value = {
    name: '',
    sku: '',
    description: '',
    price: '',
    stock: '',
  };
  isDrawerOpen.value = true;
};

const openEditDrawer = (item) => {
  editingItem.value = item;
  formError.value = '';
  form.value = {
    name: item.name,
    sku: item.sku || '',
    description: item.description || '',
    price: item.price,
    stock: item.stock,
  };
  isDrawerOpen.value = true;
};

const closeDrawer = () => {
  isDrawerOpen.value = false;
  editingItem.value = null;
};

const saveItem = async () => {
  formError.value = '';

  if (!form.value.name.trim()) {
    formError.value = 'Item name is required.';
    return;
  }
  if (form.value.price === '' || isNaN(form.value.price) || Number(form.value.price) < 0) {
    formError.value = 'Valid price is required.';
    return;
  }
  if (form.value.stock === '' || isNaN(form.value.stock) || Number(form.value.stock) < 0) {
    formError.value = 'Valid stock quantity is required.';
    return;
  }

  isSaving.value = true;
  try {
    const payload = {
      name: form.value.name.trim(),
      sku: form.value.sku ? form.value.sku.trim() : null,
      description: form.value.description ? form.value.description.trim() : null,
      price: Number(form.value.price),
      stock: parseInt(form.value.stock, 10),
    };

    if (editingItem.value) {
      await api.put(`/api/items/${editingItem.value.id}`, payload);
    } else {
      await api.post('/api/items', payload);
    }

    await fetchItems();
    closeDrawer();
  } catch (err) {
    formError.value = err.message || 'Failed to save item.';
  } finally {
    isSaving.value = false;
  }
};

const deleteItem = async (item) => {
  if (!confirm(`Are you sure you want to delete item "${item.name}"?`)) {
    return;
  }

  try {
    await api.delete(`/api/items/${item.id}`);
    await fetchItems();
  } catch (err) {
    alert(err.message || 'Failed to delete item.');
  }
};

onMounted(() => {
  fetchItems();
});
</script>

<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
      <div>
        <h1 class="text-2xl font-bold tracking-tight text-[var(--color-ink)]">Items Catalog</h1>
        <p class="text-sm text-[var(--color-muted)] mt-1">Manage product catalog, pricing, and live inventory stock levels.</p>
      </div>
      <div>
        <button
          @click="openCreateDrawer"
          class="inline-flex items-center gap-2 rounded-lg bg-[var(--color-evergreen)] px-4 py-2.5 text-sm font-medium text-white shadow-sm hover:opacity-95 transition-opacity"
        >
          <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
          </svg>
          Add Item
        </button>
      </div>
    </div>

    <!-- Controls: Search, Stock Filter, Limit Selector -->
    <div class="flex flex-col sm:flex-row gap-3 items-center justify-between">
      <div class="flex flex-col sm:flex-row gap-3 w-full sm:max-w-xl">
        <div class="relative flex-1">
          <input
            v-model="searchQuery"
            @input="handleSearchInput"
            type="text"
            placeholder="Search by name, SKU, description..."
            class="w-full rounded-lg border border-[var(--color-line)] bg-white px-4 py-2 text-sm text-[var(--color-ink)] placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-[var(--color-evergreen)]"
          />
          <div class="absolute inset-y-0 right-0 flex items-center pr-3 pointer-events-none text-gray-400">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
            </svg>
          </div>
        </div>

        <div class="w-full sm:w-44">
          <select
            v-model="stockFilter"
            @change="handleFilterChange"
            class="w-full rounded-lg border border-[var(--color-line)] bg-white px-3 py-2 text-sm text-[var(--color-ink)] focus:outline-none focus:ring-2 focus:ring-[var(--color-evergreen)]"
          >
            <option value="">All Stock Levels</option>
            <option value="in_stock">In Stock (&gt; 5)</option>
            <option value="low_stock">Low Stock (1 - 5)</option>
            <option value="out_of_stock">Out of Stock (0)</option>
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

    <!-- Data Table -->
    <div class="rounded-xl border border-[var(--color-line)] bg-white overflow-hidden shadow-sm">
      <div v-if="isLoading" class="p-12 text-center text-sm text-[var(--color-muted)]">
        <div class="animate-spin inline-block w-6 h-6 border-[3px] border-current border-t-transparent text-[var(--color-evergreen)] rounded-full mb-2"></div>
        <div>Loading catalog from server...</div>
      </div>

      <div v-else-if="errorMessage" class="p-8 text-center text-sm text-red-600">
        <p>{{ errorMessage }}</p>
        <button @click="fetchItems" class="mt-2 text-sm font-medium underline text-[var(--color-evergreen)]">Retry</button>
      </div>

      <div v-else-if="items.length === 0" class="p-12 text-center text-sm text-[var(--color-muted)]">
        No catalog items match your search or filter.
      </div>

      <div v-else class="overflow-x-auto">
        <table class="w-full text-left text-sm">
          <thead class="bg-gray-50 border-b border-[var(--color-line)] text-xs uppercase text-[var(--color-muted)]">
            <tr>
              <th @click="toggleSort('name')" class="px-6 py-3 font-semibold cursor-pointer hover:text-[var(--color-ink)] select-none">
                <div class="flex items-center gap-1.5">
                  <span>Name</span>
                  <span v-if="sortColumn === 'name'">{{ sortDirection === 'asc' ? '↑' : '↓' }}</span>
                </div>
              </th>
              <th @click="toggleSort('sku')" class="px-6 py-3 font-semibold cursor-pointer hover:text-[var(--color-ink)] select-none">
                <div class="flex items-center gap-1.5">
                  <span>SKU</span>
                  <span v-if="sortColumn === 'sku'">{{ sortDirection === 'asc' ? '↑' : '↓' }}</span>
                </div>
              </th>
              <th @click="toggleSort('price')" class="px-6 py-3 font-semibold cursor-pointer hover:text-[var(--color-ink)] select-none">
                <div class="flex items-center gap-1.5">
                  <span>Price</span>
                  <span v-if="sortColumn === 'price'">{{ sortDirection === 'asc' ? '↑' : '↓' }}</span>
                </div>
              </th>
              <th @click="toggleSort('stock')" class="px-6 py-3 font-semibold cursor-pointer hover:text-[var(--color-ink)] select-none">
                <div class="flex items-center gap-1.5">
                  <span>Stock</span>
                  <span v-if="sortColumn === 'stock'">{{ sortDirection === 'asc' ? '↑' : '↓' }}</span>
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
            <tr v-for="item in items" :key="item.id" class="hover:bg-gray-50 transition-colors">
              <td class="px-6 py-4">
                <div class="font-medium text-[var(--color-ink)]">{{ item.name }}</div>
                <div v-if="item.description" class="text-xs text-[var(--color-muted)] truncate max-w-xs">{{ item.description }}</div>
              </td>
              <td class="px-6 py-4 font-mono text-xs text-[var(--color-muted)]">
                {{ item.sku || 'SKU-' + item.id }}
              </td>
              <td class="px-6 py-4 font-semibold text-[var(--color-ink)]">
                ${{ Number(item.price).toFixed(2) }}
              </td>
              <td class="px-6 py-4">
                <span
                  :class="[
                    item.stock > 5 ? 'bg-emerald-100 text-emerald-800' :
                    item.stock > 0 ? 'bg-amber-100 text-amber-800' :
                    'bg-rose-100 text-rose-800',
                    'inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold'
                  ]"
                >
                  {{ item.stock > 0 ? item.stock + ' in stock' : 'Out of stock' }}
                </span>
              </td>
              <td class="px-6 py-4 text-xs font-mono text-[var(--color-muted)]">
                {{ item.created_at ? new Date(item.created_at).toLocaleDateString() : '—' }}
              </td>
              <td class="px-6 py-4 text-right space-x-2 whitespace-nowrap">
                <button
                  @click="openEditDrawer(item)"
                  class="text-xs font-medium text-[var(--color-evergreen)] hover:underline"
                >
                  Edit
                </button>
                <button
                  @click="deleteItem(item)"
                  class="text-xs font-medium text-red-600 hover:underline"
                >
                  Delete
                </button>
              </td>
            </tr>
          </tbody>
        </table>

        <!-- Server-side Pagination Bar -->
        <div class="px-6 py-3.5 bg-gray-50 border-t border-[var(--color-line)] flex flex-col sm:flex-row items-center justify-between gap-3 text-xs text-[var(--color-muted)]">
          <div>
            Showing <span class="font-semibold text-gray-800">{{ fromRecord }}</span> to <span class="font-semibold text-gray-800">{{ toRecord }}</span> of <span class="font-semibold text-gray-800">{{ totalRecords }}</span> items
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

    <!-- Slide-over Drawer for Add/Edit -->
    <SlideOverDrawer
      v-model="isDrawerOpen"
      :title="editingItem ? 'Edit Item' : 'Add New Item'"
      @close="closeDrawer"
    >
      <form @submit.prevent="saveItem" class="space-y-4">
        <div v-if="formError" class="rounded-lg bg-red-50 p-3 text-xs text-red-600 border border-red-200">
          {{ formError }}
        </div>

        <div>
          <label class="block text-xs font-semibold uppercase tracking-wider text-[var(--color-muted)] mb-1">
            Item Name *
          </label>
          <input
            v-model="form.name"
            type="text"
            required
            placeholder="Mechanical Keyboard"
            class="w-full rounded-lg border border-[var(--color-line)] bg-white px-3 py-2 text-sm text-[var(--color-ink)] focus:outline-none focus:ring-2 focus:ring-[var(--color-evergreen)]"
          />
        </div>

        <div>
          <label class="block text-xs font-semibold uppercase tracking-wider text-[var(--color-muted)] mb-1">
            SKU Code
          </label>
          <input
            v-model="form.sku"
            type="text"
            placeholder="KB-MECH-01"
            class="w-full rounded-lg border border-[var(--color-line)] bg-white px-3 py-2 text-sm text-[var(--color-ink)] focus:outline-none focus:ring-2 focus:ring-[var(--color-evergreen)]"
          />
        </div>

        <div>
          <label class="block text-xs font-semibold uppercase tracking-wider text-[var(--color-muted)] mb-1">
            Description
          </label>
          <textarea
            v-model="form.description"
            rows="3"
            placeholder="Product specifications..."
            class="w-full rounded-lg border border-[var(--color-line)] bg-white px-3 py-2 text-sm text-[var(--color-ink)] focus:outline-none focus:ring-2 focus:ring-[var(--color-evergreen)]"
          ></textarea>
        </div>

        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-xs font-semibold uppercase tracking-wider text-[var(--color-muted)] mb-1">
              Unit Price ($) *
            </label>
            <input
              v-model="form.price"
              type="number"
              step="0.01"
              min="0"
              required
              placeholder="99.99"
              class="w-full rounded-lg border border-[var(--color-line)] bg-white px-3 py-2 text-sm text-[var(--color-ink)] focus:outline-none focus:ring-2 focus:ring-[var(--color-evergreen)]"
            />
          </div>
          <div>
            <label class="block text-xs font-semibold uppercase tracking-wider text-[var(--color-muted)] mb-1">
              Stock Quantity *
            </label>
            <input
              v-model="form.stock"
              type="number"
              min="0"
              required
              placeholder="50"
              class="w-full rounded-lg border border-[var(--color-line)] bg-white px-3 py-2 text-sm text-[var(--color-ink)] focus:outline-none focus:ring-2 focus:ring-[var(--color-evergreen)]"
            />
          </div>
        </div>

        <div class="pt-4 flex items-center justify-end gap-3 border-t border-[var(--color-line)]">
          <button
            type="button"
            @click="closeDrawer"
            class="rounded-lg border border-[var(--color-line)] px-4 py-2 text-sm font-medium text-[var(--color-muted)] hover:bg-gray-50"
          >
            Cancel
          </button>
          <button
            type="submit"
            :disabled="isSaving"
            class="rounded-lg bg-[var(--color-evergreen)] px-4 py-2 text-sm font-medium text-white hover:opacity-90 disabled:opacity-50"
          >
            {{ isSaving ? 'Saving...' : (editingItem ? 'Save Changes' : 'Create Item') }}
          </button>
        </div>
      </form>
    </SlideOverDrawer>
  </div>
</template>
