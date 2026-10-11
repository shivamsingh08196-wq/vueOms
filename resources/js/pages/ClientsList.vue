<script setup>
import { ref, watch, onMounted } from 'vue';
import api from '../api/client';
import SlideOverDrawer from '../components/SlideOverDrawer.vue';

// Server-side State
const clients = ref([]);
const isLoading = ref(true);
const errorMessage = ref('');
const searchQuery = ref('');
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
const editingClient = ref(null);
const isSaving = ref(false);
const formError = ref('');
const form = ref({
  name: '',
  email: '',
  phone: '',
  address: '',
});

let debounceTimer = null;

const fetchClients = async () => {
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

    const res = await api.get(`/api/clients?${params.toString()}`);
    if (res && res.data) {
      clients.value = res.data;
      totalRecords.value = res.total ?? res.meta?.total ?? res.data.length;
      lastPage.value = res.last_page ?? res.meta?.last_page ?? 1;
      fromRecord.value = res.from ?? res.meta?.from ?? (clients.value.length ? 1 : 0);
      toRecord.value = res.to ?? res.meta?.to ?? clients.value.length;
    } else {
      clients.value = Array.isArray(res) ? res : [];
      totalRecords.value = clients.value.length;
      lastPage.value = 1;
    }
  } catch (err) {
    errorMessage.value = err.message || 'Failed to load clients.';
  } finally {
    isLoading.value = false;
  }
};

const handleSearchInput = () => {
  clearTimeout(debounceTimer);
  debounceTimer = setTimeout(() => {
    currentPage.value = 1;
    fetchClients();
  }, 300);
};

const toggleSort = (col) => {
  if (sortColumn.value === col) {
    sortDirection.value = sortDirection.value === 'asc' ? 'desc' : 'asc';
  } else {
    sortColumn.value = col;
    sortDirection.value = 'desc';
  }
  currentPage.value = 1;
  fetchClients();
};

const goToPage = (p) => {
  if (p < 1 || p > lastPage.value || p === currentPage.value) return;
  currentPage.value = p;
  fetchClients();
};

watch(perPage, () => {
  currentPage.value = 1;
  fetchClients();
});

const openCreateDrawer = () => {
  editingClient.value = null;
  formError.value = '';
  form.value = {
    name: '',
    email: '',
    phone: '',
    address: '',
  };
  isDrawerOpen.value = true;
};

const openEditDrawer = (client) => {
  editingClient.value = client;
  formError.value = '';
  form.value = {
    name: client.name,
    email: client.email,
    phone: client.phone || '',
    address: client.address || '',
  };
  isDrawerOpen.value = true;
};

const closeDrawer = () => {
  isDrawerOpen.value = false;
  editingClient.value = null;
};

const saveClient = async () => {
  formError.value = '';

  if (!form.value.name.trim()) {
    formError.value = 'Client name is required.';
    return;
  }
  if (!form.value.email.trim() || !form.value.email.includes('@')) {
    formError.value = 'A valid email address is required.';
    return;
  }

  isSaving.value = true;
  try {
    const payload = {
      name: form.value.name.trim(),
      email: form.value.email.trim(),
      phone: form.value.phone ? form.value.phone.trim() : null,
      address: form.value.address ? form.value.address.trim() : null,
    };

    if (editingClient.value) {
      await api.put(`/api/clients/${editingClient.value.id}`, payload);
    } else {
      await api.post('/api/clients', payload);
    }

    await fetchClients();
    closeDrawer();
  } catch (err) {
    formError.value = err.message || 'Failed to save client.';
  } finally {
    isSaving.value = false;
  }
};

const deleteClient = async (client) => {
  if (!confirm(`Are you sure you want to delete client "${client.name}"?`)) {
    return;
  }

  try {
    await api.delete(`/api/clients/${client.id}`);
    await fetchClients();
  } catch (err) {
    alert(err.message || 'Failed to delete client.');
  }
};

onMounted(() => {
  fetchClients();
});
</script>

<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
      <div>
        <h1 class="text-2xl font-bold tracking-tight text-[var(--color-ink)]">Clients</h1>
        <p class="text-sm text-[var(--color-muted)] mt-1">Manage client accounts and customer records.</p>
      </div>
      <div>
        <button
          @click="openCreateDrawer"
          class="inline-flex items-center gap-2 rounded-lg bg-[var(--color-evergreen)] px-4 py-2.5 text-sm font-medium text-white shadow-sm hover:opacity-95 transition-opacity"
        >
          <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
          </svg>
          Add Client
        </button>
      </div>
    </div>

    <!-- Controls: Backend Search, Per-Page, and Total count -->
    <div class="flex flex-col sm:flex-row gap-3 items-center justify-between">
      <div class="relative flex-1 w-full sm:max-w-md">
        <input
          v-model="searchQuery"
          @input="handleSearchInput"
          type="text"
          placeholder="Search by name, email, phone, address..."
          class="w-full rounded-lg border border-[var(--color-line)] bg-white px-4 py-2 text-sm text-[var(--color-ink)] placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-[var(--color-evergreen)]"
        />
        <div class="absolute inset-y-0 right-0 flex items-center pr-3 pointer-events-none text-gray-400">
          <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
          </svg>
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
        <div>Loading clients from server...</div>
      </div>

      <div v-else-if="errorMessage" class="p-8 text-center text-sm text-red-600">
        <p>{{ errorMessage }}</p>
        <button @click="fetchClients" class="mt-2 text-sm font-medium underline text-[var(--color-evergreen)]">Retry</button>
      </div>

      <div v-else-if="clients.length === 0" class="p-12 text-center text-sm text-[var(--color-muted)]">
        No clients match your search query.
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
              <th @click="toggleSort('email')" class="px-6 py-3 font-semibold cursor-pointer hover:text-[var(--color-ink)] select-none">
                <div class="flex items-center gap-1.5">
                  <span>Email</span>
                  <span v-if="sortColumn === 'email'">{{ sortDirection === 'asc' ? '↑' : '↓' }}</span>
                </div>
              </th>
              <th @click="toggleSort('phone')" class="px-6 py-3 font-semibold cursor-pointer hover:text-[var(--color-ink)] select-none">
                <div class="flex items-center gap-1.5">
                  <span>Phone</span>
                  <span v-if="sortColumn === 'phone'">{{ sortDirection === 'asc' ? '↑' : '↓' }}</span>
                </div>
              </th>
              <th class="px-6 py-3 font-semibold">Address</th>
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
            <tr v-for="client in clients" :key="client.id" class="hover:bg-gray-50 transition-colors">
              <td class="px-6 py-4 font-medium text-[var(--color-ink)]">{{ client.name }}</td>
              <td class="px-6 py-4 text-[var(--color-muted)]">{{ client.email }}</td>
              <td class="px-6 py-4 text-[var(--color-muted)]">{{ client.phone || '—' }}</td>
              <td class="px-6 py-4 text-[var(--color-muted)] max-w-xs truncate">{{ client.address || '—' }}</td>
              <td class="px-6 py-4 text-[var(--color-muted)] text-xs font-mono">
                {{ client.created_at ? new Date(client.created_at).toLocaleDateString() : '—' }}
              </td>
              <td class="px-6 py-4 text-right space-x-2 whitespace-nowrap">
                <button
                  @click="openEditDrawer(client)"
                  class="text-xs font-medium text-[var(--color-evergreen)] hover:underline"
                >
                  Edit
                </button>
                <button
                  @click="deleteClient(client)"
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
            Showing <span class="font-semibold text-gray-800">{{ fromRecord }}</span> to <span class="font-semibold text-gray-800">{{ toRecord }}</span> of <span class="font-semibold text-gray-800">{{ totalRecords }}</span> clients
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
      :title="editingClient ? 'Edit Client' : 'Add New Client'"
      @close="closeDrawer"
    >
      <form @submit.prevent="saveClient" class="space-y-4">
        <div v-if="formError" class="rounded-lg bg-red-50 p-3 text-xs text-red-600 border border-red-200">
          {{ formError }}
        </div>

        <div>
          <label class="block text-xs font-semibold uppercase tracking-wider text-[var(--color-muted)] mb-1">
            Client Name *
          </label>
          <input
            v-model="form.name"
            type="text"
            required
            placeholder="Acme Corporation"
            class="w-full rounded-lg border border-[var(--color-line)] bg-white px-3 py-2 text-sm text-[var(--color-ink)] focus:outline-none focus:ring-2 focus:ring-[var(--color-evergreen)]"
          />
        </div>

        <div>
          <label class="block text-xs font-semibold uppercase tracking-wider text-[var(--color-muted)] mb-1">
            Email Address *
          </label>
          <input
            v-model="form.email"
            type="email"
            required
            placeholder="contact@acme.com"
            class="w-full rounded-lg border border-[var(--color-line)] bg-white px-3 py-2 text-sm text-[var(--color-ink)] focus:outline-none focus:ring-2 focus:ring-[var(--color-evergreen)]"
          />
        </div>

        <div>
          <label class="block text-xs font-semibold uppercase tracking-wider text-[var(--color-muted)] mb-1">
            Phone Number
          </label>
          <input
            v-model="form.phone"
            type="text"
            placeholder="+1 (555) 000-0000"
            class="w-full rounded-lg border border-[var(--color-line)] bg-white px-3 py-2 text-sm text-[var(--color-ink)] focus:outline-none focus:ring-2 focus:ring-[var(--color-evergreen)]"
          />
        </div>

        <div>
          <label class="block text-xs font-semibold uppercase tracking-wider text-[var(--color-muted)] mb-1">
            Billing Address
          </label>
          <textarea
            v-model="form.address"
            rows="3"
            placeholder="123 Business Way, Suite 100"
            class="w-full rounded-lg border border-[var(--color-line)] bg-white px-3 py-2 text-sm text-[var(--color-ink)] focus:outline-none focus:ring-2 focus:ring-[var(--color-evergreen)]"
          ></textarea>
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
            {{ isSaving ? 'Saving...' : (editingClient ? 'Save Changes' : 'Create Client') }}
          </button>
        </div>
      </form>
    </SlideOverDrawer>
  </div>
</template>
