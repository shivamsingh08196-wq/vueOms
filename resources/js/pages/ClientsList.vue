<script setup>
import { ref, computed, onMounted } from 'vue';
import api from '../api/client';
import SlideOverDrawer from '../components/SlideOverDrawer.vue';

const clients = ref([]);
const isLoading = ref(true);
const errorMessage = ref('');
const searchQuery = ref('');

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

const drawerTitle = computed(() => editingClient.value ? 'Edit Client' : 'Add New Client');

const filteredClients = computed(() => {
  if (!searchQuery.value.trim()) return clients.value;
  const q = searchQuery.value.toLowerCase();
  return clients.value.filter(c =>
    c.name?.toLowerCase().includes(q) ||
    c.email?.toLowerCase().includes(q) ||
    c.phone?.toLowerCase().includes(q) ||
    c.address?.toLowerCase().includes(q) ||
    String(c.id).includes(q)
  );
});

const fetchClients = async () => {
  isLoading.value = true;
  errorMessage.value = '';
  try {
    const data = await api.get('/api/clients');
    clients.value = Array.isArray(data) ? data : (data.data || []);
  } catch (err) {
    errorMessage.value = err.message || 'Failed to load clients.';
  } finally {
    isLoading.value = false;
  }
};

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

    closeDrawer();
    await fetchClients();
  } catch (err) {
    formError.value = err.data?.message || err.message || 'Failed to save client.';
  } finally {
    isSaving.value = false;
  }
};

const deleteClient = async (id) => {
  if (!confirm('Are you sure you want to delete this client?')) return;
  try {
    await api.delete(`/api/clients/${id}`);
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
  <div>
    <!-- Header -->
    <div class="sm:flex sm:items-center sm:justify-between mb-8">
      <div>
        <h1 class="text-2xl font-bold text-[var(--color-ink)]">Clients</h1>
        <p class="mt-1 text-sm text-[var(--color-muted)]">A list of all clients in the system.</p>
      </div>
      <div class="mt-4 sm:mt-0 flex items-center gap-3">
        <button
          type="button"
          @click="openCreateDrawer"
          data-testid="client-new"
          class="inline-flex items-center gap-2 rounded-md bg-[var(--color-evergreen)] px-4 py-2.5 text-center text-sm font-semibold text-white shadow-sm hover:bg-[var(--color-teal)] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--color-amber)] transition-all cursor-pointer"
        >
          <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
          </svg>
          Add Client
        </button>
      </div>
    </div>

    <!-- Toolbar: Search & Refresh -->
    <div class="bg-white rounded-t-[var(--radius-card)] border border-[var(--color-line)] p-4 flex flex-col sm:flex-row justify-between items-center gap-4">
      <div class="relative w-full sm:w-72">
        <input
          v-model="searchQuery"
          type="text"
          placeholder="Search clients by name, email..."
          class="w-full rounded-md border border-[var(--color-line)] bg-gray-50 px-3 py-2 pl-9 text-sm text-[var(--color-ink)] focus:bg-white focus:outline-none focus:ring-2 focus:ring-[var(--color-evergreen)]"
        />
        <svg class="absolute left-3 top-2.5 h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
        </svg>
      </div>

      <div class="text-xs text-[var(--color-muted)] flex items-center gap-2">
        <span>Showing {{ filteredClients.length }} client{{ filteredClients.length === 1 ? '' : 's' }}</span>
        <button
          type="button"
          @click="fetchClients"
          class="text-[var(--color-evergreen)] hover:underline ml-2"
        >
          Refresh
        </button>
      </div>
    </div>

    <!-- Error State -->
    <div v-if="errorMessage" class="bg-red-50 border-x border-b border-red-200 text-red-700 p-4 text-sm flex justify-between items-center">
      <span>{{ errorMessage }}</span>
      <button @click="fetchClients" class="font-semibold underline hover:text-red-800">Retry</button>
    </div>

    <!-- Loading State -->
    <div v-if="isLoading" class="bg-white border-x border-b border-[var(--color-line)] p-12 text-center text-[var(--color-muted)]">
      <div class="inline-block animate-spin rounded-full h-8 w-8 border-4 border-t-[var(--color-evergreen)] border-gray-200 mb-3"></div>
      <p class="text-sm font-medium">Loading clients...</p>
    </div>

    <!-- Empty State -->
    <div v-else-if="filteredClients.length === 0" class="bg-white border-x border-b border-[var(--color-line)] rounded-b-[var(--radius-card)] p-12 text-center">
      <div class="mx-auto w-12 h-12 rounded-full bg-gray-100 flex items-center justify-center text-gray-400 mb-3">
        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
        </svg>
      </div>
      <h3 class="text-base font-medium text-[var(--color-ink)]">No clients found</h3>
      <p class="mt-1 text-sm text-[var(--color-muted)]">
        {{ searchQuery ? 'Try adjusting your search criteria.' : 'Get started by creating your first client record.' }}
      </p>
      <div v-if="!searchQuery" class="mt-4">
        <button
          type="button"
          @click="openCreateDrawer"
          class="inline-flex items-center gap-1.5 rounded-md bg-[var(--color-evergreen)] px-3.5 py-2 text-sm font-semibold text-white shadow-sm hover:bg-[var(--color-teal)]"
        >
          Add Client
        </button>
      </div>
    </div>

    <!-- Table -->
    <div v-else class="bg-white border-x border-b border-[var(--color-line)] rounded-b-[var(--radius-card)] overflow-x-auto shadow-sm">
      <table class="min-w-full divide-y divide-[var(--color-line)] text-left text-sm">
        <thead class="bg-gray-50 text-[var(--color-muted)] text-xs font-semibold uppercase tracking-wider">
          <tr>
            <th class="py-3.5 px-4">ID</th>
            <th class="py-3.5 px-4">Client Name</th>
            <th class="py-3.5 px-4">Email</th>
            <th class="py-3.5 px-4">Phone</th>
            <th class="py-3.5 px-4">Address</th>
            <th class="py-3.5 px-4 text-right">Actions</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-[var(--color-line)] text-[var(--color-ink)]">
          <tr v-for="client in filteredClients" :key="client.id" class="hover:bg-gray-50 transition-colors">
            <td class="py-4 px-4 font-mono text-xs text-gray-500">#{{ client.id }}</td>
            <td class="py-4 px-4 font-semibold text-[var(--color-ink)]">
              {{ client.name }}
            </td>
            <td class="py-4 px-4 text-sm text-[var(--color-muted)]">
              <a :href="`mailto:${client.email}`" class="text-[var(--color-teal)] hover:underline">{{ client.email }}</a>
            </td>
            <td class="py-4 px-4 text-sm text-[var(--color-muted)]">
              {{ client.phone || '—' }}
            </td>
            <td class="py-4 px-4 text-sm text-[var(--color-muted)] max-w-xs truncate">
              {{ client.address || '—' }}
            </td>
            <td class="py-4 px-4 text-right space-x-2">
              <button
                type="button"
                @click="openEditDrawer(client)"
                class="text-xs font-medium text-[var(--color-teal)] hover:text-[var(--color-evergreen)] px-2 py-1 rounded hover:bg-gray-100 transition-colors cursor-pointer"
              >
                Edit
              </button>
              <button
                type="button"
                @click="deleteClient(client.id)"
                class="text-xs font-medium text-red-600 hover:text-red-800 px-2 py-1 rounded hover:bg-red-50 transition-colors cursor-pointer"
              >
                Delete
              </button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- SlideOver Drawer for Add / Edit Client -->
    <SlideOverDrawer v-model="isDrawerOpen" :title="drawerTitle">
      <form @submit.prevent="saveClient" class="space-y-5 pt-2">
        <div v-if="formError" class="p-3 bg-red-50 text-red-700 text-sm rounded-md border border-red-200">
          {{ formError }}
        </div>

        <div>
          <label class="block text-sm font-medium text-[var(--color-ink)] mb-1">
            Client Name <span class="text-red-500">*</span>
          </label>
          <input
            v-model="form.name"
            type="text"
            required
            placeholder="e.g. Acme Corporation"
            class="w-full rounded-md border border-[var(--color-line)] px-3 py-2 text-sm text-[var(--color-ink)] focus:outline-none focus:ring-2 focus:ring-[var(--color-evergreen)]"
          />
        </div>

        <div>
          <label class="block text-sm font-medium text-[var(--color-ink)] mb-1">
            Email Address <span class="text-red-500">*</span>
          </label>
          <input
            v-model="form.email"
            type="email"
            required
            placeholder="contact@acme.com"
            class="w-full rounded-md border border-[var(--color-line)] px-3 py-2 text-sm text-[var(--color-ink)] focus:outline-none focus:ring-2 focus:ring-[var(--color-evergreen)]"
          />
        </div>

        <div>
          <label class="block text-sm font-medium text-[var(--color-ink)] mb-1">
            Phone Number
          </label>
          <input
            v-model="form.phone"
            type="tel"
            placeholder="+1 (555) 000-0000"
            class="w-full rounded-md border border-[var(--color-line)] px-3 py-2 text-sm text-[var(--color-ink)] focus:outline-none focus:ring-2 focus:ring-[var(--color-evergreen)]"
          />
        </div>

        <div>
          <label class="block text-sm font-medium text-[var(--color-ink)] mb-1">
            Billing Address
          </label>
          <textarea
            v-model="form.address"
            rows="3"
            placeholder="123 Corporate Blvd, Suite 400..."
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
            {{ editingClient ? 'Save Changes' : 'Create Client' }}
          </button>
        </div>
      </form>
    </SlideOverDrawer>
  </div>
</template>
