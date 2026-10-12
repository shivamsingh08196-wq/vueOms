<script setup>
import { ref, onMounted, onBeforeUnmount } from 'vue';
import { useAuthStore } from '../stores/auth';
import { useNotificationsStore } from '../stores/notifications';
import { usePurchasesStore } from '../stores/purchases';
import { useRouter } from 'vue-router';

const auth = useAuthStore();
const notificationsStore = useNotificationsStore();
const purchasesStore = usePurchasesStore();
const router = useRouter();

const isNotificationsOpen = ref(false);
const dropdownRef = ref(null);

const toggleNotifications = () => {
  isNotificationsOpen.value = !isNotificationsOpen.value;
  if (isNotificationsOpen.value) {
    notificationsStore.fetchNotifications();
  }
};

const handleNotificationClick = async (item) => {
  if (!item.read_at) {
    await notificationsStore.markAsRead(item.id);
  }
  isNotificationsOpen.value = false;
  if (item.data?.order_id) {
    purchasesStore.openPurchaseDetail(item.data.order_id);
  }
};

const handleMarkAllRead = async () => {
  await notificationsStore.markAllAsRead();
};

const handleClickOutside = (e) => {
  if (dropdownRef.value && !dropdownRef.value.contains(e.target)) {
    isNotificationsOpen.value = false;
  }
};

const logout = async () => {
  await auth.logout();
  await router.push('/login');
};

onMounted(() => {
  if (auth.isAuthenticated) {
    notificationsStore.fetchUnreadCount();
  }
  document.addEventListener('click', handleClickOutside);
});

onBeforeUnmount(() => {
  document.removeEventListener('click', handleClickOutside);
});
</script>

<template>
  <nav class="bg-white border-b border-[var(--color-line)] shadow-sm">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
      <div class="flex h-16 justify-between items-center">
        <div class="flex">
          <div class="flex flex-shrink-0 items-center">
            <span class="text-[var(--color-evergreen)] font-bold text-xl">vueVMS</span>
          </div>
          <div class="hidden sm:ml-6 sm:flex sm:space-x-6">
            <router-link to="/dashboard" class="inline-flex items-center border-b-2 border-transparent px-1 pt-1 text-sm font-medium text-[var(--color-muted)] hover:border-[var(--color-line)] hover:text-[var(--color-ink)]" active-class="border-[var(--color-evergreen)] text-[var(--color-ink)]">Dashboard</router-link>
            <router-link to="/purchases" class="inline-flex items-center border-b-2 border-transparent px-1 pt-1 text-sm font-medium text-[var(--color-muted)] hover:border-[var(--color-line)] hover:text-[var(--color-ink)]" active-class="border-[var(--color-evergreen)] text-[var(--color-ink)]">Purchases</router-link>
            <router-link to="/clients" class="inline-flex items-center border-b-2 border-transparent px-1 pt-1 text-sm font-medium text-[var(--color-muted)] hover:border-[var(--color-line)] hover:text-[var(--color-ink)]" active-class="border-[var(--color-evergreen)] text-[var(--color-ink)]">Clients</router-link>
            <router-link to="/items" class="inline-flex items-center border-b-2 border-transparent px-1 pt-1 text-sm font-medium text-[var(--color-muted)] hover:border-[var(--color-line)] hover:text-[var(--color-ink)]" active-class="border-[var(--color-evergreen)] text-[var(--color-ink)]">Items</router-link>
            <router-link to="/reports" v-if="auth.isAdmin || auth.isManager || auth.can('reports.view')" class="inline-flex items-center border-b-2 border-transparent px-1 pt-1 text-sm font-medium text-[var(--color-muted)] hover:border-[var(--color-line)] hover:text-[var(--color-ink)]" active-class="border-[var(--color-evergreen)] text-[var(--color-ink)]">Reports</router-link>
            <router-link to="/users" v-if="auth.isAdmin || auth.can('users.manage')" class="inline-flex items-center border-b-2 border-transparent px-1 pt-1 text-sm font-medium text-[var(--color-muted)] hover:border-[var(--color-line)] hover:text-[var(--color-ink)]" active-class="border-[var(--color-evergreen)] text-[var(--color-ink)]">Users</router-link>
            <router-link to="/audit-logs" v-if="auth.isAdmin || auth.can('audit.view')" class="inline-flex items-center border-b-2 border-transparent px-1 pt-1 text-sm font-medium text-[var(--color-muted)] hover:border-[var(--color-line)] hover:text-[var(--color-ink)]" active-class="border-[var(--color-evergreen)] text-[var(--color-ink)]">Audit Logs</router-link>
          </div>
        </div>
        <div class="flex items-center gap-4">
          <div class="text-sm text-[var(--color-muted)] hidden md:block">
            Press <kbd class="px-2 py-1 bg-gray-100 rounded text-xs">Ctrl</kbd> + <kbd class="px-2 py-1 bg-gray-100 rounded text-xs">K</kbd>
          </div>

          <!-- Notification Bell Dropdown -->
          <div v-if="auth.isAuthenticated" class="relative" ref="dropdownRef">
            <button
              type="button"
              data-testid="notification-bell"
              @click.stop="toggleNotifications"
              class="relative rounded-full p-1.5 text-gray-500 hover:text-gray-700 hover:bg-gray-100 transition-colors focus:outline-none"
              aria-label="Notifications"
            >
              <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
              </svg>
              <!-- Unread badge (absent when 0) -->
              <span
                v-if="notificationsStore.unreadCount > 0"
                data-testid="notification-badge"
                class="absolute -top-1 -right-1 flex h-4 min-w-4 items-center justify-center rounded-full bg-rose-500 px-1 text-[10px] font-bold text-white shadow-sm"
              >
                {{ notificationsStore.unreadCount > 99 ? '99+' : notificationsStore.unreadCount }}
              </span>
            </button>

            <!-- Dropdown Popover -->
            <div
              v-if="isNotificationsOpen"
              class="absolute right-0 mt-2 w-80 sm:w-96 rounded-xl bg-white shadow-2xl border border-[var(--color-line)] z-50 overflow-hidden"
            >
              <div class="flex items-center justify-between border-b border-[var(--color-line)] px-4 py-3 bg-gray-50">
                <div class="flex items-center gap-2">
                  <span class="font-bold text-xs uppercase tracking-wider text-[var(--color-ink)]">Notifications</span>
                  <span
                    v-if="notificationsStore.unreadCount > 0"
                    class="rounded-full bg-teal-100 px-1.5 py-0.2 text-[10px] font-bold text-[var(--color-evergreen)]"
                  >
                    {{ notificationsStore.unreadCount }} new
                  </span>
                </div>
                <button
                  type="button"
                  data-testid="notification-mark-all"
                  @click="handleMarkAllRead"
                  :disabled="notificationsStore.unreadCount === 0"
                  class="text-[11px] font-semibold text-[var(--color-evergreen)] hover:underline disabled:opacity-40 disabled:no-underline"
                >
                  Mark all as read
                </button>
              </div>

              <div class="max-h-80 overflow-y-auto divide-y divide-[var(--color-line)]">
                <div
                  v-if="notificationsStore.notifications.length === 0"
                  class="p-6 text-center text-xs text-[var(--color-muted)]"
                >
                  No notifications yet.
                </div>
                <div
                  v-for="item in notificationsStore.notifications"
                  :key="item.id"
                  data-testid="notification-item"
                  @click="handleNotificationClick(item)"
                  :class="[
                    !item.read_at ? 'bg-amber-50/50 hover:bg-amber-50' : 'hover:bg-gray-50',
                    'p-3 transition-colors cursor-pointer text-left'
                  ]"
                >
                  <div class="flex items-start justify-between gap-2">
                    <p class="text-xs font-semibold text-gray-900">
                      {{ item.data?.order_number ? '#' + item.data.order_number : (item.data?.type || 'Update') }}
                    </p>
                    <span class="text-[10px] text-gray-400 font-mono shrink-0">
                      {{ new Date(item.created_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) }}
                    </span>
                  </div>
                  <p class="text-xs text-gray-600 mt-0.5">
                    {{ item.data?.message || 'New notification' }}
                  </p>
                </div>
              </div>

              <div class="border-t border-[var(--color-line)] p-2 text-center bg-gray-50">
                <router-link
                  to="/notifications"
                  @click="isNotificationsOpen = false"
                  class="text-xs font-semibold text-[var(--color-evergreen)] hover:underline"
                >
                  View all notifications &rarr;
                </router-link>
              </div>
            </div>
          </div>

          <button @click="logout" v-if="auth.isAuthenticated" class="text-sm font-medium text-[var(--color-muted)] hover:text-[var(--color-ink)]">
            Log out
          </button>
        </div>
      </div>
    </div>
  </nav>
</template>
