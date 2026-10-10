<script setup>
import { ref, onMounted, onUnmounted } from 'vue';
import { useRouter } from 'vue-router';

const isOpen = ref(false);
const search = ref('');
const router = useRouter();

const handleKeydown = (e) => {
  if (e.key === 'k' && (e.ctrlKey || e.metaKey)) {
    e.preventDefault();
    isOpen.value = !isOpen.value;
    if (isOpen.value) {
      setTimeout(() => document.getElementById('command-input')?.focus(), 50);
    }
  }
  if (e.key === 'Escape' && isOpen.value) {
    isOpen.value = false;
  }
};

onMounted(() => {
  window.addEventListener('keydown', handleKeydown);
});

onUnmounted(() => {
  window.removeEventListener('keydown', handleKeydown);
});

const executeCommand = (path) => {
  isOpen.value = false;
  search.value = '';
  router.push(path);
};

// Simplified hardcoded commands for demonstration
const commands = [
  { name: 'Go to Dashboard', path: '/dashboard' },
  { name: 'View Purchases', path: '/purchases' },
  { name: 'View Clients', path: '/clients' },
  { name: 'View Items', path: '/items' },
];
</script>

<template>
  <Teleport to="body">
    <div v-if="isOpen" class="relative z-50" role="dialog" aria-modal="true">
      <div class="fixed inset-0 bg-gray-500 bg-opacity-25 transition-opacity" @click="isOpen = false"></div>

      <div class="fixed inset-0 z-10 overflow-y-auto p-4 sm:p-6 md:p-20">
        <div class="mx-auto max-w-xl transform divide-y divide-gray-100 overflow-hidden rounded-xl bg-white shadow-2xl ring-1 ring-black ring-opacity-5 transition-all">
          <div class="relative">
            <input
              id="command-input"
              v-model="search"
              type="text"
              class="h-12 w-full border-0 bg-transparent pl-11 pr-4 text-gray-900 placeholder:text-gray-400 focus:ring-0 sm:text-sm"
              placeholder="Search or jump to..."
            />
          </div>

          <ul v-if="commands.length > 0" class="max-h-72 scroll-py-2 overflow-y-auto py-2 text-sm text-gray-800" id="options" role="listbox">
            <li
              v-for="command in commands"
              :key="command.name"
              @click="executeCommand(command.path)"
              class="cursor-pointer select-none px-4 py-2 hover:bg-[var(--color-evergreen)] hover:text-white"
            >
              {{ command.name }}
            </li>
          </ul>
        </div>
      </div>
    </div>
  </Teleport>
</template>
