<script setup>
import { ref } from 'vue';

const emit = defineEmits(['close']);
const currentStep = ref(1);
const steps = [
  { id: 1, name: 'Client Selection' },
  { id: 2, name: 'Add Items' },
  { id: 3, name: 'Review & Submit' }
];

const nextStep = () => {
  if (currentStep.value < steps.length) {
    currentStep.value++;
  } else {
    // submit
    emit('close');
  }
};

const prevStep = () => {
  if (currentStep.value > 1) currentStep.value--;
};
</script>

<template>
  <div class="fixed inset-0 z-50 overflow-hidden bg-white flex">
    <!-- Left Rail Stepper -->
    <div class="w-64 bg-gray-50 border-r border-[var(--color-line)] p-6">
      <h2 class="text-lg font-medium text-[var(--color-ink)] mb-8">Create Purchase</h2>
      <nav aria-label="Progress">
        <ol role="list" class="overflow-hidden">
          <li v-for="(step, stepIdx) in steps" :key="step.id" class="relative pb-10">
            <div v-if="stepIdx !== steps.length - 1" class="absolute left-4 top-4 -ml-px mt-0.5 h-full w-0.5" :class="step.id < currentStep ? 'bg-[var(--color-evergreen)]' : 'bg-gray-300'" aria-hidden="true"></div>
            <a href="#" class="relative flex items-start group">
              <span class="h-9 flex items-center">
                <span class="relative z-10 flex h-8 w-8 items-center justify-center rounded-full" :class="step.id <= currentStep ? 'bg-[var(--color-evergreen)] text-white' : 'border-2 border-gray-300 bg-white text-gray-500'">
                  {{ step.id }}
                </span>
              </span>
              <span class="ml-4 flex min-w-0 flex-col mt-1">
                <span class="text-sm font-medium" :class="step.id <= currentStep ? 'text-[var(--color-evergreen)]' : 'text-gray-500'">{{ step.name }}</span>
              </span>
            </a>
          </li>
        </ol>
      </nav>
    </div>

    <!-- Main Content -->
    <div class="flex-1 flex flex-col h-full overflow-y-auto">
      <div class="flex items-center justify-between px-8 py-4 border-b border-[var(--color-line)]">
        <h2 class="text-xl font-bold text-[var(--color-ink)]">{{ steps[currentStep - 1].name }}</h2>
        <button @click="emit('close')" class="text-gray-400 hover:text-gray-500">
          <span class="sr-only">Close</span>
          <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
          </svg>
        </button>
      </div>

      <div class="flex-1 p-8">
        <div v-if="currentStep === 1">
          <!-- Client Selection Form -->
          <label class="block text-sm font-medium text-[var(--color-ink)]">Select Client</label>
          <select class="mt-1 block w-full rounded-md border-gray-300 py-2 pl-3 pr-10 text-base focus:border-[var(--color-evergreen)] focus:outline-none focus:ring-[var(--color-evergreen)] sm:text-sm">
            <option>Acme Corp</option>
            <option>Globex</option>
          </select>
        </div>
        <div v-else-if="currentStep === 2">
          <!-- Item Selection -->
          <p class="text-gray-500">Item selection component goes here.</p>
        </div>
        <div v-else>
          <!-- Review -->
          <p class="text-gray-500">Review purchase details here.</p>
        </div>
      </div>

      <div class="px-8 py-4 border-t border-[var(--color-line)] flex justify-between bg-gray-50">
        <button v-if="currentStep > 1" @click="prevStep" class="rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50">Back</button>
        <div v-else></div>
        <button @click="nextStep" class="rounded-md bg-[var(--color-evergreen)] px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-[var(--color-teal)]">
          {{ currentStep === steps.length ? 'Submit' : 'Next' }}
        </button>
      </div>
    </div>
  </div>
</template>
