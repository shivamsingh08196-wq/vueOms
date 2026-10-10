<script setup>
import { useQuery } from '@tanstack/vue-query';
import { computed } from 'vue';

const props = defineProps({
  purchaseId: {
    type: Number,
    required: true,
  }
});

// Mock query
const { data: purchase, isLoading } = useQuery({
  queryKey: ['purchase', props.purchaseId],
  queryFn: async () => {
    // Fake API call
    return {
      id: props.purchaseId,
      status: 'Pending',
      client: 'Acme Corp',
      total: 1500.00,
      lines: [
        { id: 1, itemName: 'Server Rack', quantity: 1, price: 1000 },
        { id: 2, itemName: 'Switch', quantity: 2, price: 250 },
      ],
      comments: [
        { id: 1, text: 'Need this ASAP', author: 'John Doe', date: '2023-10-01' }
      ],
      logs: [
        { id: 1, action: 'Created', user: 'System', date: '2023-10-01' }
      ]
    };
  }
});
</script>

<template>
  <div v-if="isLoading" class="text-center py-10">Loading details...</div>
  <div v-else-if="purchase" class="space-y-6">
    <div>
      <h3 class="text-lg font-medium text-[var(--color-ink)]">Purchase #{{ purchase.id }}</h3>
      <span class="inline-flex items-center rounded-md bg-yellow-50 px-2 py-1 text-xs font-medium text-yellow-800 ring-1 ring-inset ring-yellow-600/20 mt-2">{{ purchase.status }}</span>
    </div>
    
    <div class="border-t border-[var(--color-line)] pt-4">
      <dl class="divide-y divide-[var(--color-line)]">
        <div class="px-4 py-2 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-0">
          <dt class="text-sm font-medium leading-6 text-[var(--color-ink)]">Client</dt>
          <dd class="mt-1 text-sm leading-6 text-[var(--color-muted)] sm:col-span-2 sm:mt-0">{{ purchase.client }}</dd>
        </div>
        <div class="px-4 py-2 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-0">
          <dt class="text-sm font-medium leading-6 text-[var(--color-ink)]">Total</dt>
          <dd class="mt-1 text-sm leading-6 text-[var(--color-muted)] sm:col-span-2 sm:mt-0">${{ purchase.total.toFixed(2) }}</dd>
        </div>
      </dl>
    </div>

    <div>
      <h4 class="text-md font-medium text-[var(--color-ink)] mb-2">Purchase Lines</h4>
      <ul class="divide-y divide-[var(--color-line)] border border-[var(--color-line)] rounded-md">
        <li v-for="line in purchase.lines" :key="line.id" class="flex justify-between px-4 py-3 text-sm">
          <span>{{ line.quantity }}x {{ line.itemName }}</span>
          <span class="text-[var(--color-muted)]">${{ line.price.toFixed(2) }}</span>
        </li>
      </ul>
    </div>

    <div>
      <h4 class="text-md font-medium text-[var(--color-ink)] mb-2">Comments</h4>
      <div v-for="comment in purchase.comments" :key="comment.id" class="bg-gray-50 p-3 rounded-md mb-2">
        <p class="text-sm text-[var(--color-ink)]">{{ comment.text }}</p>
        <p class="text-xs text-[var(--color-muted)] mt-1">{{ comment.author }} - {{ comment.date }}</p>
      </div>
    </div>
    
    <div>
      <h4 class="text-md font-medium text-[var(--color-ink)] mb-2">Audit Log</h4>
      <ul class="text-sm text-[var(--color-muted)] space-y-1">
        <li v-for="log in purchase.logs" :key="log.id">{{ log.date }}: {{ log.action }} by {{ log.user }}</li>
      </ul>
    </div>
  </div>
</template>
