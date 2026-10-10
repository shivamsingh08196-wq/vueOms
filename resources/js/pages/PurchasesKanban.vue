<script setup>
import { usePurchasesStore } from '../stores/purchases';
import SlideOverDrawer from '../components/SlideOverDrawer.vue';
import PurchaseDetail from '../components/PurchaseDetail.vue';
import PurchaseCreateStepper from '../components/PurchaseCreateStepper.vue';
import { storeToRefs } from 'pinia';

const store = usePurchasesStore();
const { isDetailDrawerOpen, isCreateStepperOpen, selectedPurchaseId } = storeToRefs(store);

const columns = [
  { id: 'pending', name: 'Pending', purchases: [{ id: 1, client: 'Acme Corp', total: 1500 }, { id: 2, client: 'Globex', total: 450 }] },
  { id: 'approved', name: 'Approved', purchases: [{ id: 3, client: 'Initech', total: 2000 }] },
  { id: 'shipped', name: 'Shipped', purchases: [{ id: 4, client: 'Soylent', total: 150 }] }
];
</script>

<template>
  <div class="h-full flex flex-col">
    <div class="sm:flex sm:items-center mb-8">
      <div class="sm:flex-auto">
        <h1 class="text-2xl font-bold text-[var(--color-ink)]">Purchases Kanban</h1>
        <p class="mt-2 text-sm text-[var(--color-muted)]">Manage the flow of purchases.</p>
      </div>
      <div class="mt-4 sm:ml-16 sm:mt-0 sm:flex-none">
        <button @click="store.openCreateStepper" type="button" class="block rounded-md bg-[var(--color-evergreen)] px-3 py-2 text-center text-sm font-semibold text-white shadow-sm hover:bg-[var(--color-teal)] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--color-amber)]">Create Purchase</button>
      </div>
    </div>

    <!-- Kanban Board -->
    <div class="flex-1 overflow-x-auto">
      <div class="inline-flex h-full items-start space-x-4 pb-4">
        <div v-for="col in columns" :key="col.id" class="w-80 flex-shrink-0 bg-gray-50 rounded-lg p-4 border border-[var(--color-line)] min-h-[500px]">
          <h3 class="text-sm font-medium text-[var(--color-ink)] mb-3">{{ col.name }}</h3>
          <div class="space-y-3">
            <div 
              v-for="p in col.purchases" 
              :key="p.id" 
              @click="store.openPurchaseDetail(p.id)"
              class="bg-white p-4 rounded shadow-sm border border-[var(--color-line)] cursor-pointer hover:border-[var(--color-evergreen)] hover:shadow-md transition-all"
            >
              <div class="flex justify-between items-start mb-2">
                <span class="font-medium text-[var(--color-ink)]">#{{ p.id }}</span>
                <span class="text-sm font-medium text-[var(--color-evergreen)]">${{ p.total }}</span>
              </div>
              <p class="text-sm text-[var(--color-muted)]">{{ p.client }}</p>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Modals / Drawers -->
    <SlideOverDrawer v-model="isDetailDrawerOpen" :title="`Purchase Details`">
      <PurchaseDetail v-if="selectedPurchaseId" :purchaseId="selectedPurchaseId" />
    </SlideOverDrawer>

    <PurchaseCreateStepper v-if="isCreateStepperOpen" @close="store.closeCreateStepper" />
  </div>
</template>
