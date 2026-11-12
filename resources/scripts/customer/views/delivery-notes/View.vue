<template>
  <div class="customer-delivery-notes-view-stub">
    <h1 class="text-2xl font-semibold mb-4">{{ $t('delivery-notes.view_delivery_note') }}</h1>
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
 * Customer portal: detalle de un albarán emitido. Plan 05 entra
 * esta pantalla como placeholder navegable. La implementación
 * completa (renglones, totales, link al PDF firmado, "Mark as
 * viewed") entra en un PR UI posterior.
 */
export default {
  name: 'CustomerDeliveryNoteView',
}
</script>
