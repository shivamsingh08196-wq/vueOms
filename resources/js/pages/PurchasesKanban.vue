<script setup>
import { ref, computed, onMounted } from 'vue';
import { usePurchasesStore } from '../stores/purchases';
import SlideOverDrawer from '../components/SlideOverDrawer.vue';
import PurchaseDetail from '../components/PurchaseDetail.vue';
import PurchaseCreateStepper from '../components/PurchaseCreateStepper.vue';
import { storeToRefs } from 'pinia';
import api from '../api/client';

const store = usePurchasesStore();
const { isDetailDrawerOpen, isCreateStepperOpen, selectedPurchaseId } = storeToRefs(store);

const purchases = ref([]);
const isLoading = ref(true);

const fetchPurchases = async () => {
  isLoading.value = true;
  try {
    const data = await api.get('/api/purchases');
    purchases.value = Array.isArray(data) ? data : (data.data || []);
  } catch {
    // If empty or initial
    purchases.value = [];
  } finally {
    isLoading.value = false;
  }
};

const columns = computed(() => [
  {
    id: 'pending',
    name: 'Pending',
    purchases: purchases.value.filter(p => !p.status || p.status.toLowerCase() === 'pending' || p.status.toLowerCase() === 'new')
  },
  {
    id: 'in_progress',
    name: 'In Progress',
    purchases: purchases.value.filter(p => ['in progress', 'confirmed', 'processing', 'approved'].includes(p.status?.toLowerCase()))
  },
  {
    id: 'completed',
    name: 'Completed',
    purchases: purchases.value.filter(p => ['completed', 'shipped', 'ready'].includes(p.status?.toLowerCase()))
  }
]);

const handleStepperClose = () => {
  store.closeCreateStepper();
  fetchPurchases();
};

onMounted(() => {
  fetchPurchases();
});
</script>

<template>
  <div class="h-full flex flex-col">
    <div class="sm:flex sm:items-center sm:justify-between mb-8">
      <div class="sm:flex-auto">
        <h1 class="text-2xl font-bold text-[var(--color-ink)]">Purchases Kanban</h1>
        <p class="mt-1 text-sm text-[var(--color-muted)]">Manage and track purchases across stages.</p>
      </div>
      <div class="mt-4 sm:ml-16 sm:mt-0 sm:flex-none">
        <button
          type="button"
          @click="store.openCreateStepper"
          class="block rounded-md bg-[var(--color-evergreen)] px-4 py-2.5 text-center text-sm font-semibold text-white shadow-sm hover:bg-[var(--color-teal)] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--color-amber)] cursor-pointer"
        >
          Create Purchase
        </button>
      </div>
    </div>

    <!-- Loading State -->
    <div v-if="isLoading" class="p-12 text-center text-[var(--color-muted)] bg-white rounded-lg border border-[var(--color-line)]">
      <div class="inline-block animate-spin rounded-full h-8 w-8 border-4 border-t-[var(--color-evergreen)] border-gray-200 mb-3"></div>
      <p class="text-sm font-medium">Loading purchases...</p>
    </div>

    <!-- Kanban Board -->
    <div v-else class="flex-1 overflow-x-auto">
      <div class="inline-flex h-full items-start space-x-4 pb-4">
        <div v-for="col in columns" :key="col.id" class="w-80 flex-shrink-0 bg-gray-50 rounded-lg p-4 border border-[var(--color-line)] min-h-[500px]">
          <div class="flex items-center justify-between mb-3">
            <h3 class="text-sm font-semibold text-[var(--color-ink)]">{{ col.name }}</h3>
            <span class="text-xs bg-gray-200 text-gray-700 font-medium px-2 py-0.5 rounded-full">{{ col.purchases.length }}</span>
          </div>

          <div v-if="col.purchases.length === 0" class="p-6 text-center text-xs text-gray-400 border border-dashed border-gray-200 rounded">
            No purchases in this stage
          </div>

          <div v-else class="space-y-3">
            <div
              v-for="p in col.purchases"
              :key="p.id"
              @click="store.openPurchaseDetail(p.id)"
              class="bg-white p-4 rounded shadow-sm border border-[var(--color-line)] cursor-pointer hover:border-[var(--color-evergreen)] hover:shadow-md transition-all"
            >
              <div class="flex justify-between items-start mb-2">
                <span class="font-medium text-[var(--color-ink)] font-mono text-sm">#{{ p.purchase_number || p.id }}</span>
                <span class="text-sm font-bold text-[var(--color-evergreen)]">${{ Number(p.total_amount || p.total || 0).toFixed(2) }}</span>
              </div>
              <p class="text-sm text-[var(--color-muted)] font-medium">{{ p.client?.name || `Client #${p.client_id || 'N/A'}` }}</p>
              <div class="mt-2 text-xs text-gray-400">
                {{ p.purchase_date || new Date().toISOString().slice(0, 10) }}
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Modals / Drawers -->
    <SlideOverDrawer v-model="isDetailDrawerOpen" title="Purchase Details">
      <PurchaseDetail v-if="selectedPurchaseId" :purchaseId="selectedPurchaseId" />
    </SlideOverDrawer>

    <PurchaseCreateStepper v-if="isCreateStepperOpen" @close="handleStepperClose" />
  </div>
</template>
