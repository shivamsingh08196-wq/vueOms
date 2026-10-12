import { defineStore } from 'pinia';
import { ref } from 'vue';
import api from '../api/client';

export const useNotificationsStore = defineStore('notifications', () => {
  const notifications = ref([]);
  const unreadCount = ref(0);
  const isLoading = ref(false);

  const fetchUnreadCount = async () => {
    try {
      const res = await api.get('/api/notifications/unread-count');
      unreadCount.value = res?.data?.count ?? 0;
    } catch {
      // Silently fail if not authenticated
    }
  };

  const fetchNotifications = async ({ unread = false } = {}) => {
    isLoading.value = true;
    try {
      const endpoint = unread ? '/api/notifications?unread=1' : '/api/notifications';
      const res = await api.get(endpoint);
      notifications.value = res?.data || [];
      await fetchUnreadCount();
    } catch (err) {
      console.error('Failed to load notifications:', err);
    } finally {
      isLoading.value = false;
    }
  };

  const markAsRead = async (id) => {
    try {
      await api.post(`/api/notifications/${id}/read`);
      const item = notifications.value.find((n) => n.id === id);
      if (item && !item.read_at) {
        item.read_at = new Date().toISOString();
        unreadCount.value = Math.max(0, unreadCount.value - 1);
      }
    } catch (err) {
      console.error('Failed to mark notification as read:', err);
    }
  };

  const markAllAsRead = async () => {
    try {
      await api.post('/api/notifications/read-all');
      notifications.value.forEach((n) => {
        if (!n.read_at) {
          n.read_at = new Date().toISOString();
        }
      });
      unreadCount.value = 0;
    } catch (err) {
      console.error('Failed to mark all notifications as read:', err);
    }
  };

  return {
    notifications,
    unreadCount,
    isLoading,
    fetchUnreadCount,
    fetchNotifications,
    markAsRead,
    markAllAsRead,
  };
});

