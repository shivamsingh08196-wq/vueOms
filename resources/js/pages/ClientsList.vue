<script setup>
</script>

<template>
  <div>
    <div class="sm:flex sm:items-center mb-8">
      <div class="sm:flex-auto">
        <h1 class="text-2xl font-bold text-[var(--color-ink)]">Clients</h1>
        <p class="mt-2 text-sm text-[var(--color-muted)]">A list of all clients in the system.</p>
      </div>
      <div class="mt-4 sm:ml-16 sm:mt-0 sm:flex-none">
        <button type="button" class="block rounded-md bg-[var(--color-evergreen)] px-3 py-2 text-center text-sm font-semibold text-white shadow-sm hover:bg-[var(--color-teal)] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--color-amber)]">Add Client</button>
      </div>
    </div>
    <div class="bg-white rounded-[var(--radius-card)] shadow-sm border border-[var(--color-line)] p-6 text-center text-gray-500">
      Client list table would go here.
    </div>
  </div>
</template>
