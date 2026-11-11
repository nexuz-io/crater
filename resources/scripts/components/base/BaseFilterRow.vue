<template>
  <div :class="rowClasses">
    <slot />
  </div>
</template>

<script setup>
import { computed } from 'vue'

// `BaseFilterRow` is the configurable row primitive that consumers
// compose inside `BaseFilterSheet` (mobile) or `BaseFilterWrapper`
// (desktop) to declare how many controls share a horizontal band.
//
// Two variants:
//   - 'full' — single control occupies the full row width.
//   - 'pair' — two controls share the row in a 2-col grid; switches
//              to a single column on the smallest phones (sub-640px)
//              where the Multiselect placeholders would otherwise be
//              truncated.
//
// The wrapper additionally exposes:
//   - `gap` — vertical/horizontal gap between cells.
//   - `align` — vertical alignment of the two cells inside a pair
//              (used when one cell is a switch and the other is text).
const props = defineProps({
  variant: {
    type: String,
    default: 'pair',
    validator: (v) => ['full', 'pair'].includes(v),
  },
  gap: {
    type: String,
    default: 'md',
    validator: (v) => ['none', 'sm', 'md', 'lg'].includes(v),
  },
  align: {
    type: String,
    default: 'stretch',
    validator: (v) =>
      ['stretch', 'start', 'center', 'end', 'baseline'].includes(v),
  },
})

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

const alignClass = computed(() => {
  if (props.align === 'start') return 'items-start'
  if (props.align === 'center') return 'items-center'
  if (props.align === 'end') return 'items-end'
  if (props.align === 'baseline') return 'items-baseline'
  return 'items-stretch'
})

// The pair variant stays single-column on phones narrower than 640px
// so Multiselect placeholders don't cramp; from `sm` upward the grid
// is 2 cols which matches the InvoiceShelf filter sheet pattern.
const rowClasses = computed(() => {
  const classes = ['grid', gapClass.value, alignClass.value]

  if (props.variant === 'full') {
    classes.push('grid-cols-1')
  } else {
    // pair
    classes.push('grid-cols-1 sm:grid-cols-2')
  }

  return classes
})
</script>
