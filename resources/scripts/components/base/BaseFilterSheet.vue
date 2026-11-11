<template>
  <transition
    enter-active-class="transition duration-300 ease-out"
    enter-from-class="opacity-0 -translate-y-2"
    enter-to-class="opacity-100 translate-y-0"
    leave-active-class="transition duration-200 ease-in"
    leave-from-class="opacity-100 translate-y-0"
    leave-to-class="opacity-0 -translate-y-2"
  >
    <section
      v-show="show"
      :id="resolvedId"
      :aria-label="resolvedAriaLabel"
      role="region"
      class="
        base-filter-sheet
        relative z-10
        bg-surface-secondary
        rounded-xl
        border border-line-light
        p-4 sm:p-6
      "
    >
      <!-- Optional subheader (active filter chips, instructions,
           custom badges). Renders only when `sheet-subheader` slot is
           consumed. Mobile-first listings prefer to control the
           dismiss + visibility themselves via the parent's `v-show`
           rather than rely on an in-sheet collapse button. -->
      <div v-if="$slots['sheet-subheader']" class="mb-4">
        <slot name="sheet-subheader" />
      </div>

      <!-- Body: the row-based controls. The wrapper's own slot is
           available so consumers not using BaseFilterRow can still
           place arbitrary markup, but the canonical body shape for
           mobile-first filter sheets is `BaseFilterRow`. -->
      <div class="space-y-4">
        <slot />
      </div>

      <!-- Footer: action buttons. By default we render `Limpiar`
           (outline) and a primary confirm-close button. The full
           footer can be overridden via the `sheet-footer` slot for
           consumers that need a different CTA. -->
      <footer
        v-if="hasFooter"
        class="mt-6 pt-4 border-t border-line-light"
      >
        <slot name="sheet-footer">
          <div class="flex flex-col-reverse sm:flex-row gap-2 sm:gap-3">
            <BaseButton
              v-if="showClear"
              variant="primary-outline"
              class="flex-1"
              @click="$emit('clear')"
            >
              {{ clearLabel }}
            </BaseButton>
            <BaseButton
              v-if="showApply"
              variant="primary"
              class="flex-1"
              @click="onApply"
            >
              <template #left="slotProps">
                <BaseIcon name="CheckIcon" :class="slotProps.class" />
              </template>
              {{ applyLabel }}
            </BaseButton>
          </div>
        </slot>
      </footer>
    </section>
  </transition>
</template>

<script setup>
import { computed, useSlots } from 'vue'

// `BaseFilterSheet` is the mobile-first filter panel used inside
// `BaseFilterToolbar`. It is intentionally chrome-less:
//   - No title bar / collapse chevron — those are owned by the
//     toolbar (the trigger button outside); in-sheet controls
//     duplicate them without adding clarity.
//   - The body is just the slot — the consumer composes rows with
//     `BaseFilterRow`.
//   - The footer (Limpiar / Aplicar) is the only chrome inherited
//     from the earlier design; if a consumer needs no footer they
//     can opt out with `show-clear` / `show-apply` props or supply
//     their own via the `sheet-footer` slot.
//
// Visibility is driven from outside: the consumer toggles a
// `v-show`-bound Boolean and (optionally) listens to
// `update:show` to dismiss via the Aplicar button.

const props = defineProps({
  show: {
    type: Boolean,
    default: false,
  },
  // Optional. Reserved for future consumers — the listings we ship
  // omit it so the rendered sheet has no header chrome. If set,
  // exposed to `aria-label` as fallback when no `ariaLabel` prop
  // is provided.
  title: {
    type: String,
    default: '',
  },
  ariaLabel: {
    type: String,
    default: '',
  },
  id: {
    type: String,
    default: null,
  },
  showClear: {
    type: Boolean,
    default: true,
  },
  showApply: {
    type: Boolean,
    default: true,
  },
  clearLabel: {
    type: String,
    default: '',
  },
  applyLabel: {
    type: String,
    default: '',
  },
})

const emit = defineEmits(['update:show', 'clear', 'apply'])

const slots = useSlots()

const resolvedId = computed(() => props.id || null)

const resolvedAriaLabel = computed(
  () => props.ariaLabel || props.title || 'Filters'
)

const hasFooter = computed(
  () => !!slots['sheet-footer'] || props.showClear || props.showApply
)

function onApply() {
  // Auto-apply semantics: debouncedWatch already updated the table
  // by the time the user reaches the Aplicar button. Its only job
  // is to dismiss the sheet so the user lands back on the table.
  // Manual-apply consumers can override by listening to `apply`.
  emit('apply')
  emit('update:show', false)
}
</script>
