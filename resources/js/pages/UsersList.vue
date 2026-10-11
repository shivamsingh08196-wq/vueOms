<script setup>
import { ref, computed, onMounted } from 'vue';
import api from '../api/client';
import { useAuthStore } from '../stores/auth';
import SlideOverDrawer from '../components/SlideOverDrawer.vue';

const auth = useAuthStore();
const activeTab = ref('users'); // 'users' or 'roles'

// Users State
const users = ref([]);
const isLoadingUsers = ref(true);
const userError = ref('');
const searchQuery = ref('');

// User Drawer State
const isUserDrawerOpen = ref(false);
const editingUser = ref(null);
const isSavingUser = ref(false);
const userFormError = ref('');
const userForm = ref({
  name: '',
  email: '',
  password: '',
  role: 'staff',
});

// Roles State
const roles = ref([]);
const allPermissions = ref([]);
const isLoadingRoles = ref(false);
const roleError = ref('');
const editingRole = ref(null);
const selectedPermissions = ref([]);
const isSavingRole = ref(false);

const filteredUsers = computed(() => {
  if (!searchQuery.value.trim()) return users.value;
  const q = searchQuery.value.toLowerCase();
  return users.value.filter(u =>
    u.name?.toLowerCase().includes(q) ||
    u.email?.toLowerCase().includes(q) ||
    u.roles?.some(r => r.name.toLowerCase().includes(q))
  );
});

const fetchUsers = async () => {
  isLoadingUsers.value = true;
  userError.value = '';
  try {
    const data = await api.get('/api/users');
    users.value = Array.isArray(data) ? data : (data.data || []);
  } catch (err) {
    userError.value = err.message || 'Failed to load users.';
  } finally {
    isLoadingUsers.value = false;
  }
};

const fetchRoles = async () => {
  isLoadingRoles.value = true;
  roleError.value = '';
  try {
    const data = await api.get('/api/roles');
    roles.value = data.roles || [];
    allPermissions.value = data.all_permissions || [];
  } catch (err) {
    roleError.value = err.message || 'Failed to load roles.';
  } finally {
    isLoadingRoles.value = false;
  }
};

const openCreateUserDrawer = () => {
  editingUser.value = null;
  userFormError.value = '';
  userForm.value = {
    name: '',
    email: '',
    password: '',
    role: 'staff',
  };
  isUserDrawerOpen.value = true;
};

const openEditUserDrawer = (user) => {
  editingUser.value = user;
  userFormError.value = '';
  userForm.value = {
    name: user.name,
    email: user.email,
    password: '',
    role: user.roles?.[0]?.name || 'staff',
  };
  isUserDrawerOpen.value = true;
};

const closeUserDrawer = () => {
  isUserDrawerOpen.value = false;
  editingUser.value = null;
};

const saveUser = async () => {
  userFormError.value = '';
  if (!userForm.value.name.trim()) {
    userFormError.value = 'Name is required.';
    return;
  }
  if (!userForm.value.email.trim() || !userForm.value.email.includes('@')) {
    userFormError.value = 'A valid email is required.';
    return;
  }
  if (!editingUser.value && (!userForm.value.password || userForm.value.password.length < 6)) {
    userFormError.value = 'Password must be at least 6 characters.';
    return;
  }

  isSavingUser.value = true;
  try {
    const payload = {
      name: userForm.value.name.trim(),
      email: userForm.value.email.trim(),
      role: userForm.value.role,
    };
    if (userForm.value.password) {
      payload.password = userForm.value.password;
    }

    if (editingUser.value) {
      await api.put(`/api/users/${editingUser.value.id}`, payload);
    } else {
      await api.post('/api/users', payload);
    }
    await fetchUsers();
    closeUserDrawer();
  } catch (err) {
    userFormError.value = err.message || 'Failed to save user.';
  } finally {
    isSavingUser.value = false;
  }
};

const deleteUser = async (user) => {
  if (user.id === auth.user?.id) {
    alert('You cannot delete your own account.');
    return;
  }
  if (!confirm(`Are you sure you want to delete user "${user.name}"?`)) {
    return;
  }
  try {
    await api.delete(`/api/users/${user.id}`);
    await fetchUsers();
  } catch (err) {
    alert(err.message || 'Failed to delete user.');
  }
};

const selectRoleToEdit = (role) => {
  editingRole.value = role;
  selectedPermissions.value = role.permissions?.map(p => p.name) || [];
};

const togglePermission = (permName) => {
  const idx = selectedPermissions.value.indexOf(permName);
  if (idx > -1) {
    selectedPermissions.value.splice(idx, 1);
  } else {
    selectedPermissions.value.push(permName);
  }
};

const saveRolePermissions = async () => {
  if (!editingRole.value) return;
  isSavingRole.value = true;
  roleError.value = '';
  try {
    await api.put(`/api/roles/${editingRole.value.id}/permissions`, {
      permissions: selectedPermissions.value,
    });
    alert('Permissions updated successfully!');
    await fetchRoles();
  } catch (err) {
    roleError.value = err.message || 'Failed to update permissions.';
  } finally {
    isSavingRole.value = false;
  }
};

onMounted(() => {
  fetchUsers();
  fetchRoles();
});
</script>

<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
      <div>
        <h1 class="text-2xl font-bold tracking-tight text-[var(--color-ink)]">User & Role Management</h1>
        <p class="text-sm text-[var(--color-muted)] mt-1">Manage system operators, assign roles, and configure security permissions.</p>
      </div>
      <div v-if="activeTab === 'users'" class="flex items-center gap-3">
        <button
          @click="openCreateUserDrawer"
          data-testid="add-user-btn"
          class="inline-flex items-center gap-2 rounded-lg bg-[var(--color-evergreen)] px-4 py-2.5 text-sm font-medium text-white shadow-sm hover:opacity-95 transition-opacity"
        >
          <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
          </svg>
          Add User
        </button>
      </div>
    </div>

    <!-- Navigation Tabs -->
    <div class="border-b border-[var(--color-line)]">
      <nav class="flex space-x-8" aria-label="Tabs">
        <button
          @click="activeTab = 'users'"
          :class="[
            activeTab === 'users'
              ? 'border-[var(--color-evergreen)] text-[var(--color-evergreen)]'
              : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300',
            'whitespace-nowrap py-3 px-1 border-b-2 font-medium text-sm'
          ]"
        >
          Users List ({{ users.length }})
        </button>
        <button
          @click="activeTab = 'roles'"
          :class="[
            activeTab === 'roles'
              ? 'border-[var(--color-evergreen)] text-[var(--color-evergreen)]'
              : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300',
            'whitespace-nowrap py-3 px-1 border-b-2 font-medium text-sm'
          ]"
        >
          Roles & Permissions Matrix
        </button>
      </nav>
    </div>

    <!-- USERS TAB -->
    <div v-if="activeTab === 'users'" class="space-y-4">
      <!-- Search & Filters -->
      <div class="flex flex-col sm:flex-row gap-3">
        <div class="relative flex-1">
          <input
            v-model="searchQuery"
            type="text"
            data-testid="user-search-input"
            placeholder="Search by name, email, or role..."
            class="w-full rounded-lg border border-[var(--color-line)] bg-white px-4 py-2 text-sm text-[var(--color-ink)] placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-[var(--color-evergreen)]"
          />
        </div>
      </div>

      <!-- Users Table -->
      <div class="rounded-xl border border-[var(--color-line)] bg-white overflow-hidden shadow-sm">
        <div v-if="isLoadingUsers" class="p-8 text-center text-sm text-[var(--color-muted)]">
          <div class="animate-spin inline-block w-6 h-6 border-[3px] border-current border-t-transparent text-[var(--color-evergreen)] rounded-full mb-2"></div>
          <div>Loading users...</div>
        </div>

        <div v-else-if="userError" class="p-8 text-center text-sm text-red-600">
          <p>{{ userError }}</p>
          <button @click="fetchUsers" class="mt-2 text-sm font-medium underline text-[var(--color-evergreen)]">Retry</button>
        </div>

        <div v-else-if="filteredUsers.length === 0" class="p-8 text-center text-sm text-[var(--color-muted)]">
          No users found.
        </div>

        <div v-else class="overflow-x-auto">
          <table class="w-full text-left text-sm" data-testid="users-table">
            <thead class="bg-gray-50 border-b border-[var(--color-line)] text-xs uppercase text-[var(--color-muted)]">
              <tr>
                <th class="px-6 py-3 font-semibold">User</th>
                <th class="px-6 py-3 font-semibold">Email</th>
                <th class="px-6 py-3 font-semibold">Role</th>
                <th class="px-6 py-3 font-semibold">Created</th>
                <th class="px-6 py-3 font-semibold text-right">Actions</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-[var(--color-line)]">
              <tr v-for="user in filteredUsers" :key="user.id" class="hover:bg-gray-50 transition-colors">
                <td class="px-6 py-4 font-medium text-[var(--color-ink)]">
                  <div class="flex items-center gap-3">
                    <div class="h-8 w-8 rounded-full bg-emerald-100 text-[var(--color-evergreen)] flex items-center justify-center font-bold text-xs uppercase">
                      {{ user.name.charAt(0) }}
                    </div>
                    <span>{{ user.name }}</span>
                    <span v-if="user.id === auth.user?.id" class="ml-1 text-xs px-2 py-0.5 rounded bg-blue-100 text-blue-800 font-semibold">(You)</span>
                  </div>
                </td>
                <td class="px-6 py-4 text-[var(--color-muted)]">{{ user.email }}</td>
                <td class="px-6 py-4">
                  <span
                    v-for="role in user.roles"
                    :key="role.name"
                    :class="[
                      role.name === 'admin' ? 'bg-purple-100 text-purple-800' :
                      role.name === 'manager' ? 'bg-amber-100 text-amber-800' :
                      'bg-emerald-100 text-emerald-800',
                      'px-2.5 py-1 rounded-full text-xs font-semibold uppercase mr-1'
                    ]"
                  >
                    {{ role.name }}
                  </span>
                </td>
                <td class="px-6 py-4 text-[var(--color-muted)] text-xs">
                  {{ new Date(user.created_at).toLocaleDateString() }}
                </td>
                <td class="px-6 py-4 text-right space-x-2">
                  <button
                    @click="openEditUserDrawer(user)"
                    class="text-xs font-medium text-[var(--color-evergreen)] hover:underline"
                  >
                    Edit
                  </button>
                  <button
                    v-if="user.id !== auth.user?.id"
                    @click="deleteUser(user)"
                    class="text-xs font-medium text-red-600 hover:underline"
                  >
                    Delete
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- ROLES & PERMISSIONS TAB -->
    <div v-else class="space-y-6">
      <div v-if="isLoadingRoles" class="p-8 text-center text-sm text-[var(--color-muted)]">
        Loading roles and permissions...
      </div>
      <div v-else class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <!-- Roles List -->
        <div class="space-y-3">
          <h2 class="text-sm font-semibold uppercase tracking-wider text-[var(--color-muted)]">System Roles</h2>
          <div
            v-for="role in roles"
            :key="role.id"
            @click="selectRoleToEdit(role)"
            :class="[
              editingRole?.id === role.id ? 'border-[var(--color-evergreen)] ring-2 ring-[var(--color-evergreen)]/20 bg-emerald-50/30' : 'border-[var(--color-line)] bg-white hover:bg-gray-50',
              'p-4 rounded-xl border cursor-pointer transition-all'
            ]"
          >
            <div class="flex items-center justify-between">
              <span class="font-bold text-sm uppercase tracking-wide text-[var(--color-ink)]">{{ role.name }}</span>
              <span class="text-xs bg-gray-100 px-2 py-0.5 rounded text-gray-600 font-medium">{{ role.permissions?.length || 0 }} perms</span>
            </div>
            <p class="text-xs text-[var(--color-muted)] mt-1">
              {{ role.name === 'admin' ? 'Super administrator with full rights' : role.name === 'manager' ? 'Department operations and order assignments' : 'Operational staff with assigned orders' }}
            </p>
          </div>
        </div>

        <!-- Permissions Editor -->
        <div class="md:col-span-2 rounded-xl border border-[var(--color-line)] bg-white p-6 space-y-4">
          <div v-if="!editingRole" class="text-center py-12 text-sm text-[var(--color-muted)]">
            Select a role from the left to inspect and modify permissions.
          </div>
          <div v-else class="space-y-4">
            <div class="flex items-center justify-between border-b border-[var(--color-line)] pb-3">
              <div>
                <h3 class="text-base font-bold text-[var(--color-ink)] capitalize">{{ editingRole.name }} Permissions</h3>
                <p class="text-xs text-[var(--color-muted)]">Toggle permissions granted to the {{ editingRole.name }} role.</p>
              </div>
              <button
                @click="saveRolePermissions"
                :disabled="isSavingRole"
                class="rounded-lg bg-[var(--color-evergreen)] px-4 py-2 text-xs font-semibold text-white shadow-sm hover:opacity-90 disabled:opacity-50"
              >
                {{ isSavingRole ? 'Saving...' : 'Save Permissions' }}
              </button>
            </div>

            <div v-if="roleError" class="p-3 rounded-lg bg-red-50 border border-red-200 text-xs text-red-700">
              {{ roleError }}
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 max-h-[460px] overflow-y-auto pr-2">
              <label
                v-for="perm in allPermissions"
                :key="perm.id"
                class="flex items-center gap-3 p-3 rounded-lg border border-[var(--color-line)] hover:bg-gray-50 cursor-pointer"
              >
                <input
                  type="checkbox"
                  :checked="selectedPermissions.includes(perm.name)"
                  @change="togglePermission(perm.name)"
                  class="rounded border-gray-300 text-[var(--color-evergreen)] focus:ring-[var(--color-evergreen)]"
                />
                <div>
                  <span class="text-xs font-mono font-medium text-[var(--color-ink)]">{{ perm.name }}</span>
                </div>
              </label>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- User Add/Edit SlideOver Drawer -->
    <SlideOverDrawer
      v-model="isUserDrawerOpen"
      :title="editingUser ? 'Edit User' : 'Add New User'"
      @close="closeUserDrawer"
    >
      <form @submit.prevent="saveUser" class="space-y-4">
        <div v-if="userFormError" class="rounded-lg bg-red-50 p-3 text-xs text-red-600 border border-red-200">
          {{ userFormError }}
        </div>

        <div>
          <label class="block text-xs font-semibold uppercase tracking-wider text-[var(--color-muted)] mb-1">Full Name *</label>
          <input
            v-model="userForm.name"
            type="text"
            required
            placeholder="John Doe"
            class="w-full rounded-lg border border-[var(--color-line)] bg-white px-3 py-2 text-sm text-[var(--color-ink)] focus:outline-none focus:ring-2 focus:ring-[var(--color-evergreen)]"
          />
        </div>

        <div>
          <label class="block text-xs font-semibold uppercase tracking-wider text-[var(--color-muted)] mb-1">Email Address *</label>
          <input
            v-model="userForm.email"
            type="email"
            required
            placeholder="john@example.com"
            class="w-full rounded-lg border border-[var(--color-line)] bg-white px-3 py-2 text-sm text-[var(--color-ink)] focus:outline-none focus:ring-2 focus:ring-[var(--color-evergreen)]"
          />
        </div>

        <div>
          <label class="block text-xs font-semibold uppercase tracking-wider text-[var(--color-muted)] mb-1">
            {{ editingUser ? 'Password (leave blank to keep current)' : 'Password *' }}
          </label>
          <input
            v-model="userForm.password"
            type="password"
            :required="!editingUser"
            placeholder="Minimum 6 characters"
            class="w-full rounded-lg border border-[var(--color-line)] bg-white px-3 py-2 text-sm text-[var(--color-ink)] focus:outline-none focus:ring-2 focus:ring-[var(--color-evergreen)]"
          />
        </div>

        <div>
          <label class="block text-xs font-semibold uppercase tracking-wider text-[var(--color-muted)] mb-1">Assigned Role *</label>
          <select
            v-model="userForm.role"
            required
            class="w-full rounded-lg border border-[var(--color-line)] bg-white px-3 py-2 text-sm text-[var(--color-ink)] focus:outline-none focus:ring-2 focus:ring-[var(--color-evergreen)]"
          >
            <option value="admin">Admin (Full Access)</option>
            <option value="manager">Manager (Orders & Reports)</option>
            <option value="staff">Staff (Assigned Orders Only)</option>
          </select>
        </div>

        <div class="pt-4 flex items-center justify-end gap-3 border-t border-[var(--color-line)]">
          <button
            type="button"
            @click="closeUserDrawer"
            class="rounded-lg border border-[var(--color-line)] px-4 py-2 text-sm font-medium text-[var(--color-muted)] hover:bg-gray-50"
          >
            Cancel
          </button>
          <button
            type="submit"
            :disabled="isSavingUser"
            class="rounded-lg bg-[var(--color-evergreen)] px-4 py-2 text-sm font-medium text-white hover:opacity-90 disabled:opacity-50"
          >
            {{ isSavingUser ? 'Saving...' : (editingUser ? 'Save Changes' : 'Create User') }}
          </button>
        </div>
      </form>
    </SlideOverDrawer>
  </div>
</template>
