<template>
  <span
    class="
      base-filter-chip
      group inline-flex items-center
      h-8 pl-3 pr-1
      gap-1.5
      rounded-lg
      bg-primary-50
      text-primary-700
      border border-primary-100
      text-[13px] font-medium
      whitespace-nowrap
      transition-colors duration-150
      hover:bg-primary-100
    "
  >
    <span class="leading-none">{{ label }}</span>
    <button
      v-if="removable"
      type="button"
      class="
        -mr-0.5
        inline-flex items-center justify-center
        h-5 w-5 ml-0.5
        rounded-md
        text-primary-500
        transition-colors duration-150
        hover:bg-primary-200 hover:text-primary-800
        focus:outline-none
        focus-visible:bg-primary-200
        focus-visible:text-primary-800
        focus-visible:ring-2 focus-visible:ring-primary-400
      "
      :aria-label="removeLabel"
      @click="$emit('remove')"
    >
      <BaseIcon name="XIcon" class="h-3.5 w-3.5 stroke-current" />
    </button>
  </span>
</template>

<script setup>
import { computed } from 'vue'

// Chip de filtro activo. El listín visual:
//
//   ┌────────────────────────┐
//   │  Family                │
//   │   Motor /               × │  ← XIcon al final
//   └────────────────────────┘
//
// Atributo label: texto ya localizado por el consumer.
// removable=true: emite remove() al clickar la X.
// removable=false: chip sin botón (badge estático, sólo lectura).
//
// Estilo: rounded-lg en lugar de pill completo — más alineado con
// los tokens del proyecto (cards usan rounded-xl, dropdowns/items
// suelen usar rounded-lg). El hover sobre la X la oscurece
// indicando acción sin necesidad de tooltip. La transición dura
// 150ms para no quedar "nerviosa" al limpiar varios seguidos.

const props = defineProps({
  label: {
    type: String,
    required: true,
  },
  removable: {
    type: Boolean,
    default: true,
  },
  removeAriaLabel: {
    type: String,
    default: '',
  },
})

defineEmits(['remove'])

const removeLabel = computed(
  () => props.removeAriaLabel || `Quitar ${props.label}`
)
</script>
