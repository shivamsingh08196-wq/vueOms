<script setup>
import { ref, watch, computed, onMounted } from 'vue';
import api from '../api/client';
import { useAuthStore } from '../stores/auth';

const props = defineProps({
  purchaseId: {
    type: Number,
    required: true,
  }
});

const emit = defineEmits(['updated']);

const authStore = useAuthStore();
const canAssign = computed(() => authStore.isAdmin || authStore.isManager || authStore.can('orders.assign'));

const purchase = ref(null);
const isLoading = ref(true);
const errorMessage = ref('');

// Assign state
const users = ref([]);
const isLoadingUsers = ref(false);
const isEditingAssignee = ref(false);
const selectedAssigneeId = ref('');
const isSavingAssignee = ref(false);
const assignError = ref('');
const assignSuccessMessage = ref('');

const fetchUsers = async () => {
  if (!canAssign.value || users.value.length > 0) return;
  isLoadingUsers.value = true;
  try {
    const res = await api.get('/api/users?all=1');
    users.value = Array.isArray(res) ? res : (res.data || []);
  } catch (err) {
    console.error('Failed to load users for assignment:', err);
  } finally {
    isLoadingUsers.value = false;
  }
};

const fetchPurchase = async () => {
  if (!props.purchaseId) return;
  isLoading.value = true;
  errorMessage.value = '';
  assignSuccessMessage.value = '';
  assignError.value = '';
  try {
    const data = await api.get(`/api/purchases/${props.purchaseId}`);
    purchase.value = data.data || data;
    selectedAssigneeId.value = purchase.value.assigned_to ? String(purchase.value.assigned_to) : '';
    if (canAssign.value) {
      fetchUsers();
    }
  } catch (err) {
    errorMessage.value = err.message || 'Failed to load purchase details.';
  } finally {
    isLoading.value = false;
  }
};

const startEditingAssignee = () => {
  selectedAssigneeId.value = purchase.value.assigned_to ? String(purchase.value.assigned_to) : '';
  isEditingAssignee.value = true;
  assignError.value = '';
  assignSuccessMessage.value = '';
  fetchUsers();
};

const cancelEditingAssignee = () => {
  isEditingAssignee.value = false;
  selectedAssigneeId.value = purchase.value.assigned_to ? String(purchase.value.assigned_to) : '';
  assignError.value = '';
};

const saveAssignment = async () => {
  isSavingAssignee.value = true;
  assignError.value = '';
  assignSuccessMessage.value = '';
  try {
    const res = await api.post(`/api/purchases/${props.purchaseId}/assign`, {
      assigned_to: selectedAssigneeId.value ? Number(selectedAssigneeId.value) : null,
    });
    if (res && res.data) {
      purchase.value = res.data;
    } else {
      await fetchPurchase();
    }
    assignSuccessMessage.value = 'Assignment updated successfully.';
    isEditingAssignee.value = false;
    emit('updated', purchase.value);
    setTimeout(() => {
      assignSuccessMessage.value = '';
    }, 4000);
  } catch (err) {
    assignError.value = err.message || 'Failed to update assignment.';
  } finally {
    isSavingAssignee.value = false;
  }
};

watch(() => props.purchaseId, () => {
  isEditingAssignee.value = false;
  fetchPurchase();
});

onMounted(() => {
  fetchPurchase();
});
</script>

<template>
  <div v-if="isLoading" class="text-center py-12 text-sm text-[var(--color-muted)]">
    <div class="animate-spin inline-block w-6 h-6 border-[3px] border-current border-t-transparent text-[var(--color-evergreen)] rounded-full mb-2"></div>
    <div>Loading purchase details...</div>
  </div>

  <div v-else-if="errorMessage" class="p-4 rounded-lg bg-red-50 text-xs text-red-600 border border-red-200">
    <p>{{ errorMessage }}</p>
    <button @click="fetchPurchase" class="mt-2 text-xs font-semibold underline text-[var(--color-evergreen)]">Retry</button>
  </div>

  <div v-else-if="purchase" class="space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
      <div>
        <h3 class="text-lg font-bold text-[var(--color-ink)]">
          {{ purchase.purchase_number || 'Purchase #' + purchase.id }}
        </h3>
        <p class="text-xs text-[var(--color-muted)]">Date: {{ purchase.purchase_date }}</p>
      </div>
      <div class="flex items-center gap-2">
        <span
          :class="[
            purchase.status === 'confirmed' ? 'bg-blue-100 text-blue-800' :
            purchase.status === 'completed' ? 'bg-emerald-100 text-emerald-800' :
            purchase.status === 'cancelled' ? 'bg-rose-100 text-rose-800' :
            'bg-amber-100 text-amber-800',
            'inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-bold uppercase'
          ]"
        >
          {{ purchase.status }}
        </span>
        <span
          v-if="purchase.priority"
          class="inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-semibold uppercase bg-gray-100 text-gray-700"
        >
          {{ purchase.priority }}
        </span>
      </div>
    </div>

    <!-- Client and Assignment Info -->
    <div class="border-t border-[var(--color-line)] pt-4">
      <dl class="divide-y divide-[var(--color-line)] text-xs">
        <div class="py-2.5 flex justify-between">
          <dt class="font-semibold text-gray-500">Client</dt>
          <dd class="font-medium text-gray-900">{{ purchase.client?.name || 'N/A' }}</dd>
        </div>
        <div class="py-2.5 flex justify-between">
          <dt class="font-semibold text-gray-500">Email</dt>
          <dd class="text-gray-700">{{ purchase.client?.email || 'N/A' }}</dd>
        </div>
        <!-- Assigned To -->
        <div class="py-2.5">
          <div class="flex justify-between items-center">
            <dt class="font-semibold text-gray-500">Assigned To</dt>
            <dd class="flex items-center gap-2">
              <span class="font-medium text-gray-900" data-testid="assignee-name">
                {{ purchase.assignee?.name || 'Unassigned' }}
              </span>
              <button
                v-if="canAssign && !isEditingAssignee"
                type="button"
                @click="startEditingAssignee"
                class="text-xs font-semibold text-[var(--color-evergreen)] hover:underline inline-flex items-center gap-1"
                data-testid="edit-assignee-btn"
              >
                <span>{{ purchase.assigned_to ? 'Change' : 'Assign' }}</span>
              </button>
            </dd>
          </div>

          <!-- Assignment Edit Form -->
          <div v-if="canAssign && isEditingAssignee" class="mt-2 p-2.5 bg-gray-50 rounded-lg border border-[var(--color-line)] space-y-2">
            <div v-if="assignError" class="text-[11px] text-red-600">
              {{ assignError }}
            </div>
            <div class="flex items-center gap-2">
              <select
                v-model="selectedAssigneeId"
                data-testid="assignee-select"
                class="flex-1 rounded-md border border-[var(--color-line)] bg-white px-2.5 py-1.5 text-xs text-[var(--color-ink)] focus:outline-none focus:ring-2 focus:ring-[var(--color-evergreen)]"
                :disabled="isSavingAssignee"
              >
                <option value="">Unassigned (None)</option>
                <option v-for="u in users" :key="u.id" :value="String(u.id)">
                  {{ u.name }} ({{ u.roles?.map(r => r.name || r).join(', ') || u.email }})
                </option>
              </select>
              <button
                type="button"
                data-testid="save-assignee-btn"
                :disabled="isSavingAssignee"
                @click="saveAssignment"
                class="rounded-md bg-[var(--color-evergreen)] px-2.5 py-1.5 text-xs font-semibold text-white hover:opacity-90 disabled:opacity-50"
              >
                {{ isSavingAssignee ? 'Saving...' : 'Save' }}
              </button>
              <button
                type="button"
                :disabled="isSavingAssignee"
                @click="cancelEditingAssignee"
                class="rounded-md border border-gray-300 bg-white px-2 py-1.5 text-xs font-medium text-gray-600 hover:bg-gray-100"
              >
                Cancel
              </button>
            </div>
          </div>

          <div v-if="assignSuccessMessage" class="mt-1 text-[11px] text-emerald-600 font-medium">
            ✓ {{ assignSuccessMessage }}
          </div>
        </div>
        <div class="py-2.5 flex justify-between">
          <dt class="font-semibold text-gray-500">Total Amount</dt>
          <dd class="font-bold text-base text-[var(--color-evergreen)]">
            ${{ Number(purchase.total_amount || 0).toFixed(2) }}
          </dd>
        </div>
        <div v-if="purchase.notes" class="py-2.5">
          <dt class="font-semibold text-gray-500 mb-1">Notes</dt>
          <dd class="text-gray-700 bg-gray-50 p-2 rounded">{{ purchase.notes }}</dd>
        </div>
      </dl>
    </div>

    <!-- Line items -->
    <div>
      <h4 class="text-xs font-semibold uppercase tracking-wider text-[var(--color-muted)] mb-2">
        Line Items ({{ purchase.lines?.length || 0 }})
      </h4>
      <div class="rounded-lg border border-[var(--color-line)] overflow-hidden">
        <table class="w-full text-left text-xs">
          <thead class="bg-gray-50 border-b border-[var(--color-line)] text-gray-500 uppercase">
            <tr>
              <th class="px-3 py-2 font-semibold">Item</th>
              <th class="px-3 py-2 font-semibold text-center">Qty</th>
              <th class="px-3 py-2 font-semibold text-right">Price</th>
              <th class="px-3 py-2 font-semibold text-right">Subtotal</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-[var(--color-line)]">
            <tr v-for="line in purchase.lines" :key="line.id">
              <td class="px-3 py-2 font-medium text-gray-900">{{ line.item?.name || 'Item #' + line.item_id }}</td>
              <td class="px-3 py-2 text-center">{{ line.quantity }}</td>
              <td class="px-3 py-2 text-right">${{ Number(line.unit_price).toFixed(2) }}</td>
              <td class="px-3 py-2 text-right font-semibold text-[var(--color-evergreen)]">
                ${{ Number(line.subtotal).toFixed(2) }}
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Status History Timeline -->
    <div v-if="purchase.status_logs?.length || purchase.statusLogs?.length">
      <h4 class="text-xs font-semibold uppercase tracking-wider text-[var(--color-muted)] mb-2">
        Status History
      </h4>
      <div class="space-y-2 border-l-2 border-[var(--color-evergreen)] pl-3">
        <div
          v-for="log in (purchase.status_logs || purchase.statusLogs)"
          :key="log.id"
          class="text-xs"
        >
          <div class="font-semibold text-gray-900">
            {{ log.old_status || 'Initial' }} &rarr; <span class="text-[var(--color-evergreen)] uppercase">{{ log.new_status }}</span>
          </div>
          <div class="text-[10px] text-gray-400 font-mono">
            {{ new Date(log.created_at).toLocaleString() }}
          </div>
          <div v-if="log.remarks" class="text-gray-600 italic mt-0.5">
            "{{ log.remarks }}"
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
