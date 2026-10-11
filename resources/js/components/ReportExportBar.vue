<script setup>
/**
 * Compact export control used by every report section.
 * Emits `export` with the chosen format (csv, xls, xlsx, pdf).
 */
defineProps({
  type: { type: String, required: true },
  label: { type: String, default: 'Export' },
  formats: { type: Array, default: () => ['csv', 'xls', 'xlsx', 'pdf'] },
  busy: { type: Boolean, default: false },
});

defineEmits(['export']);
</script>

<template>
  <div class="flex flex-wrap items-center gap-1.5">
    <span class="mr-1 text-[11px] font-semibold uppercase tracking-wider text-[var(--color-muted)]">{{ label }}</span>
    <button
      v-for="fmt in formats"
      :key="fmt"
      type="button"
      :disabled="busy"
      :data-testid="`report-export-${type}-${fmt}`"
      class="rounded-md border border-[var(--color-line)] bg-white px-2 py-1 text-[11px] font-semibold text-[var(--color-ink)] shadow-sm transition hover:border-[var(--color-evergreen)] hover:text-[var(--color-evergreen)] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--color-amber)] disabled:cursor-not-allowed disabled:opacity-50"
      @click="$emit('export', fmt)"
    >
      {{ fmt.toUpperCase() }}
    </button>
  </div>
</template>
