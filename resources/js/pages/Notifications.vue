<script setup>
import { ref, onMounted } from 'vue';
import { useNotificationsStore } from '../stores/notifications';
import { storeToRefs } from 'pinia';
import { usePurchasesStore } from '../stores/purchases';
import SlideOverDrawer from '../components/SlideOverDrawer.vue';
import PurchaseDetail from '../components/PurchaseDetail.vue';

const store = useNotificationsStore();
const purchasesStore = usePurchasesStore();
const { notifications, unreadCount, isLoading } = storeToRefs(store);

const onlyUnread = ref(false);

const load = () => {
  store.fetchNotifications({ unread: onlyUnread.value });
};

const handleToggleFilter = () => {
  onlyUnread.value = !onlyUnread.value;
  load();
};

const handleItemClick = async (item) => {
  if (!item.read_at) {
    await store.markAsRead(item.id);
  }
  if (item.data?.order_id) {
    purchasesStore.openPurchaseDetail(item.data.order_id);
  }
};

const handleMarkAll = async () => {
  await store.markAllAsRead();
  if (onlyUnread.value) {
    load();
  }
};

onMounted(() => {
  load();
});
</script>

<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
      <div>
        <h1 class="text-2xl font-bold tracking-tight text-[var(--color-ink)]" data-testid="page-title">
          Notifications
        </h1>
        <p class="text-sm text-[var(--color-muted)] mt-1">
          Stay updated on order assignments, status transitions, and inventory alerts.
        </p>
      </div>

      <div class="flex items-center gap-3">
        <button
          type="button"
          @click="handleToggleFilter"
          class="rounded-lg border border-[var(--color-line)] bg-white px-3 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-50"
        >
          {{ onlyUnread ? 'Show All' : 'Show Unread Only' }}
        </button>

        <button
          type="button"
          data-testid="notification-mark-all"
          @click="handleMarkAll"
          :disabled="unreadCount === 0"
          class="inline-flex items-center gap-1.5 rounded-lg bg-[var(--color-evergreen)] px-3 py-1.5 text-xs font-semibold text-white shadow-sm hover:opacity-90 disabled:opacity-40 disabled:cursor-not-allowed"
        >
          <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
          </svg>
          Mark all as read
        </button>
      </div>
    </div>

    <!-- Notification List -->
    <div class="rounded-xl border border-[var(--color-line)] bg-white shadow-sm overflow-hidden">
      <div v-if="isLoading" class="p-12 text-center text-sm text-[var(--color-muted)]">
        <div class="animate-spin inline-block w-6 h-6 border-[3px] border-current border-t-transparent text-[var(--color-evergreen)] rounded-full mb-2"></div>
        <div>Loading notifications...</div>
      </div>

      <div v-else-if="notifications.length === 0" class="p-12 text-center text-sm text-[var(--color-muted)]">
        No notifications found.
      </div>

      <div v-else class="divide-y divide-[var(--color-line)]">
        <div
          v-for="item in notifications"
          :key="item.id"
          data-testid="notification-item"
          @click="handleItemClick(item)"
          :class="[
            !item.read_at ? 'bg-amber-50/40 hover:bg-amber-50/70' : 'hover:bg-gray-50',
            'p-4 transition-colors cursor-pointer flex items-start justify-between gap-4'
          ]"
        >
          <div class="flex items-start gap-3">
            <!-- Icon based on type -->
            <div class="mt-0.5">
              <span
                v-if="item.data?.type === 'order_assigned'"
                class="inline-flex h-8 w-8 items-center justify-center rounded-full bg-teal-100 text-[var(--color-evergreen)] text-xs font-bold"
              >
                📋
              </span>
              <span
                v-else-if="item.data?.type === 'order_confirmed'"
                class="inline-flex h-8 w-8 items-center justify-center rounded-full bg-emerald-100 text-emerald-800 text-xs font-bold"
              >
                ✓
              </span>
              <span
                v-else-if="item.data?.type === 'order_cancelled'"
                class="inline-flex h-8 w-8 items-center justify-center rounded-full bg-rose-100 text-rose-800 text-xs font-bold"
              >
                ✕
              </span>
              <span
                v-else-if="item.data?.type === 'low_stock'"
                class="inline-flex h-8 w-8 items-center justify-center rounded-full bg-amber-100 text-amber-800 text-xs font-bold"
              >
                ⚠️
              </span>
              <span
                v-else
                class="inline-flex h-8 w-8 items-center justify-center rounded-full bg-gray-100 text-gray-700 text-xs font-bold"
              >
                🔔
              </span>
            </div>

            <div class="space-y-1">
              <div class="flex items-center gap-2">
                <span class="text-xs font-bold text-[var(--color-ink)]">
                  {{ item.data?.order_number ? '#' + item.data.order_number : (item.data?.type || 'Notification') }}
                </span>
                <span
                  v-if="!item.read_at"
                  class="inline-flex items-center rounded-full bg-[var(--color-evergreen)] px-1.5 py-0.2 text-[10px] font-semibold text-white"
                >
                  New
                </span>
              </div>
              <p class="text-sm text-gray-800 font-medium">
                {{ item.data?.message || 'New notification received' }}
              </p>
              <p class="text-xs text-[var(--color-muted)] font-mono">
                {{ new Date(item.created_at).toLocaleString() }}
              </p>
            </div>
          </div>

          <div class="flex items-center gap-2" @click.stop>
            <button
              v-if="!item.read_at"
              type="button"
              @click="store.markAsRead(item.id)"
              class="text-xs text-[var(--color-muted)] hover:text-[var(--color-evergreen)] underline font-medium"
            >
              Mark read
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Purchase Drawer if notification links to an order -->
    <SlideOverDrawer v-model="purchasesStore.isDetailDrawerOpen" title="Purchase Details">
      <PurchaseDetail
        v-if="purchasesStore.selectedPurchaseId"
        :purchaseId="purchasesStore.selectedPurchaseId"
      />
    </SlideOverDrawer>
  </div>
</template>

