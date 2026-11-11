<template>
  <div class="base-filter-toolbar" :aria-label="ariaLabel">
    <!-- Slot: primary persistent controls (search bar, primary
         filters that must remain visible regardless of the panel's
         expanded state). The toolbar itself doesn't own the inputs —
         the consumer binds them to its reactive filter state and
         renders them inside this slot so it can compose them with
         the rest of its page-level layout (e.g. page header actions
         like "Add service" that stay at the top right). -->
    <div
      v-if="$slots['filter-primary']"
      class="flex flex-wrap items-center gap-2 sm:gap-3"
    >
      <slot name="filter-primary" />
    </div>

    <!-- Active filters chips. Reading from `chips` prop keeps the
         toolbar agnostic to the consumer's filter state shape;
         chips removal is delegated back to the consumer via the
         `remove` emit so the source of truth (filters reactive object)
         stays where the rest of the business logic lives. -->
    <BaseActiveFilters
      v-if="chips.length"
      class="mt-4"
      :chips="chips"
      :count-label="countLabel"
      :clear-label="clearAllLabel"
      @remove="$emit('remove', $event)"
      @clear="$emit('clear')"
    />

    <!-- Slot: advanced panel. Toggle visibility is the consumer's
         responsibility (via the `show` prop on BaseFilterWrapper /
         BaseFilterSheet); the toolbar only provides the mounting
         point and doesn't impose any panel chrome. -->
    <div v-if="$slots['filter-panel']" class="mt-3 sm:mt-4">
      <slot name="filter-panel" />
    </div>
  </div>
</template>

<script setup>
import { computed, useSlots } from 'vue'

const props = defineProps({
  // List of active filter chips. The consumer owns the formatting
  // and decides when a chip is shown (i.e. when the corresponding
  // filter has a meaningful non-empty value). Each chip shape:
  //
  //   { key, label, removable, removeAriaLabel }
  //
  //   key              — identifier emitted on `remove` so the
  //                      consumer can clear that filter field.
  //   label            — display text (already localised by the
  //                      consumer).
  //   removable        — when false the chip is rendered as a
  //                      static badge; useful for read-only
  //                      summaries.
  //   removeAriaLabel  — localised string used as the chip's X
  //                      button aria-label; falls back to chip
  //                      label when missing.
  chips: {
    type: Array,
    default: () => [],
  },
  countLabel: {
    type: [String, Number, Boolean],
    default: null,
  },
  clearAllLabel: {
    type: String,
    default: '',
  },
  ariaLabel: {
    type: String,
    default: 'Filters',
  },
})

defineEmits(['remove', 'clear'])

const slots = useSlots()

const hasAnySlot = computed(
  () =>
    !!slots['filter-primary'] ||
    !!slots['filter-panel'] ||
    !!slots['filter-actions']
)
</script>
