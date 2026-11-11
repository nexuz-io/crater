<template>
  <transition
    enter-active-class="transition duration-500 ease-in-out"
    enter-from-class="opacity-0"
    enter-to-class="opacity-100"
    leave-active-class="transition ease-in-out"
    leave-from-class="opacity-100"
    leave-to-class="opacity-0"
  >
    <div
      v-show="show"
      :id="id"
      :aria-label="resolvedAriaLabel"
      role="region"
      class="base-filter-wrapper relative z-10 p-4 md:p-8 bg-surface-secondary rounded-xl border border-line-light"
    >
      <header
        v-if="hasHeaderSlots"
        class="flex flex-wrap items-start justify-between gap-3 mb-4"
      >
        <div class="flex-1 min-w-0">
          <slot name="filter-header" />
        </div>
        <div class="flex flex-wrap items-center gap-2">
          <slot name="filter-actions" />
          <button
            v-if="showClearButton"
            type="button"
            class="
              inline-flex
              items-center
              text-sm
              leading-snug
              text-muted
              hover:text-heading
              px-1
              rounded-sm
              focus:outline-none
              focus-visible:ring-2 focus-visible:ring-primary-400
            "
            @click="$emit('clear')"
          >
            {{ $t('general.clear_all') }}
          </button>
        </div>
      </header>

      <div :class="containerClass">
        <slot />
      </div>
    </div>
  </transition>
</template>

<script setup>
import { computed, useSlots } from 'vue'

const props = defineProps({
  show: {
    type: Boolean,
    default: false,
  },
  rowOnXl: {
    type: Boolean,
    default: false,
  },
  cols: {
    type: [String, Number],
    default: 'auto',
    validator: (value) => {
      // Accepted shapes:
      //   'auto' (1/2/3 curve)
      //   'auto-N' or 'auto-N-M' (responsive presets with optional
      //     explicit lg column count)
      //   Numeric 'N' or 'N-M' for fixed column count(s)
      const str = String(value).trim()

      if (str === 'auto') return true
      if (/^auto-([1-9]|1[0-2])(?:-([1-9]|1[0-2]))?$/.test(str)) return true
      if (/^([1-9]|1[0-2])(?:-([1-9]|1[0-2]))?$/.test(str)) return true
      return false
    },
  },
  gap: {
    type: String,
    default: 'md',
    validator: (value) => ['none', 'sm', 'md', 'lg'].includes(value),
  },
  ariaLabel: {
    type: String,
    default: '',
  },
  showClearButton: {
    type: Boolean,
    default: true,
  },
  // Optional DOM id for the root element. Useful when the trigger
  // button outside the wrapper declares `aria-controls` pointing at
  // it: the id must land on the actual `<div>`, not on the
  // surrounding <transition>, so this prop is applied explicitly.
  id: {
    type: String,
    default: null,
  },
})

defineEmits(['clear'])

const slots = useSlots()

const hasHeaderSlots = computed(
  () => !!slots['filter-header'] || !!slots['filter-actions']
)

const resolvedAriaLabel = computed(() => props.ariaLabel || 'Filters')

const gapClass = computed(() => {
  switch (props.gap) {
    case 'none':
      return ''
    case 'sm':
      return 'gap-2'
    case 'lg':
      return 'gap-6'
    case 'md':
    default:
      return 'gap-4'
  }
})

// Container class hierarchy:
// - rowOnXl legacy: forced single row at xl (existing consumers).
// - cols='auto': 1 column -> 2 at md -> 3 at xl. Sensible default
//   for the majority of current listings.
// - cols='auto-N' or numeric N (1..12): adaptive responsive curve so
//   the grid takes advantage of every available breakpoint. Below
//   `xl` we never exceed the consumer-set lg column count; at `xl`
//   we hit the requested N. The 'auto-N-M' (or numeric 'N-M')
//   override lets callers tweak the lg density when controls would
//   otherwise feel cramped or wasted.
const containerClass = computed(() => {
  const base = ['grid', gapClass.value]

  if (props.rowOnXl) {
    base.push(
      'grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-1 xl:flex xl:flex-row xl:flex-nowrap xl:space-x-4 xl:space-y-0 xl:items-center'
    )
    return base.join(' ')
  }

  const cols = String(props.cols).trim()

  // 'auto' preserves the original responsive curve (1/2/3).
  if (cols === 'auto') {
    base.push('grid-cols-1 md:grid-cols-2 xl:grid-cols-3')
    return base.join(' ')
  }

  // Form 1: 'auto-N' or 'auto-N-M'.
  const autoMatch = /^auto-([1-9]|1[0-2])(?:-([1-9]|1[0-2]))?$/.exec(cols)
  if (autoMatch) {
    const xlCols = Number(autoMatch[1])
    const lgCols = autoMatch[2] ? Number(autoMatch[2]) : defaultLgColsFor(xlCols)
    base.push(...curveFor(xlCols, lgCols))
    return base.join(' ')
  }

  // Form 2: bare number N or N-M.
  const numericMatch = /^([1-9]|1[0-2])(?:-([1-9]|1[0-2]))?$/.exec(cols)
  if (numericMatch) {
    const xlCols = Number(numericMatch[1])
    const lgCols = numericMatch[2]
      ? Number(numericMatch[2])
      : defaultLgColsFor(xlCols)
    base.push(...curveFor(xlCols, lgCols))
    return base.join(' ')
  }

  // Fallback to auto curve if shape is unknown.
  base.push('grid-cols-1 md:grid-cols-2 xl:grid-cols-3')
  return base.join(' ')
})

// Sensible default for the lg breakpoint when callers didn't pass
// 'auto-N-M'. Goes progressive so small N values don't waste
// horizontal space on tablets while high N values do NOT immediately
// jump to skinny 6-column rows on small laptops.
function defaultLgColsFor(xlCols) {
  if (xlCols <= 3) return Math.min(3, xlCols)
  if (xlCols <= 5) return xlCols
  if (xlCols <= 7) return 4
  return 4
}

// Build the responsive class chain for a (xl, lg) pair. md always
// tops at 2 — labels and switches stay legible on tablets. The lg
// step is what adjusts when more horizontal real estate shows up
// but xl has not been reached (some setups with a wide sidebar
// never break out of lg in practice).
function curveFor(xlCols, lgCols) {
  const mdCols = xlCols <= 2 ? 2 : Math.min(2, xlCols)
  return [
    `grid-cols-1`,
    `md:grid-cols-${mdCols}`,
    `lg:grid-cols-${lgCols}`,
    `xl:grid-cols-${xlCols}`,
  ]
}
</script>

<!--
  Layout hook for span-aware fields. Consumers can pass
  `:span="2"` (or `'full'`) on `BaseInputGroup` to widen a single
  cell, which translates into the matching `grid-column` span
  below. Rules are scoped to `.base-filter-wrapper` so the cascade
  does not leak into other components.
-->
<style>
.base-filter-wrapper [data-span='2'] { grid-column: span 2 / span 2; }
.base-filter-wrapper [data-span='3'] { grid-column: span 3 / span 3; }
.base-filter-wrapper [data-span='full'] { grid-column: 1 / -1; }
</style>
