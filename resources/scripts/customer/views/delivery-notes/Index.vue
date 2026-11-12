<template>
  <div class="customer-delivery-notes-index-stub">
    <h1 class="text-2xl font-semibold mb-4">{{ $t('delivery-notes.title') }}</h1>
    <p class="text-sm text-gray-500">
      {{ $t('delivery-notes.list_of_delivery_notes') }}
    </p>
    <p class="mt-2 text-xs text-gray-400">
      Stub — implementación completa entra en un PR UI dedicado (plan 05 §8).
    </p>
  </div>
</template>

<script>
/**
 * Customer portal: list of delivery notes for the authenticated
 * customer. Plan 05 entra esta pantalla como placeholder navegable
 * para que el cliente pueda ver los albaranes que la empresa le
 * ha emitido. La implementación completa (lista filtrable por
 * estado, badge de DRAFT/DELIVERED/CANCELLED, link al PDF) entra
 * en un PR UI posterior.
 */
export default {
  name: 'CustomerDeliveryNoteIndex',
}
</script>
