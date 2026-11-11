<template>
  <div
    class="
      base-active-filters
      flex flex-wrap items-center gap-2
      px-3 py-2.5
      rounded-xl
      bg-surface-secondary
      border border-line-light
      shadow-soft
    "
    role="status"
    :aria-label="resolvedAriaLabel"
  >
    <span
      v-if="countLabel"
      class="
        inline-flex items-center justify-center
        h-6 px-2 mr-1
        rounded-md
        bg-primary-500 text-white
        text-xs font-semibold
      "
    >
      {{ countLabel }}
    </span>

    <div class="flex flex-wrap items-center gap-2">
      <BaseFilterChip
        v-for="chip in chips"
        :key="chip.key"
        :label="chip.label"
        :removable="chip.removable !== false"
        :remove-aria-label="chip.removeAriaLabel"
        @remove="$emit('remove', chip.key)"
      />
    </div>

    <button
      v-if="chips.length"
      type="button"
      class="
        ml-auto sm:ml-2
        inline-flex items-center gap-1.5
        h-8 px-2.5
        text-xs font-medium
        text-muted
        bg-surface
        border border-line-light
        rounded-lg
        transition-colors duration-150
        hover:text-heading hover:bg-surface-tertiary hover:border-line-strong
        focus:outline-none
        focus-visible:ring-2 focus-visible:ring-primary-400 focus-visible:border-primary-300
      "
      @click="$emit('clear')"
    >
      <BaseIcon name="XIcon" class="h-3.5 w-3.5" />
      <span>{{ clearLabel }}</span>
    </button>
  </div>
</template>

<script setup>
import { computed } from 'vue'

// Contenedor de chips de filtros activos. Renderiza un panel
// discreto cuando hay al menos un filtro aplicado, alineado con
// los tokens surface/line del proyecto (cards usan los mismos).
//
// Atributo countLabel:
//   - String: se renderiza como badge numérico (e.g. "3").
//   - Boolean false/null: oculta el badge.
//   - Por defecto null → el consumer decide si lo muestra.
//
// Estructura del wrapper:
//   ┌──────────────────────────────────────────────────────────┐
//   │ ┌──┐ ┌Chip─┐ ┌Chip─┐ ┌Chip─┐           ┌─────────────┐  │
//   │ │ 3│ │Name │ │Marca│ │Fam  │           │ Limpiar todo │  │
//   │ └──┘ └─────┘ └─────┘ └─────┘           └─────────────┘  │
//   └──────────────────────────────────────────────────────────┘
//
// El botón Limpiar vive al extremo derecho (ml-auto) sólo en
// viewports donde hay espacio (≥ sm); en móvil se mantiene alineado
// con el bloque de chips para no romper el flujo.
const props = defineProps({
  chips: {
    type: Array,
    required: true,
  },
  clearLabel: {
    type: String,
    default: '',
  },
  countLabel: {
    type: [String, Number, Boolean],
    default: null,
  },
  ariaLabel: {
    type: String,
    default: '',
  },
})

defineEmits(['remove', 'clear'])

const resolvedAriaLabel = computed(() => props.ariaLabel || 'Active filters')
</script>
