<template>
  <label
    :for="resolvedId"
    class="
      base-filter-search-bar
      group
      flex items-center
      h-11
      min-w-[14rem] sm:max-w-md
      w-full
      gap-2
      px-3
      bg-surface
      border border-line-light
      rounded-xl
      shadow-soft
      transition
      duration-150
      hover:border-line-default
      focus-within:ring-2
      focus-within:ring-primary-400
      focus-within:border-primary-300
    "
  >
    <span class="flex items-center justify-center text-muted shrink-0">
      <BaseIcon name="SearchIcon" class="h-5 w-5" />
    </span>

    <input
      :id="resolvedId"
      ref="input"
      v-bind="$attrs"
      :placeholder="placeholder"
      :type="type"
      :value="modelValue"
      :name="name"
      :autocomplete="autocomplete"
      class="
        flex-1
        bg-transparent
        border-0 outline-none ring-0
        text-sm text-heading placeholder:text-muted
        focus:outline-none focus:ring-0 focus:border-0
        disabled:text-muted disabled:cursor-not-allowed
      "
      @input="$emit('update:modelValue', $event.target.value)"
      @keydown.enter.prevent="$emit('submit')"
    />

    <button
      v-if="clearable && modelValue"
      type="button"
      class="
        inline-flex items-center justify-center
        h-7 w-7 rounded-md
        text-muted
        transition-colors duration-150
        hover:bg-surface-secondary hover:text-heading
        focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-400
      "
      :aria-label="clearLabel"
      @click="clearInput"
    >
      <BaseIcon name="XIcon" class="h-4 w-4" />
    </button>

    <span
      v-else-if="$slots.right"
      class="flex items-center justify-center shrink-0"
    >
      <slot name="right" />
    </span>
  </label>
</template>

<script setup>
import { computed, ref, useSlots } from 'vue'

// Cuadro de búsqueda usado por la barra persistente de filtros.
// Se renderiza como `<label>` envolviendo un input nativo para que
// cualquier click dentro active el input (mejor UX que envolver el
// input en un div clickeable, además permite usar `:for` para
// asociar con el id correspondiente — clave para accesibilidad).
//
// Atributos clave:
//   - modelValue (v-model) — string vinculado al estado reactivo
//     del consumer.
//   - placeholder — texto del input (ya localizado).
//   - clearable=true — muestra una X cuando hay valor, que emite
//     update:modelValue con string vacío.
//   - submit — emite cuando el usuario presiona Enter.
//   - right slot — spinner, atajo de teclado u otro adorno.
//
// Estilo:
//   - height: h-11 (44 px) — área de toque generosa en mobile y
//     equilibrio con badges/chips h-8 que viven al lado (chips son
//     auxiliares, search es la pieza principal).
//   - bg-surface sobre borde line-light con shadow-soft: combina
//     con el patrón de las cards del proyecto.
//   - focus-within:ring-primary-400 + border más marcado cuando
//     recibe foco: feedback inmediato sin color adicional al
//     estado neutral.
defineOptions({
  inheritAttrs: false,
})

const props = defineProps({
  id: {
    type: String,
    default: null,
  },
  modelValue: {
    type: [String, Number],
    default: '',
  },
  placeholder: {
    type: String,
    default: '',
  },
  name: {
    type: String,
    default: 'search',
  },
  type: {
    type: String,
    default: 'search',
  },
  autocomplete: {
    type: String,
    default: 'off',
  },
  clearable: {
    type: Boolean,
    default: true,
  },
  clearLabel: {
    type: String,
    default: 'Limpiar búsqueda',
  },
})

const emit = defineEmits(['update:modelValue', 'submit', 'clear'])

const slots = useSlots()
const input = ref(null)

const resolvedId = computed(() =>
  props.id || `search-${Math.random().toString(36).slice(2, 9)}`
)

function clearInput() {
  emit('update:modelValue', '')
  emit('clear')
  if (input.value) {
    input.value.focus()
  }
}

defineExpose({
  focus: () => input.value?.focus(),
  blur: () => input.value?.blur(),
})
</script>
