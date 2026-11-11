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
        v-if="hasHeader"
        class="flex flex-wrap items-start justify-between gap-3 mb-4"
      >
        <div class="flex-1 min-w-0">
          <slot name="filter-header" />
        </div>
        <div class="flex flex-wrap items-center gap-2">
          <slot name="filter-actions" />
          <button
            v-if="shouldShowClear"
            type="button"
            class="
              inline-flex items-center gap-1.5
              h-7 px-2
              text-xs font-medium
              text-muted
              bg-surface
              border border-line-light
              rounded-md
              transition-colors duration-150
              hover:text-heading hover:bg-surface-tertiary hover:border-line-strong
              focus:outline-none
              focus-visible:ring-2 focus-visible:ring-primary-400 focus-visible:border-primary-300
            "
            @click="$emit('clear')"
          >
            <BaseIcon name="XIcon" class="h-3.5 w-3.5" />
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
      // Accepted shapes (single string, hyphen-separated):
      //   'auto' (1/2/3 curve)
      //   'auto-N' / 'N' (responsive: N is the XL column count;
      //     AUTO prefixes are dead-equal to the bare numeric form
      //     and exist only to keep callers self-documenting).
      //   'auto-N-M' / 'N-M' (responsive with explicit lg override).
      //   'auto-N-M-K' / 'N-M-K' (responsive with explicit 2xl
      //     override — K is only emitted as a 2xl rule when it
      //     differs from N so the existing two-number form keeps
      //     its byte-for-byte output).
      const str = String(value).trim()

      if (str === 'auto') return true
      if (
        /^auto-([1-9]|1[0-2])(?:-([1-9]|1[0-2]))?(?:-([1-9]|1[0-2]))?$/.test(
          str
        )
      ) {
        return true
      }
      if (
        /^([1-9]|1[0-2])(?:-([1-9]|1[0-2]))?(?:-([1-9]|1[0-2]))?$/.test(str)
      ) {
        return true
      }
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
  // Consumer-driven signal: when true, the panel's "Limpiar todo"
  // button (and the surrounding header chrome) becomes visible. By
  // default we treat the panel as inactive until the consumer
  // proves otherwise, so an empty listing does not render a
  // disabled-looking control in the middle of the panel.
  // Backward-compatible: existing consumers that don't pass this
  // prop get `false`, which means the wrapper continues to render
  // exactly as before (no header rendered when no slot is filled).
  hasActiveFilters: {
    type: Boolean,
    default: false,
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

// Header chrome now stays in sync with the consumer's reactivity:
// it renders when at least one source of content is present
// (header/filter-actions slot OR an active "Limpiar todo" button).
const hasHeader = computed(
  () => hasHeaderSlots.value || shouldShowClear.value
)

const shouldShowClear = computed(
  () => props.showClearButton && props.hasActiveFilters
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
// - cols='auto': 1 column -> 2 at sm -> 2 at md -> 3 at xl. Sensible
//   default for the majority of current listings.
// - cols='auto-N' or numeric N (1..12): adaptive responsive curve so
//   the grid takes advantage of every available breakpoint. The
//   curve is 1 col base, 2 cols sm/md, then progressively hits the
//   consumer-set lg/xl/2xl counts. Bringing 2 cols forward to `sm`
//   (640px) keeps the panel from sprawling on phablets and large
//   phones in landscape. Below `xl` we never exceed the consumer-set
//   lg column count; at `xl` we hit the requested N. The
//   'auto-N-M' (or numeric 'N-M') override lets callers tweak the lg
//   density when controls would otherwise feel cramped or wasted. The
//   'auto-N-M-K' form (or numeric 'N-M-K') adds an explicit 2xl
//   override on top of xl — this is what listings that pack many
//   controls under a wide monitor need (e.g. items with 9 filters:
//   6 cols at xl feels cramped, 8 cols at 2xl reads better).
const containerClass = computed(() => {
  const base = ['grid', gapClass.value]

  if (props.rowOnXl) {
    base.push(
      'grid-cols-1 sm:grid-cols-2 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-1 xl:flex xl:flex-row xl:flex-nowrap xl:space-x-4 xl:space-y-0 xl:items-center'
    )
    return base.join(' ')
  }

  const cols = String(props.cols).trim()

  // 'auto' preserves the original responsive curve (1/2/3), now
  // reinforced with the sm step so 2 cols kicks in at 640px instead
  // of 768px. The xl step is unchanged.
  if (cols === 'auto') {
    base.push('grid-cols-1 sm:grid-cols-2 md:grid-cols-2 xl:grid-cols-3')
    return base.join(' ')
  }

  // Form 1: 'auto-N(-M(-K))'. Capture 1 is xl; an optional capture 2
  // overrides lg; an optional capture 3 introduces a 2xl rule that
  // does not exist in the two-number form. This keeps the existing
  // emit byte-for-byte equivalent for callers that never request
  // 2xl — the curve below skips the 2xl class entirely unless the
  // third value is present.
  const autoMatch = /^auto-([1-9]|1[0-2])(?:-([1-9]|1[0-2]))?(?:-([1-9]|1[0-2]))?$/.exec(
    cols
  )
  if (autoMatch) {
    const xlCols = Number(autoMatch[1])
    const lgCols = autoMatch[2] ? Number(autoMatch[2]) : defaultLgColsFor(xlCols)
    const xl2Cols =
      autoMatch[3] !== undefined ? Number(autoMatch[3]) : undefined
    base.push(...curveFor(xlCols, lgCols, xl2Cols))
    return base.join(' ')
  }

  // Form 2: bare number N(-M(-K)). Same semantics as form 1.
  const numericMatch = /^([1-9]|1[0-2])(?:-([1-9]|1[0-2]))?(?:-([1-9]|1[0-2]))?$/.exec(
    cols
  )
  if (numericMatch) {
    const xlCols = Number(numericMatch[1])
    const lgCols = numericMatch[2]
      ? Number(numericMatch[2])
      : defaultLgColsFor(xlCols)
    const xl2Cols =
      numericMatch[3] !== undefined ? Number(numericMatch[3]) : undefined
    base.push(...curveFor(xlCols, lgCols, xl2Cols))
    return base.join(' ')
  }

  // Fallback to auto curve if shape is unknown.
  base.push('grid-cols-1 sm:grid-cols-2 md:grid-cols-2 xl:grid-cols-3')
  return base.join(' ')
})

// Sensible default for the lg breakpoint when callers didn't pass
// the explicit lg value. Goes progressive so small N values don't
// waste horizontal space on tablets while high N values do NOT
// immediately jump to skinny 6-column rows on small laptops.
function defaultLgColsFor(xlCols) {
  if (xlCols <= 3) return Math.min(3, xlCols)
  if (xlCols <= 5) return xlCols
  if (xlCols <= 7) return 4
  return 4
}

// Build the responsive class chain. The base line always goes
// single-column so the panel keeps its inputs full-width on the
// smallest phones — at ~320px the inner content area is ~288px
// and squeezing two controls there would crowd the multiselect
// placeholders. sm introduces 2 columns at the 640px threshold so
// phablets and large phones in landscape get the same compactness
// tablets enjoy, halving the panel height for listings with many
// filters. md always tops at 2 — labels and switches stay legible
// on tablets. The lg step is what adjusts when more horizontal
// real estate shows up but xl has not been reached (some setups
// with a wide sidebar never break out of lg in practice). When
// `xl2Cols` is provided, the chain gains an extra `2xl:grid-cols-*`
// rule — useful for catalogs that pack many controls and start
// feeling cramped at xl but have spare width on full HD+ monitors.
function curveFor(xlCols, lgCols, xl2Cols) {
  const mdCols = xlCols <= 2 ? 2 : Math.min(2, xlCols)
  const classes = [
    `grid-cols-1`,
    `sm:grid-cols-2`,
    `md:grid-cols-${mdCols}`,
    `lg:grid-cols-${lgCols}`,
    `xl:grid-cols-${xlCols}`,
  ]
  if (xl2Cols !== undefined) {
    classes.push(`2xl:grid-cols-${xl2Cols}`)
  }
  return classes
}
</script>

<!--
  Layout hook for span-aware fields. Consumers can pass
  `:span="2"` / `:span="3"` / `:span="4"` / `:span="'full'"` on
  `BaseInputGroup` to widen a single cell, which translates into the
  matching `grid-column` span below. Rules are scoped to
  `.base-filter-wrapper` so the cascade does not leak into other
  components.

  Span semantics:
  - `span="full"` is honoured at every breakpoint — the cell
    occupies the full row regardless of viewport.
  - `span="2"` / `span="3"` / `span="4"` only apply at `lg+`
    (≥ 1024 px). Below that breakpoint the rule does not exist,
    so the cell falls back to its natural `auto` placement (one
    grid column). This is intentional: requesting `grid-column:
    span N` in a grid that has fewer than `N` explicit tracks
    makes the browser create implicit phantom columns
    (CSS Grid L1 §8.5), which is exactly the regression that
    produced the historic "3-column mobile rendering" of the
    items filter panel. At < lg the wrapper template only
    declares 1–2 columns, so leaving the spans dormant is the
    safe default. Mobile-first filter sheets should compose
    `BaseFilterRow` instead.
-->
<style>
.base-filter-wrapper [data-span='full'] { grid-column: 1 / -1; }
@media (min-width: 1024px) {
  .base-filter-wrapper [data-span='2'] { grid-column: span 2 / span 2; }
  .base-filter-wrapper [data-span='3'] { grid-column: span 3 / span 3; }
  .base-filter-wrapper [data-span='4'] { grid-column: span 4 / span 4; }
}
</style>
