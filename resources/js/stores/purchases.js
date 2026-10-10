import { defineStore } from 'pinia';
import { ref } from 'vue';

export const usePurchasesStore = defineStore('purchases', () => {
  const selectedPurchaseId = ref(null);
  const isDetailDrawerOpen = ref(false);
  const isCreateStepperOpen = ref(false);

  const openPurchaseDetail = (id) => {
    selectedPurchaseId.value = id;
    isDetailDrawerOpen.value = true;
  };

  const closePurchaseDetail = () => {
    selectedPurchaseId.value = null;
    isDetailDrawerOpen.value = false;
  };

  const openCreateStepper = () => {
    isCreateStepperOpen.value = true;
  };

  const closeCreateStepper = () => {
    isCreateStepperOpen.value = false;
  };

  return {
    selectedPurchaseId,
    isDetailDrawerOpen,
    isCreateStepperOpen,
    openPurchaseDetail,
    closePurchaseDetail,
    openCreateStepper,
    closeCreateStepper
  };
});
