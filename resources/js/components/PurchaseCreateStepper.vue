<script setup>
import { ref, computed, onMounted } from 'vue';
import api from '../api/client';
import { useAuthStore } from '../stores/auth';

const emit = defineEmits(['close', 'created']);

const authStore = useAuthStore();
const canAssign = computed(() => authStore.isAdmin || authStore.isManager || authStore.can('orders.assign'));

const currentStep = ref(1);
const steps = [
  { id: 1, name: 'Client & Details' },
  { id: 2, name: 'Add Items' },
  { id: 3, name: 'Review & Submit' }
];

// Data sources
const clients = ref([]);
const items = ref([]);
const users = ref([]);
const isLoadingData = ref(true);
const isSubmitting = ref(false);
const errorMessage = ref('');

// Form state
const selectedClientId = ref('');
const purchaseDate = ref(new Date().toISOString().slice(0, 10));
const priority = ref('normal');
const assignedTo = ref('');
const notes = ref('');

const lines = ref([
  { item_id: '', quantity: 1, unit_price: 0, item: null }
]);

const fetchInitialData = async () => {
  isLoadingData.value = true;
  errorMessage.value = '';
  try {
    const promises = [
      api.get('/api/clients?all=1'),
      api.get('/api/items?all=1'),
    ];
    if (canAssign.value) {
      promises.push(api.get('/api/users?all=1'));
    }

    const [clientsRes, itemsRes, usersRes] = await Promise.all(promises);
    clients.value = Array.isArray(clientsRes) ? clientsRes : (clientsRes?.data || []);
    items.value = Array.isArray(itemsRes) ? itemsRes : (itemsRes?.data || []);
    if (usersRes) {
      users.value = Array.isArray(usersRes) ? usersRes : (usersRes?.data || []);
    }

    if (clients.value.length > 0) {
      selectedClientId.value = clients.value[0].id;
    }
  } catch (err) {
    errorMessage.value = err.message || 'Failed to load form data.';
  } finally {
    isLoadingData.value = false;
  }
};

const selectedClient = computed(() => {
  return clients.value.find(c => c.id === Number(selectedClientId.value)) || null;
});

const onItemSelected = (lineIndex, itemId) => {
  const item = items.value.find(i => i.id === Number(itemId));
  if (item) {
    lines.value[lineIndex].item_id = item.id;
    lines.value[lineIndex].unit_price = Number(item.price);
    lines.value[lineIndex].item = item;
  }
};

const addLine = () => {
  lines.value.push({
    item_id: '',
    quantity: 1,
    unit_price: 0,
    item: null,
  });
};

const removeLine = (index) => {
  if (lines.value.length > 1) {
    lines.value.splice(index, 1);
  }
};

const subtotal = computed(() => {
  return lines.value.reduce((sum, line) => {
    return sum + (Number(line.quantity || 0) * Number(line.unit_price || 0));
  }, 0);
});

const tax = computed(() => {
  return subtotal.value * 0.10; // 10% standard tax
});

const total = computed(() => {
  return subtotal.value + tax.value;
});

const nextStep = () => {
  errorMessage.value = '';
  if (currentStep.value === 1) {
    if (!selectedClientId.value) {
      errorMessage.value = 'Please select a client to proceed.';
      return;
    }
    currentStep.value = 2;
  } else if (currentStep.value === 2) {
    const hasInvalidLine = lines.value.some(l => !l.item_id || l.quantity < 1);
    if (hasInvalidLine) {
      errorMessage.value = 'Please select an item and valid quantity for each line.';
      return;
    }
    currentStep.value = 3;
  }
};

const prevStep = () => {
  errorMessage.value = '';
  if (currentStep.value > 1) currentStep.value--;
};

const submitPurchase = async () => {
  errorMessage.value = '';
  isSubmitting.value = true;
  try {
    const payload = {
      client_id: Number(selectedClientId.value),
      purchase_date: purchaseDate.value,
      priority: priority.value,
      assigned_to: assignedTo.value ? Number(assignedTo.value) : null,
      notes: notes.value ? notes.value.trim() : null,
      lines: lines.value.map(l => ({
        item_id: Number(l.item_id),
        quantity: Number(l.quantity),
        unit_price: Number(l.unit_price),
      })),
    };

    const created = await api.post('/api/purchases', payload);
    emit('created', created);
    emit('close');
  } catch (err) {
    errorMessage.value = err.message || 'Failed to create purchase.';
  } finally {
    isSubmitting.value = false;
  }
};

onMounted(() => {
  fetchInitialData();
});
</script>

<template>
  <div class="fixed inset-0 z-50 overflow-hidden bg-black/40 flex justify-center items-center p-4">
    <div class="bg-white rounded-2xl shadow-2xl border border-[var(--color-line)] w-full max-w-4xl max-h-[90vh] flex flex-col overflow-hidden">
      <!-- Top header bar -->
      <div class="flex items-center justify-between px-6 py-4 border-b border-[var(--color-line)] bg-gray-50">
        <div>
          <h2 class="text-lg font-bold text-[var(--color-ink)]">Create New Purchase</h2>
          <p class="text-xs text-[var(--color-muted)]">Step {{ currentStep }} of {{ steps.length }}: {{ steps[currentStep - 1].name }}</p>
        </div>
        <button @click="emit('close')" class="text-gray-400 hover:text-gray-600 rounded-lg p-1.5 hover:bg-gray-200">
          <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
          </svg>
        </button>
      </div>

      <!-- Main Layout with Left Rail & Content Area -->
      <div class="flex-1 flex overflow-hidden">
        <!-- Left Stepper Rail -->
        <div class="w-60 bg-gray-50/70 border-r border-[var(--color-line)] p-6 hidden md:block">
          <nav aria-label="Progress">
            <ol class="space-y-6">
              <li v-for="step in steps" :key="step.id" class="flex items-start">
                <div class="flex items-center">
                  <span
                    :class="[
                      step.id < currentStep ? 'bg-[var(--color-evergreen)] text-white' :
                      step.id === currentStep ? 'border-2 border-[var(--color-evergreen)] text-[var(--color-evergreen)] bg-white font-bold' :
                      'border-2 border-gray-300 text-gray-400 bg-white',
                      'flex h-8 w-8 items-center justify-center rounded-full text-xs font-semibold'
                    ]"
                  >
                    <svg v-if="step.id < currentStep" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
                    </svg>
                    <span v-else>{{ step.id }}</span>
                  </span>
                </div>
                <div class="ml-3">
                  <p :class="[step.id === currentStep ? 'font-bold text-[var(--color-ink)]' : 'text-gray-500', 'text-xs']">
                    {{ step.name }}
                  </p>
                </div>
              </li>
            </ol>
          </nav>

          <div class="mt-12 p-3 bg-white rounded-lg border border-[var(--color-line)] text-xs text-[var(--color-muted)]">
            <span class="font-semibold text-gray-700 block mb-1">Live Subtotal:</span>
            <span class="text-base font-bold text-[var(--color-evergreen)]">${{ subtotal.toFixed(2) }}</span>
          </div>
        </div>

        <!-- Content Area -->
        <div class="flex-1 overflow-y-auto p-6">
          <div v-if="errorMessage" class="mb-4 p-3 rounded-lg bg-red-50 border border-red-200 text-xs text-red-700">
            {{ errorMessage }}
          </div>

          <div v-if="isLoadingData" class="p-12 text-center text-sm text-[var(--color-muted)]">
            <div class="animate-spin inline-block w-6 h-6 border-[3px] border-current border-t-transparent text-[var(--color-evergreen)] rounded-full mb-2"></div>
            <div>Loading client directory and item catalog...</div>
          </div>

          <!-- STEP 1: CLIENT SELECTION & DETAILS -->
          <div v-else-if="currentStep === 1" class="space-y-4">
            <div>
              <label class="block text-xs font-semibold uppercase tracking-wider text-[var(--color-muted)] mb-1">
                Select Client *
              </label>
              <select
                v-model="selectedClientId"
                data-testid="client-select"
                class="w-full rounded-lg border border-[var(--color-line)] bg-white px-3 py-2.5 text-sm text-[var(--color-ink)] focus:outline-none focus:ring-2 focus:ring-[var(--color-evergreen)]"
              >
                <option value="" disabled>Choose a client...</option>
                <option v-for="c in clients" :key="c.id" :value="c.id">
                  {{ c.name }} ({{ c.email }})
                </option>
              </select>
            </div>

            <!-- Client Info Card -->
            <div v-if="selectedClient" class="p-3 bg-gray-50 rounded-lg border border-[var(--color-line)] text-xs space-y-1">
              <div class="font-semibold text-gray-800">{{ selectedClient.name }}</div>
              <div class="text-gray-600">Email: {{ selectedClient.email }}</div>
              <div v-if="selectedClient.phone" class="text-gray-600">Phone: {{ selectedClient.phone }}</div>
              <div v-if="selectedClient.address" class="text-gray-600">Address: {{ selectedClient.address }}</div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-[var(--color-muted)] mb-1">
                  Purchase Date *
                </label>
                <input
                  v-model="purchaseDate"
                  type="date"
                  class="w-full rounded-lg border border-[var(--color-line)] bg-white px-3 py-2 text-sm text-[var(--color-ink)] focus:outline-none focus:ring-2 focus:ring-[var(--color-evergreen)]"
                />
              </div>
              <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-[var(--color-muted)] mb-1">
                  Priority
                </label>
                <select
                  v-model="priority"
                  class="w-full rounded-lg border border-[var(--color-line)] bg-white px-3 py-2 text-sm text-[var(--color-ink)] focus:outline-none focus:ring-2 focus:ring-[var(--color-evergreen)]"
                >
                  <option value="normal">Normal</option>
                  <option value="high">High</option>
                  <option value="urgent">Urgent</option>
                  <option value="low">Low</option>
                </select>
              </div>
            </div>

            <!-- Optional Assignment (Admin / Manager) -->
            <div v-if="canAssign">
              <label class="block text-xs font-semibold uppercase tracking-wider text-[var(--color-muted)] mb-1">
                Assign To Staff / Manager (Optional)
              </label>
              <select
                v-model="assignedTo"
                data-testid="create-assignee-select"
                class="w-full rounded-lg border border-[var(--color-line)] bg-white px-3 py-2 text-sm text-[var(--color-ink)] focus:outline-none focus:ring-2 focus:ring-[var(--color-evergreen)]"
              >
                <option value="">Unassigned (Select later)</option>
                <option v-for="u in users" :key="u.id" :value="String(u.id)">
                  {{ u.name }} ({{ u.roles?.map(r => r.name || r).join(', ') || u.email }})
                </option>
              </select>
            </div>

            <div>
              <label class="block text-xs font-semibold uppercase tracking-wider text-[var(--color-muted)] mb-1">
                Order Notes (Optional)
              </label>
              <textarea
                v-model="notes"
                rows="3"
                placeholder="Instructions or special handling requirements..."
                class="w-full rounded-lg border border-[var(--color-line)] bg-white px-3 py-2 text-sm text-[var(--color-ink)] focus:outline-none focus:ring-2 focus:ring-[var(--color-evergreen)]"
              ></textarea>
            </div>
          </div>

          <!-- STEP 2: ADD ITEMS -->
          <div v-else-if="currentStep === 2" class="space-y-4">
            <div class="flex items-center justify-between">
              <span class="text-xs font-semibold uppercase tracking-wider text-[var(--color-muted)]">
                Selected Line Items ({{ lines.length }})
              </span>
              <button
                type="button"
                @click="addLine"
                class="inline-flex items-center gap-1.5 text-xs font-semibold text-[var(--color-evergreen)] hover:underline"
              >
                + Add Another Item
              </button>
            </div>

            <div class="space-y-3">
              <div
                v-for="(line, idx) in lines"
                :key="idx"
                class="p-4 rounded-xl border border-[var(--color-line)] bg-gray-50/50 flex flex-col sm:flex-row items-start sm:items-center gap-3"
              >
                <!-- Item Selector -->
                <div class="flex-1 w-full">
                  <label class="block text-[11px] font-semibold text-gray-500 mb-1">Catalog Item *</label>
                  <select
                    :value="line.item_id"
                    data-testid="item-select"
                    @change="onItemSelected(idx, $event.target.value)"
                    class="w-full rounded-lg border border-[var(--color-line)] bg-white px-3 py-1.5 text-sm text-[var(--color-ink)] focus:ring-2 focus:ring-[var(--color-evergreen)]"
                  >
                    <option value="" disabled>Select item from catalog...</option>
                    <option v-for="item in items" :key="item.id" :value="item.id">
                      {{ item.name }} (${{ Number(item.price).toFixed(2) }} | Stock: {{ item.stock }})
                    </option>
                  </select>
                </div>

                <!-- Quantity -->
                <div class="w-full sm:w-28">
                  <label class="block text-[11px] font-semibold text-gray-500 mb-1">Quantity *</label>
                  <input
                    v-model.number="line.quantity"
                    type="number"
                    min="1"
                    class="w-full rounded-lg border border-[var(--color-line)] bg-white px-3 py-1.5 text-sm text-[var(--color-ink)] text-center focus:ring-2 focus:ring-[var(--color-evergreen)]"
                  />
                </div>

                <!-- Unit Price -->
                <div class="w-full sm:w-28">
                  <label class="block text-[11px] font-semibold text-gray-500 mb-1">Unit Price ($)</label>
                  <div class="px-3 py-1.5 text-sm font-semibold text-gray-800 bg-gray-100 rounded-lg">
                    ${{ Number(line.unit_price || 0).toFixed(2) }}
                  </div>
                </div>

                <!-- Line Subtotal -->
                <div class="w-full sm:w-28">
                  <label class="block text-[11px] font-semibold text-gray-500 mb-1">Subtotal ($)</label>
                  <div class="px-3 py-1.5 text-sm font-bold text-[var(--color-evergreen)] bg-gray-100 rounded-lg">
                    ${{ (line.quantity * line.unit_price).toFixed(2) }}
                  </div>
                </div>

                <!-- Delete Action -->
                <div class="sm:pt-5">
                  <button
                    type="button"
                    @click="removeLine(idx)"
                    :disabled="lines.length === 1"
                    class="p-1.5 text-gray-400 hover:text-red-600 disabled:opacity-30 rounded hover:bg-gray-100"
                  >
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                    </svg>
                  </button>
                </div>
              </div>
            </div>
          </div>

          <!-- STEP 3: REVIEW & SUBMIT -->
          <div v-else-if="currentStep === 3" class="space-y-4">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 p-4 rounded-xl bg-gray-50 border border-[var(--color-line)] text-xs">
              <div>
                <span class="text-gray-500 block">Client:</span>
                <span class="font-bold text-gray-900 text-sm">{{ selectedClient?.name }}</span>
                <span class="text-gray-600 block">{{ selectedClient?.email }}</span>
              </div>
              <div>
                <span class="text-gray-500 block">Purchase Date & Priority:</span>
                <span class="font-semibold text-gray-900">{{ purchaseDate }}</span>
                <span class="ml-2 uppercase px-2 py-0.5 rounded text-[10px] font-bold bg-amber-100 text-amber-800">{{ priority }}</span>
              </div>
            </div>

            <!-- Items Table Review -->
            <div class="rounded-xl border border-[var(--color-line)] overflow-hidden">
              <table class="w-full text-left text-xs">
                <thead class="bg-gray-50 border-b border-[var(--color-line)] text-gray-500 uppercase">
                  <tr>
                    <th class="px-4 py-2 font-semibold">Item</th>
                    <th class="px-4 py-2 font-semibold text-center">Qty</th>
                    <th class="px-4 py-2 font-semibold text-right">Price</th>
                    <th class="px-4 py-2 font-semibold text-right">Total</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-[var(--color-line)]">
                  <tr v-for="(l, i) in lines" :key="i">
                    <td class="px-4 py-2.5 font-medium text-gray-900">{{ l.item?.name || 'Item #' + l.item_id }}</td>
                    <td class="px-4 py-2.5 text-center">{{ l.quantity }}</td>
                    <td class="px-4 py-2.5 text-right">${{ Number(l.unit_price).toFixed(2) }}</td>
                    <td class="px-4 py-2.5 text-right font-semibold">${{ (l.quantity * l.unit_price).toFixed(2) }}</td>
                  </tr>
                </tbody>
              </table>
            </div>

            <!-- Pricing Breakdown -->
            <div class="p-4 rounded-xl bg-emerald-50/50 border border-[var(--color-line)] text-sm space-y-1 text-right">
              <div class="text-xs text-gray-600">Subtotal: <span class="font-semibold text-gray-900">${{ subtotal.toFixed(2) }}</span></div>
              <div class="text-xs text-gray-600">Tax (10%): <span class="font-semibold text-gray-900">${{ tax.toFixed(2) }}</span></div>
              <div class="text-base font-bold text-[var(--color-evergreen)] pt-1 border-t border-emerald-200">
                Grand Total: ${{ total.toFixed(2) }}
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Action Footer -->
      <div class="px-6 py-4 border-t border-[var(--color-line)] bg-gray-50 flex items-center justify-between">
        <button
          type="button"
          v-if="currentStep > 1"
          @click="prevStep"
          class="rounded-lg border border-[var(--color-line)] bg-white px-4 py-2 text-xs font-semibold text-gray-700 hover:bg-gray-100"
        >
          Back
        </button>
        <div v-else></div>

        <div class="flex items-center gap-3">
          <button
            type="button"
            @click="emit('close')"
            class="rounded-lg border border-[var(--color-line)] bg-white px-4 py-2 text-xs font-semibold text-gray-700 hover:bg-gray-100"
          >
            Cancel
          </button>
          <button
            v-if="currentStep < steps.length"
            type="button"
            data-testid="stepper-continue-btn"
            @click="nextStep"
            class="rounded-lg bg-[var(--color-evergreen)] px-5 py-2 text-xs font-semibold text-white shadow-sm hover:opacity-90"
          >
            Continue to {{ steps[currentStep].name }}
          </button>
          <button
            v-else
            type="button"
            data-testid="stepper-submit-btn"
            :disabled="isSubmitting"
            @click="submitPurchase"
            class="rounded-lg bg-[var(--color-evergreen)] px-6 py-2 text-xs font-semibold text-white shadow-sm hover:opacity-90 disabled:opacity-50"
          >
            {{ isSubmitting ? 'Creating Purchase...' : 'Confirm & Create Purchase' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
