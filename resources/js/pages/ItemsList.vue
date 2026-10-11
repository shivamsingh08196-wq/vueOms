<script setup>
import { ref, computed, onMounted } from 'vue';
import api from '../api/client';
import SlideOverDrawer from '../components/SlideOverDrawer.vue';

const items = ref([]);
const isLoading = ref(true);
const errorMessage = ref('');
const searchQuery = ref('');

// Drawer / Form state
const isDrawerOpen = ref(false);
const editingItem = ref(null);
const isSaving = ref(false);
const formError = ref('');
const form = ref({
  name: '',
  description: '',
  price: '',
  stock: 0,
});

const drawerTitle = computed(() => editingItem.value ? 'Edit Item' : 'Add New Item');

const filteredItems = computed(() => {
  if (!searchQuery.value.trim()) return items.value;
  const q = searchQuery.value.toLowerCase();
  return items.value.filter(item =>
    item.name?.toLowerCase().includes(q) ||
    item.description?.toLowerCase().includes(q) ||
    String(item.id).includes(q)
  );
});

const fetchItems = async () => {
  isLoading.value = true;
  errorMessage.value = '';
  try {
    const data = await api.get('/api/items');
    items.value = Array.isArray(data) ? data : (data.data || []);
  } catch (err) {
    errorMessage.value = err.message || 'Failed to load items.';
  } finally {
    isLoading.value = false;
  }
};

const openCreateDrawer = () => {
  editingItem.value = null;
  formError.value = '';
  form.value = {
    name: '',
    description: '',
    price: '',
    stock: 0,
  };
  isDrawerOpen.value = true;
};

const openEditDrawer = (item) => {
  editingItem.value = item;
  formError.value = '';
  form.value = {
    name: item.name,
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
  if (form.value.price === '' || isNaN(Number(form.value.price)) || Number(form.value.price) < 0) {
    formError.value = 'A valid positive price is required.';
    return;
  }
  if (form.value.stock === '' || isNaN(Number(form.value.stock)) || Number(form.value.stock) < 0) {
    formError.value = 'A valid non-negative stock quantity is required.';
    return;
  }

  isSaving.value = true;
  try {
    const payload = {
      name: form.value.name.trim(),
      description: form.value.description ? form.value.description.trim() : null,
      price: parseFloat(form.value.price),
      stock: parseInt(form.value.stock, 10),
    };

    if (editingItem.value) {
      await api.put(`/api/items/${editingItem.value.id}`, payload);
    } else {
      await api.post('/api/items', payload);
    }

    closeDrawer();
    await fetchItems();
  } catch (err) {
    formError.value = err.data?.message || err.message || 'Failed to save item.';
  } finally {
    isSaving.value = false;
  }
};

const deleteItem = async (id) => {
  if (!confirm('Are you sure you want to delete this item?')) return;
  try {
    await api.delete(`/api/items/${id}`);
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
  <div>
    <!-- Header -->
    <div class="sm:flex sm:items-center sm:justify-between mb-8">
      <div>
        <h1 class="text-2xl font-bold text-[var(--color-ink)]">Items</h1>
        <p class="mt-1 text-sm text-[var(--color-muted)]">Browse, add, and manage items in your inventory.</p>
      </div>
      <div class="mt-4 sm:mt-0 flex items-center gap-3">
        <button
          type="button"
          @click="openCreateDrawer"
          data-testid="item-new"
          class="inline-flex items-center gap-2 rounded-md bg-[var(--color-evergreen)] px-4 py-2.5 text-center text-sm font-semibold text-white shadow-sm hover:bg-[var(--color-teal)] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--color-amber)] transition-all cursor-pointer"
        >
          <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
          </svg>
          Add Item
        </button>
      </div>
    </div>

    <!-- Toolbar: Search & Refresh -->
    <div class="bg-white rounded-t-[var(--radius-card)] border border-[var(--color-line)] p-4 flex flex-col sm:flex-row justify-between items-center gap-4">
      <div class="relative w-full sm:w-72">
        <input
          v-model="searchQuery"
          type="text"
          placeholder="Search items by name or ID..."
          class="w-full rounded-md border border-[var(--color-line)] bg-gray-50 px-3 py-2 pl-9 text-sm text-[var(--color-ink)] focus:bg-white focus:outline-none focus:ring-2 focus:ring-[var(--color-evergreen)]"
        />
        <svg class="absolute left-3 top-2.5 h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
        </svg>
      </div>

      <div class="text-xs text-[var(--color-muted)] flex items-center gap-2">
        <span>Showing {{ filteredItems.length }} item{{ filteredItems.length === 1 ? '' : 's' }}</span>
        <button
          type="button"
          @click="fetchItems"
          class="text-[var(--color-evergreen)] hover:underline ml-2"
        >
          Refresh
        </button>
      </div>
    </div>

    <!-- Error State -->
    <div v-if="errorMessage" class="bg-red-50 border-x border-b border-red-200 text-red-700 p-4 text-sm flex justify-between items-center">
      <span>{{ errorMessage }}</span>
      <button @click="fetchItems" class="font-semibold underline hover:text-red-800">Retry</button>
    </div>

    <!-- Loading State -->
    <div v-if="isLoading" class="bg-white border-x border-b border-[var(--color-line)] p-12 text-center text-[var(--color-muted)]">
      <div class="inline-block animate-spin rounded-full h-8 w-8 border-4 border-t-[var(--color-evergreen)] border-gray-200 mb-3"></div>
      <p class="text-sm font-medium">Loading items...</p>
    </div>

    <!-- Empty State -->
    <div v-else-if="filteredItems.length === 0" class="bg-white border-x border-b border-[var(--color-line)] rounded-b-[var(--radius-card)] p-12 text-center">
      <div class="mx-auto w-12 h-12 rounded-full bg-gray-100 flex items-center justify-center text-gray-400 mb-3">
        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
        </svg>
      </div>
      <h3 class="text-base font-medium text-[var(--color-ink)]">No items found</h3>
      <p class="mt-1 text-sm text-[var(--color-muted)]">
        {{ searchQuery ? 'Try adjusting your search criteria.' : 'Get started by creating your first catalog item.' }}
      </p>
      <div v-if="!searchQuery" class="mt-4">
        <button
          type="button"
          @click="openCreateDrawer"
          class="inline-flex items-center gap-1.5 rounded-md bg-[var(--color-evergreen)] px-3.5 py-2 text-sm font-semibold text-white shadow-sm hover:bg-[var(--color-teal)]"
        >
          Add Item
        </button>
      </div>
    </div>

    <!-- Table -->
    <div v-else class="bg-white border-x border-b border-[var(--color-line)] rounded-b-[var(--radius-card)] overflow-x-auto shadow-sm">
      <table class="min-w-full divide-y divide-[var(--color-line)] text-left text-sm">
        <thead class="bg-gray-50 text-[var(--color-muted)] text-xs font-semibold uppercase tracking-wider">
          <tr>
            <th class="py-3.5 px-4">ID</th>
            <th class="py-3.5 px-4">Name & Description</th>
            <th class="py-3.5 px-4">Price</th>
            <th class="py-3.5 px-4">Stock</th>
            <th class="py-3.5 px-4 text-right">Actions</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-[var(--color-line)] text-[var(--color-ink)]">
          <tr v-for="item in filteredItems" :key="item.id" class="hover:bg-gray-50 transition-colors">
            <td class="py-4 px-4 font-mono text-xs text-gray-500">#{{ item.id }}</td>
            <td class="py-4 px-4">
              <div class="font-semibold text-[var(--color-ink)]">{{ item.name }}</div>
              <div v-if="item.description" class="text-xs text-[var(--color-muted)] mt-0.5 line-clamp-1">{{ item.description }}</div>
            </td>
            <td class="py-4 px-4 font-semibold text-[var(--color-evergreen)]">
              ${{ Number(item.price).toFixed(2) }}
            </td>
            <td class="py-4 px-4">
              <span
                :class="[
                  'inline-flex items-center px-2 py-0.5 rounded text-xs font-medium',
                  item.stock > 10 ? 'bg-green-100 text-green-800' :
                  item.stock > 0 ? 'bg-amber-100 text-amber-800' :
                  'bg-red-100 text-red-800'
                ]"
              >
                {{ item.stock }} in stock
              </span>
            </td>
            <td class="py-4 px-4 text-right space-x-2">
              <button
                type="button"
                @click="openEditDrawer(item)"
                class="text-xs font-medium text-[var(--color-teal)] hover:text-[var(--color-evergreen)] px-2 py-1 rounded hover:bg-gray-100 transition-colors cursor-pointer"
              >
                Edit
              </button>
              <button
                type="button"
                @click="deleteItem(item.id)"
                class="text-xs font-medium text-red-600 hover:text-red-800 px-2 py-1 rounded hover:bg-red-50 transition-colors cursor-pointer"
              >
                Delete
              </button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- SlideOver Drawer for Add / Edit Item -->
    <SlideOverDrawer v-model="isDrawerOpen" :title="drawerTitle">
      <form @submit.prevent="saveItem" class="space-y-5 pt-2">
        <div v-if="formError" class="p-3 bg-red-50 text-red-700 text-sm rounded-md border border-red-200">
          {{ formError }}
        </div>

        <div>
          <label class="block text-sm font-medium text-[var(--color-ink)] mb-1">
            Item Name <span class="text-red-500">*</span>
          </label>
          <input
            v-model="form.name"
            type="text"
            required
            placeholder="e.g. Mechanical Keyboard"
            class="w-full rounded-md border border-[var(--color-line)] px-3 py-2 text-sm text-[var(--color-ink)] focus:outline-none focus:ring-2 focus:ring-[var(--color-evergreen)]"
          />
        </div>

        <div>
          <label class="block text-sm font-medium text-[var(--color-ink)] mb-1">
            Price ($) <span class="text-red-500">*</span>
          </label>
          <input
            v-model="form.price"
            type="number"
            step="0.01"
            min="0"
            required
            placeholder="99.99"
            class="w-full rounded-md border border-[var(--color-line)] px-3 py-2 text-sm text-[var(--color-ink)] focus:outline-none focus:ring-2 focus:ring-[var(--color-evergreen)]"
          />
        </div>

        <div>
          <label class="block text-sm font-medium text-[var(--color-ink)] mb-1">
            Stock Quantity <span class="text-red-500">*</span>
          </label>
          <input
            v-model.number="form.stock"
            type="number"
            min="0"
            required
            placeholder="50"
            class="w-full rounded-md border border-[var(--color-line)] px-3 py-2 text-sm text-[var(--color-ink)] focus:outline-none focus:ring-2 focus:ring-[var(--color-evergreen)]"
          />
        </div>

        <div>
          <label class="block text-sm font-medium text-[var(--color-ink)] mb-1">
            Description
          </label>
          <textarea
            v-model="form.description"
            rows="3"
            placeholder="Optional detailed description of the item..."
            class="w-full rounded-md border border-[var(--color-line)] px-3 py-2 text-sm text-[var(--color-ink)] focus:outline-none focus:ring-2 focus:ring-[var(--color-evergreen)]"
          ></textarea>
        </div>

        <div class="pt-4 border-t border-[var(--color-line)] flex items-center justify-end gap-3">
          <button
            type="button"
            @click="closeDrawer"
            class="px-4 py-2 text-sm font-medium text-[var(--color-muted)] hover:text-[var(--color-ink)] border border-[var(--color-line)] rounded-md hover:bg-gray-50"
          >
            Cancel
          </button>
          <button
            type="submit"
            :disabled="isSaving"
            class="inline-flex items-center gap-2 px-4 py-2 text-sm font-semibold text-white bg-[var(--color-evergreen)] hover:bg-[var(--color-teal)] rounded-md shadow-sm disabled:opacity-50"
          >
            <span v-if="isSaving" class="inline-block animate-spin h-4 w-4 border-2 border-white border-t-transparent rounded-full"></span>
            {{ editingItem ? 'Save Changes' : 'Create Item' }}
          </button>
        </div>
      </form>
    </SlideOverDrawer>
  </div>
</template>
