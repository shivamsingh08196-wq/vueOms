<script setup>
import NavBar from '../components/NavBar.vue';
import CommandPalette from '../components/CommandPalette.vue';
</script>

<template>
  <div class="min-h-screen bg-[var(--color-paper)]">
    <NavBar />
    <CommandPalette />
    
    <main class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-8">
      <router-view />
    </main>
  </div>
</template>
