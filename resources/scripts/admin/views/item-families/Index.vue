<template>
  <BasePage>
    <BasePageHeader :title="$t('item_families.title')">
      <BaseBreadcrumb>
        <BaseBreadcrumbItem :title="$t('general.home')" to="dashboard" />
        <BaseBreadcrumbItem :title="$t('item_families.title')" to="#" active />
      </BaseBreadcrumb>

      <template #actions>
        <div class="flex items-center justify-end space-x-5">
          <BaseButton
            v-show="familyStore.totalFamilies"
            variant="primary-outline"
            :aria-expanded="showFilters ? 'true' : 'false'"
            aria-controls="item-family-filter-wrapper"
            @click="toggleFilter"
          >
            {{ $t('general.filter') }}
            <template #right="slotProps">
              <BaseIcon
                v-if="!showFilters"
                :class="slotProps.class"
                name="FilterIcon"
              />
              <BaseIcon v-else name="XIcon" :class="slotProps.class" />
            </template>
          </BaseButton>

          <BaseButton
            v-if="userStore.hasAbilities(abilities.CREATE_ITEM_FAMILY)"
            @click="onAddRoot"
          >
            <template #left="slotProps">
              <BaseIcon name="PlusIcon" :class="slotProps.class" />
            </template>
            {{ $t('item_families.add_root') }}
          </BaseButton>
        </div>
      </template>
    </BasePageHeader>

    <ItemFamilyModal />

    <BaseFilterWrapper
      id="item-family-filter-wrapper"
      :show="showFilters"
      class="mt-4"
      cols="auto"
      gap="md"
      aria-label="Item family filters"
      @clear="clearFilter"
    >
      <BaseInputGroup :label="$t('general.search')" :span="2">
        <BaseInput
          v-model="searchTerm"
          type="search"
          :placeholder="$t('item_families.search_placeholder')"
          autocomplete="off"
          @input="onSearchInput"
        />
      </BaseInputGroup>
    </BaseFilterWrapper>

    <div v-if="familyStore.isFetching" class="flex justify-center pt-6">
      <LoadingIcon class="h-6 w-6 text-primary-400" />
    </div>

    <BaseEmptyPlaceholder
      v-else-if="!familyStore.families.length"
      :title="$t('item_families.no_families')"
      :description="$t('item_families.list_of_families')"
    >
      <SatelliteIcon class="mt-5 mb-4" />
      <template #actions>
        <BaseButton
          v-if="userStore.hasAbilities(abilities.CREATE_ITEM_FAMILY)"
          variant="primary-outline"
          @click="onAddRoot"
        >
          <template #left="slotProps">
            <BaseIcon name="PlusIcon" :class="slotProps.class" />
          </template>
          {{ $t('item_families.add_first_root') }}
        </BaseButton>
      </template>
    </BaseEmptyPlaceholder>

    <div v-else class="bg-surface rounded-lg border border-line-light shadow-card mt-5">
      <ul class="px-2 py-2 space-y-0.5">
        <ItemFamilyTreeNode
          v-for="node in roots"
          :key="node.id"
          :node="node"
          :children-map="childrenMap"
          :parents-map="parentsMap"
          :active-id="activeId"
          :expanded-set="expandedSet"
          :full-path-set="fullPathSet"
          :can-create="userStore.hasAbilities(abilities.CREATE_ITEM_FAMILY)"
          :can-edit="userStore.hasAbilities(abilities.EDIT_ITEM_FAMILY)"
          :can-delete="userStore.hasAbilities(abilities.DELETE_ITEM_FAMILY)"
          @select="onSelect"
          @add-child="onAddChild"
          @rename="onRename"
          @move="onMove"
          @remove="onRemove"
          @toggle="onToggle"
        />
      </ul>
    </div>
  </BasePage>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { debouncedWatch } from '@vueuse/core'
import { useItemFamilyStore } from '@/scripts/admin/stores/item-family'
import { useUserStore } from '@/scripts/admin/stores/user'
import { useModalStore } from '@/scripts/stores/modal'
import { useNotificationStore } from '@/scripts/stores/notification'
import { useDialogStore } from '@/scripts/stores/dialog'
import ItemFamilyTreeNode from '@/scripts/admin/components/trees/ItemFamilyTreeNode.vue'
import ItemFamilyModal from '@/scripts/admin/components/modal-components/ItemFamilyModal.vue'
import SatelliteIcon from '@/scripts/components/icons/empty/SatelliteIcon.vue'
import abilities from '@/scripts/admin/stub/abilities'

const familyStore = useItemFamilyStore()
const userStore = useUserStore()
const modalStore = useModalStore()
const dialogStore = useDialogStore()
const notificationStore = useNotificationStore()
const { t } = useI18n()

const showFilters = ref(false)
const searchTerm = ref('')
const activeId = ref(null)

// Track which nodes are expanded. The first render opens every
// root so the structure is visible on first paint; users can
// collapse them individually. Newly-created subfamilies auto-expand
// their parent (see `refresh`) so the result of an additive action
// is actually visible — the most common "I added it but it didn't
// appear" complaint happens when the parent branch was still
// closed.
const expandedSet = ref(new Set())

function expandNode(id) {
  if (id == null) return
  expandedSet.value.add(id)
  expandedSet.value = new Set(expandedSet.value)
}

function collapseNode(id) {
  if (id == null) return
  expandedSet.value.delete(id)
  expandedSet.value = new Set(expandedSet.value)
}

// When a search filter narrows results, we open every ancestor of a
// match. `fullPathSet` highlights the matched nodes themselves while
// `expandedSet` keeps them visible.
const fullPathSet = ref(new Set())

const roots = computed(() =>
  familyStore.families.filter((f) => f.parent_id === null)
)

const childrenMap = computed(() => {
  const map = {}
  for (const family of familyStore.families) {
    const key = family.parent_id ?? '__root__'
    if (!map[key]) map[key] = []
    map[key].push(family)
  }
  return map
})

const parentsMap = computed(() => {
  const map = {}
  for (const family of familyStore.families) {
    map[family.id] = family
  }
  return map
})

// BFS to compute descendants of a single node — used to exclude
// self+descendants when changing parent.
function descendantIds(rootId) {
  const collected = new Set([rootId])
  const visited = new Set([rootId])
  let frontier = [rootId]
  while (frontier.length) {
    const children = childrenMap.value[frontier[0]] ?? []
    let next = []
    for (const child of children) {
      if (visited.has(child.id)) continue
      visited.add(child.id)
      collected.add(child.id)
      next.push(child.id)
    }
    frontier = frontier.slice(1).concat(next)
  }
  return collected
}

async function refresh(context = {}) {
  const previousFamilies = familyStore.families.slice()
  await familyStore.fetchFamilies()
  // Reset selection highlight, but keep user's expand-state since
  // it's just UI personal preference.
  activeId.value = null

  // Initial render: open every root so the operator sees the
  // structure on first paint; collapsing them is still possible from
  // the chevron. Newly created subfamilies auto-open the parent so
  // the new node is actually visible — the "I added it but it
  // didn't appear" complaint happens when the parent branch was
  // still closed. Move operations fold the OLD branch to avoid
  // double-counting the node under its previous parent.
  if (context.initial) {
    for (const family of familyStore.families) {
      if (family.parent_id === null) {
        expandNode(family.id)
      }
    }
  }

  if (context.expandAncestorsOf) {
    let cursor = context.expandAncestorsOf
    let depth = 0
    const maxDepth = 64
    while (cursor != null && depth < maxDepth) {
      expandNode(cursor)
      const family = familyStore.families.find((f) => f.id === cursor)
      cursor = family ? family.parent_id : null
      depth++
    }
  }

  if (context.collapseAncestorsOf) {
    let cursor = context.collapseAncestorsOf
    let depth = 0
    const maxDepth = 64
    while (cursor != null && depth < maxDepth) {
      collapseNode(cursor)
      const family = previousFamilies.find((f) => f.id === cursor)
      cursor = family ? family.parent_id : null
      depth++
    }
  }
}

function onToggle(node) {
  if (expandedSet.value.has(node.id)) {
    expandedSet.value.delete(node.id)
  } else {
    expandedSet.value.add(node.id)
  }
  // Trigger reactivity on Set
  expandedSet.value = new Set(expandedSet.value)
}

function onSelect(node) {
  activeId.value = node.id
  if (expandedSet.value.has(node.id) === false) {
    expandedSet.value.add(node.id)
    expandedSet.value = new Set(expandedSet.value)
  }
}

function onAddRoot() {
  familyStore.resetCurrentFamily()
  modalStore.openModal({
    title: t('item_families.add_root'),
    componentName: 'ItemFamilyModal',
    size: 'sm',
    refreshData: refresh,
  })
}

function onAddChild(parent) {
  familyStore.resetCurrentFamily()
  familyStore.currentFamily.parent_id = parent.id
  modalStore.openModal({
    title: t('item_families.add_child_of', { name: parent.name }),
    componentName: 'ItemFamilyModal',
    size: 'sm',
    refreshData: (created) => {
      // Captured by the modal handler and returned with `data`
      // wrapped at the resource level. We expand the parent so
      // the new node shows up immediately on the canvas.
      const data = created?.data?.data ?? created
      const parentId = data?.parent_id ?? parent.id
      refresh({ expandAncestorsOf: parentId })
    },
  })
}

function onRename(node) {
  familyStore.resetCurrentFamily()
  familyStore.currentFamily = {
    id: node.id,
    name: node.name,
    parent_id: node.parent_id,
  }
  modalStore.openModal({
    title: t('general.edit'),
    componentName: 'ItemFamilyModal',
    size: 'sm',
    refreshData: refresh,
  })
}

function onMove(node) {
  const previousParentId = node.parent_id
  familyStore.resetCurrentFamily()
  familyStore.currentFamily = {
    id: node.id,
    name: node.name,
    parent_id: node.parent_id,
  }
  modalStore.openModal({
    title: t('item_families.change_parent_of', { name: node.name }),
    componentName: 'ItemFamilyModal',
    size: 'sm',
    refreshData: (updated) => {
      const data = updated?.data?.data ?? updated
      const newParentId = data?.parent_id ?? node.parent_id
      refresh({
        expandAncestorsOf: newParentId,
        collapseAncestorsOf: previousParentId,
      })
    },
  })
}

function onRemove(node) {
  dialogStore
    .openDialog({
      title: t('general.are_you_sure'),
      message: t('item_families.confirm_delete', { name: node.name }),
      yesLabel: t('general.ok'),
      noLabel: t('general.cancel'),
      variant: 'danger',
      hideNoButton: false,
      size: 'lg',
    })
    .then(async (confirmed) => {
      if (!confirmed) return
      const response = await familyStore.deleteFamily(node.id)
      if (response.data?.success) {
        refresh()
      } else {
        notificationStore.showNotification({
          type: 'error',
          message: t('item_families.family_attached_message'),
        })
      }
    })
}

function toggleFilter() {
  showFilters.value = !showFilters.value
  if (!showFilters.value) {
    clearFilter()
  }
}

function clearFilter() {
  searchTerm.value = ''
  fullPathSet.value = new Set()
}

function onSearchInput() {
  // Open every parent in the chain of any match so the match stays
  // visible regardless of how deeply nested it is.
  const needle = searchTerm.value.trim().toLowerCase()
  if (!needle) {
    fullPathSet.value = new Set()
    return
  }

  const matches = new Set()
  for (const family of familyStore.families) {
    if (family.name && family.name.toLowerCase().includes(needle)) {
      matches.add(family.id)
    }
  }

  for (const id of matches) {
    let cursor = id
    let depth = 0
    const maxDepth = 128
    while (cursor != null && depth < maxDepth) {
      const node = parentsMap.value[cursor]
      if (!node) break
      expandedSet.value.add(node.id)
      cursor = node.parent_id
      depth++
    }
  }
  expandedSet.value = new Set(expandedSet.value)
  fullPathSet.value = matches
}

debouncedWatch(
  searchTerm,
  () => onSearchInput(),
  { debounce: 250 }
)

onMounted(() => {
  refresh({ initial: true })
})
</script>
