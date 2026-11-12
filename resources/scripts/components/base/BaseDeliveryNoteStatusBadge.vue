<template>
  <span :class="badgeColorClasses">
    <slot />
  </span>
</template>

<script setup>
import { computed } from 'vue'

/**
 * Plan 09 §F11 + plan 05 §1 — albarán admite 3 estados disjuntos
 * (matriz plan 06 §paso-5 §3 columna DeliveryNote): DRAFT
 * (pendiente de entrega), DELIVERED (entregado, periodo de gracia
 * para editar ya cerrado y para facturar abierto) y CANCELLED
 * (anulado, no facturable).
 *
 * La facturación NO es un estado: cuando existe `invoices.delivery_note_id`
 * el documento ya está facturado y eso se indica en el UI con la
 * etiqueta "Facturado" complementaria. Esta badge mantiene el
 * código de color coherente con el resto del admin.
 */
const props = defineProps({
  status: {
    type: String,
    required: false,
    default: '',
  },
})

const badgeColorClasses = computed(() => {
  switch (props.status) {
    case 'DRAFT':
      return 'px-2.5 py-1 rounded-full text-xs font-medium text-center text-status-yellow bg-status-yellow-bg uppercase'
    case 'DELIVERED':
      return 'px-2.5 py-1 rounded-full text-xs font-medium text-center text-status-green bg-status-green-bg uppercase'
    case 'CANCELLED':
      return 'px-2.5 py-1 rounded-full text-xs font-medium text-center text-status-red bg-status-red-bg uppercase'
    default:
      return 'px-2.5 py-1 rounded-full text-xs font-medium text-center text-status-gray bg-status-gray-bg uppercase'
  }
})
</script>
