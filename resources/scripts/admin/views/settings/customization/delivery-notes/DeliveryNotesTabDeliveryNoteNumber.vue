<template>
  <NumberCustomizer
    type="delivery_note"
    :type-store="deliveryNoteStore"
    default-series="DN"
  />
</template>

<script setup>
import { useDeliveryNoteStore } from '@/scripts/admin/stores/delivery-note'
import NumberCustomizer from '../NumberCustomizer.vue'

/**
 * Plan 05/09 §F11 — gemelo de InvoicesTabInvoiceNumber,
 * EstimatesTabEstimateNumber y PaymentsTabPaymentNumber.
 *
 * El componente base (`NumberCustomizer.vue`) consume
 * `${type}_number_format` desde
 * `companyStore.selectedCompanySettings` y previsualiza el
 * resultado a través de `typeStore.getNextNumber({ key: type })`.
 *
 * Como `DeliveryNote::nextNumber()` ya está implementado a nivel
 * backend (PR-1 del merge de plan 09) y la action
 * `getNextNumber` del store usa
 * `/api/v1/next-number?key=delivery_note`, basta con pasar
 * `type="delivery_note"` + `default-series="DN"` para que el
 * configurador funcione sin cambios estructurales.
 */
const deliveryNoteStore = useDeliveryNoteStore()
</script>
