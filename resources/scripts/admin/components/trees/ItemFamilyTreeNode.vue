<template>
  <li>
    <div
      :class="[
        'group flex items-center py-1.5 pr-2 rounded-md',
        highlight
          ? 'bg-primary-50'
          : isActive
          ? 'bg-gray-100'
          : 'hover:bg-gray-50',
      ]"
    >
      <button
        type="button"
        class="
          flex items-center justify-center w-6 h-6 text-gray-500
          focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-400 rounded
        "
        :aria-expanded="hasChildren ? isExpanded : undefined"
        :aria-label="
          hasChildren
            ? isExpanded
              ? $t('item_families.collapse_branch')
              : $t('item_families.expand_branch')
            : null
        "
        :tabindex="hasChildren ? 0 : -1"
        @click.stop="onToggle"
      >
        <BaseIcon
          v-if="hasChildren"
          :name="isExpanded ? 'ChevronDownIcon' : 'ChevronRightIcon'"
          class="h-4 w-4"
        />
        <span v-else class="block w-4" aria-hidden="true"></span>
      </button>

      <button
        type="button"
        class="
          flex-1 ml-1 text-left truncate text-sm
          focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-400 rounded
        "
        :title="fullPath"
        @click.stop="$emit('select', node)"
      >
        <span class="font-medium text-gray-900">{{ node.name }}</span>
        <span
          v-if="isRoot"
          class="ml-2 text-xs uppercase text-gray-400"
        >
          {{ $t('item_families.root_badge') }}
        </span>
      </button>

      <span
        v-if="hasChildren"
        class="ml-2 text-xs text-gray-400 shrink-0"
        :title="$t('item_families.children_count', { count: children.length })"
      >
        {{ children.length }}
      </span>

      <BaseDropdown v-if="canEdit || canDelete || canCreate" class="ml-2">
        <template #activator>
          <BaseButton
            type="button"
            size="sm"
            variant="gray"
            class="!px-1 !py-1"
            :title="$t('general.actions')"
          >
            <BaseIcon name="DotsHorizontalIcon" class="h-4 w-4" />
          </BaseButton>
        </template>
        <BaseDropdownItem
          v-if="canCreate"
          @click.stop.prevent="$emit('add-child', node)"
        >
          <BaseIcon
            name="PlusIcon"
            class="w-4 h-4 mr-3 text-gray-400 group-hover:text-gray-500"
          />
          {{ $t('item_families.add_child') }}
        </BaseDropdownItem>
        <BaseDropdownItem
          v-if="canEdit"
          @click.stop.prevent="$emit('rename', node)"
        >
          <BaseIcon
            name="PencilIcon"
            class="w-4 h-4 mr-3 text-gray-400 group-hover:text-gray-500"
          />
          {{ $t('general.edit') }}
        </BaseDropdownItem>
        <BaseDropdownItem
          v-if="canCreate && !isRoot"
          @click.stop.prevent="$emit('move', node)"
        >
          <BaseIcon
            name="ArrowsExpandIcon"
            class="w-4 h-4 mr-3 text-gray-400 group-hover:text-gray-500"
          />
          {{ $t('item_families.change_parent') }}
        </BaseDropdownItem>
        <BaseDropdownItem
          v-if="canDelete && isEmpty"
          @click.stop.prevent="$emit('remove', node)"
        >
          <BaseIcon
            name="TrashIcon"
            class="w-4 h-4 mr-3 text-gray-400 group-hover:text-gray-500"
          />
          {{ $t('general.delete') }}
        </BaseDropdownItem>
      </BaseDropdown>
    </div>

    <ul
      v-if="hasChildren && isExpanded"
      class="pl-6 mt-0.5 space-y-0.5 border-l border-gray-200 ml-3"
    >
      <ItemFamilyTreeNode
        v-for="child in children"
        :key="child.id"
        :node="child"
        :children-map="childrenMap"
        :parents-map="parentsMap"
        :active-id="activeId"
        :expanded-set="expandedSet"
        :full-path-set="fullPathSet"
        :can-create="canCreate"
        :can-edit="canEdit"
        :can-delete="canDelete"
        @select="$emit('select', $event)"
        @add-child="$emit('add-child', $event)"
        @rename="$emit('rename', $event)"
        @move="$emit('move', $event)"
        @remove="$emit('remove', $event)"
        @toggle="$emit('toggle', $event)"
      />
    </ul>
  </li>
</template>

<script setup>
import { computed } from 'vue'

// Recursive component: define name so Vue treats it as a registered
// component in the SFC tree (lets the template reference itself via
// <ItemFamilyTreeNode>).
defineOptions({
  name: 'ItemFamilyTreeNode',
})

const props = defineProps({
  node: { type: Object, required: true },
  childrenMap: { type: Object, required: true },
  parentsMap: { type: Object, default: () => ({}) },
  activeId: { type: [Number, null], default: null },
  expandedSet: { type: Set, required: true },
  fullPathSet: { type: Set, default: () => new Set() },
  canCreate: { type: Boolean, default: false },
  canEdit: { type: Boolean, default: false },
  canDelete: { type: Boolean, default: false },
})

const emit = defineEmits(['select', 'add-child', 'rename', 'move', 'remove', 'toggle'])

const children = computed(() => props.childrenMap[props.node.id] ?? [])
const hasChildren = computed(() => children.value.length > 0)
const isExpanded = computed(() => props.expandedSet.has(props.node.id))
const isActive = computed(() => props.activeId === props.node.id)
const isRoot = computed(() => props.node.parent_id === null)
const isEmpty = computed(() => !hasChildren.value)
const highlight = computed(() => props.fullPathSet.has(props.node.id))

const fullPath = computed(() => {
  if (!props.parentsMap || Object.keys(props.parentsMap).length === 0) {
    return props.node.name
  }
  const trail = []
  let cursor = props.node.id

  // Hard cap defends against deeply nested data; UI scrolls inside.
  let depth = 0
  const maxDepth = 128

  while (cursor != null && depth < maxDepth) {
    const node = props.parentsMap[cursor]
    if (!node) break
    trail.unshift(node.name)
    cursor = node.parent_id
    depth++
  }

  return trail.join(' / ')
})

function onToggle() {
  if (!hasChildren.value) return
  emit('toggle', props.node)
}
</script>
