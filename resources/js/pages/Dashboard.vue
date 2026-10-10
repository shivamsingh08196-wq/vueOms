<script setup>
</script>

<template>
  <div>
    <h1 class="text-2xl font-bold text-[var(--color-ink)] mb-6">Dashboard</h1>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
      <div class="bg-white p-6 rounded-[var(--radius-card)] shadow-sm border border-[var(--color-line)]">
        <h3 class="text-lg font-medium text-[var(--color-muted)]">Total Purchases</h3>
        <p class="text-3xl font-bold text-[var(--color-evergreen)] mt-2">124</p>
      </div>
      <div class="bg-white p-6 rounded-[var(--radius-card)] shadow-sm border border-[var(--color-line)]">
        <h3 class="text-lg font-medium text-[var(--color-muted)]">Active Clients</h3>
        <p class="text-3xl font-bold text-[var(--color-evergreen)] mt-2">45</p>
      </div>
      <div class="bg-white p-6 rounded-[var(--radius-card)] shadow-sm border border-[var(--color-line)]">
        <h3 class="text-lg font-medium text-[var(--color-muted)]">Total Items</h3>
        <p class="text-3xl font-bold text-[var(--color-evergreen)] mt-2">89</p>
      </div>
    </div>
  </div>
</template>
