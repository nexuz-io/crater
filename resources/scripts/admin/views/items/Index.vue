<template>
  <BasePage>
    <BasePageHeader :title="$t('items.title')">
      <BaseBreadcrumb>
        <BaseBreadcrumbItem :title="$t('general.home')" to="dashboard" />
        <BaseBreadcrumbItem :title="$t('items.services')" to="#" active />
      </BaseBreadcrumb>

      <template #actions>
        <div class="flex items-center justify-end space-x-5">
          <BaseButton
            v-show="itemStore.totalItems"
            variant="primary-outline"
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
            v-if="userStore.hasAbilities(abilities.VIEW_BRAND)"
            variant="primary-outline"
            @click="$router.push('/admin/brands')"
          >
            <template #left="slotProps">
              <BaseIcon name="TagIcon" :class="slotProps.class" />
            </template>
            {{ $t('brands.title') }}
          </BaseButton>

          <BaseButton
            v-if="userStore.hasAbilities(abilities.VIEW_ITEM_FAMILY)"
            variant="primary-outline"
            @click="$router.push('/admin/item-families')"
          >
            <template #left="slotProps">
              <BaseIcon name="PuzzleIcon" :class="slotProps.class" />
            </template>
            {{ $t('item_families.title') }}
          </BaseButton>

          <BaseButton
            v-if="userStore.hasAbilities(abilities.CREATE_ITEM)"
            @click="$router.push('/admin/items/create')"
          >
            <template #left="slotProps">
              <BaseIcon name="PlusIcon" :class="slotProps.class" />
            </template>
            {{ $t('items.add_service') }}
          </BaseButton>
        </div>
      </template>
    </BasePageHeader>

    <BaseFilterWrapper :show="showFilters" class="mt-5" @clear="clearFilter">
      <BaseInputGroup :label="$t('items.service_name')" class="text-left">
        <BaseInput
          v-model="filters.name"
          type="text"
          name="name"
          autocomplete="off"
        />
      </BaseInputGroup>

      <BaseInputGroup :label="$tc('items.unit')" class="text-left">
        <BaseMultiselect
          v-model="filters.unit_id"
          :placeholder="$t('items.select_a_unit')"
          value-prop="id"
          track-by="name"
          :filter-results="false"
          label="name"
          resolve-on-load
          :delay="500"
          searchable
          class="w-full"
          :options="searchUnits"
        />
      </BaseInputGroup>

      <BaseInputGroup class="text-left" :label="$t('items.unit_price')">
        <BaseMoney v-model="filters.price" />
      </BaseInputGroup>

      <BaseInputGroup class="text-left" :label="$t('items.service_status')">
        <BaseMultiselect
          v-model="filters.active"
          value-prop="value"
          track-by="label"
          label="label"
          :filter-results="false"
          :options="statusOptions"
          :placeholder="$t('items.all_statuses')"
          class="w-full"
        />
      </BaseInputGroup>

      <BaseInputGroup :label="$t('items.brand')" class="text-left">
        <BaseMultiselect
          v-model="filters.brand_id"
          :placeholder="$t('items.select_a_brand')"
          value-prop="id"
          track-by="name"
          :filter-results="false"
          :allow-empty="true"
          :clearable="true"
          label="name"
          resolve-on-load
          :delay="500"
          searchable
          class="w-full"
          :options="searchBrands"
          @update:model-value="onBrandFilterChange"
        />
      </BaseInputGroup>

      <BaseInputGroup class="text-left" :label="$t('brands.filter_without_brand')">
        <BaseSwitch
          :model-value="filters.without_brand"
          @update:model-value="toggleWithoutBrand"
        />
      </BaseInputGroup>

      <BaseInputGroup :label="$t('items.family')" class="text-left">
        <BaseMultiselect
          v-model="filters.family_id"
          :placeholder="$t('items.select_a_family')"
          value-prop="id"
          track-by="name"
          :filter-results="false"
          :allow-empty="true"
          :clearable="true"
          label="name"
          resolve-on-load
          :delay="500"
          searchable
          class="w-full"
          :options="searchFamilies"
          @update:model-value="onFamilyFilterChange"
        />
      </BaseInputGroup>

      <BaseInputGroup class="text-left" :label="$t('item_families.include_descendants')">
        <BaseSwitch
          v-show="filters.family_id"
          :model-value="filters.include_descendants"
          @update:model-value="toggleIncludeDescendants"
        />
      </BaseInputGroup>

      <BaseInputGroup class="text-left" :label="$t('item_families.without_family')">
        <BaseSwitch
          :model-value="filters.without_family"
          @update:model-value="toggleWithoutFamily"
        />
      </BaseInputGroup>
    </BaseFilterWrapper>

    <BaseEmptyPlaceholder
      v-show="showEmptyScreen"
      :title="$t('items.no_items')"
      :description="$t('items.list_of_items')"
    >
      <SatelliteIcon class="mt-5 mb-4" />

      <template #actions>
        <BaseButton
          v-if="userStore.hasAbilities(abilities.CREATE_ITEM)"
          variant="primary-outline"
          @click="$router.push('/admin/items/create')"
        >
          <template #left="slotProps">
            <BaseIcon name="PlusIcon" :class="slotProps.class" />
          </template>
          {{ $t('items.add_new_item') }}
        </BaseButton>
      </template>
    </BaseEmptyPlaceholder>

    <div v-show="!showEmptyScreen" class="relative table-container">
      <div
        class="
          relative
          flex
          items-center
          justify-end
          h-5
          border-gray-200 border-solid
        "
      >
        <BaseDropdown v-if="itemStore.selectedItems.length">
          <template #activator>
            <span
              class="
                flex
                text-sm
                font-medium
                cursor-pointer
                select-none
                text-primary-400
              "
            >
              {{ $t('general.actions') }}
              <BaseIcon name="ChevronDownIcon" />
            </span>
          </template>
          <BaseDropdownItem @click="removeMultipleItems">
            <BaseIcon name="TrashIcon" class="mr-3 text-gray-600" />
            {{ $t('general.delete') }}
          </BaseDropdownItem>
        </BaseDropdown>
      </div>

      <BaseTable
        ref="table"
        :data="fetchData"
        :columns="itemColumns"
        :placeholder-count="itemStore.totalItems >= 20 ? 10 : 5"
        class="mt-3"
      >
        <template #header>
          <div class="absolute items-center left-6 top-2.5 select-none">
            <BaseCheckbox
              v-model="itemStore.selectAllField"
              variant="primary"
              @change="itemStore.selectAllItems"
            />
          </div>
        </template>

        <template #cell-status="{ row }">
          <div class="relative block">
            <BaseCheckbox
              :id="row.id"
              v-model="selectField"
              :value="row.data.id"
            />
          </div>
        </template>

        <template #cell-name="{ row }">
          <router-link
            :to="{ path: `items/${row.data.id}/edit` }"
            class="font-medium text-primary-500"
          >
            {{ row.data.name }}
          </router-link>
        </template>

        <template #cell-is_active="{ row }">
          <BaseItemStatusBadge
            :status="row.data.is_active ? 'ACTIVE' : 'INACTIVE'"
            class="px-3 py-1"
          >
            {{ row.data.is_active ? $t('items.active') : $t('items.inactive') }}
          </BaseItemStatusBadge>
        </template>

        <template #cell-unit_name="{ row }">
          <span>
            {{ row.data.unit ? row.data.unit.name : '-' }}
          </span>
        </template>

        <template #cell-brand="{ row }">
          <span>
            {{ row.data.brand ? row.data.brand.name : '—' }}
          </span>
        </template>

        <template #cell-family="{ row }">
          <span :title="familyPathFor(row.data)">
            {{ familyPathFor(row.data) || '—' }}
          </span>
        </template>

        <template #cell-price="{ row }">
          <BaseFormatMoney
            :amount="row.data.price"
            :currency="companyStore.selectedCompanyCurrency"
          />
        </template>

        <template #cell-created_at="{ row }">
          <span>{{ row.data.formatted_created_at }}</span>
        </template>

        <template v-if="hasAbilities()" #cell-actions="{ row }">
          <ItemDropdown
            :row="row.data"
            :table="table"
            :load-data="refreshTable"
          />
        </template>
      </BaseTable>
    </div>
  </BasePage>
</template>

<script setup>
import { ref, computed, inject, onMounted, reactive, onUnmounted } from 'vue'
import { debouncedWatch } from '@vueuse/core'
import { useI18n } from 'vue-i18n'
import { useItemStore } from '@/scripts/admin/stores/item'
import { useNotificationStore } from '@/scripts/stores/notification'
import { useDialogStore } from '@/scripts/stores/dialog'
import { useCompanyStore } from '@/scripts/admin/stores/company'
import { useUserStore } from '@/scripts/admin/stores/user'
import ItemDropdown from '@/scripts/admin/components/dropdowns/ItemIndexDropdown.vue'
import SatelliteIcon from '@/scripts/components/icons/empty/SatelliteIcon.vue'
import abilities from '@/scripts/admin/stub/abilities'

const utils = inject('utils')

const itemStore = useItemStore()
const companyStore = useCompanyStore()
const notificationStore = useNotificationStore()
const dialogStore = useDialogStore()
const userStore = useUserStore()

const { t } = useI18n()
let showFilters = ref(false)
let isFetchingInitialData = ref(true)

const filters = reactive({
  name: '',
  unit_id: '',
  price: '',
  brand_id: '',
  active: '',
  without_brand: false,
  family_id: '',
  without_family: false,
  include_descendants: true,
})

const table = ref(null)

const statusOptions = [
  { value: 1, label: t('items.active') },
  { value: 0, label: t('items.inactive') },
]

const showEmptyScreen = computed(
  () => !itemStore.totalItems && !isFetchingInitialData.value
)

// Eagerly populate the family catalog so the table cell renders
// breadcrumb paths on first paint. The `searchFamilies` helper below
// reuses the same catalogue when the filter is interacted with.
if (userStore?.hasAbilities?.(abilities.VIEW_ITEM_FAMILY)) {
  itemStore.fetchItemFamilies({ limit: 'all' })
}

const selectField = computed({
  get: () => itemStore.selectedItems,
  set: (value) => {
    return itemStore.selectItem(value)
  },
})

const itemColumns = computed(() => {
  return [
    {
      key: 'status',
      thClass: 'extra w-10',
      tdClass: 'font-medium text-gray-900',
      placeholderClass: 'w-10',
      sortable: false,
    },
    {
      key: 'name',
      label: t('items.service_name'),
      thClass: 'extra',
      tdClass: 'font-medium text-gray-900',
    },
    {
      key: 'is_active',
      label: t('items.service_status'),
      thClass: 'extra',
      tdClass: 'font-medium text-gray-900',
    },
    { key: 'unit_name', label: t('items.unit') },
    {
      key: 'brand',
      label: t('items.brand'),
      tdClass: 'font-medium text-gray-900',
    },
    {
      key: 'family',
      label: t('items.family'),
      tdClass: 'font-medium text-gray-900',
    },
    { key: 'price', label: t('items.unit_price') },
    { key: 'created_at', label: t('items.added_on') },

    {
      key: 'actions',
      thClass: 'text-right',
      tdClass: 'text-right text-sm font-medium',
      sortable: false,
    },
  ]
})

debouncedWatch(
  filters,
  () => {
    setFilters()
  },
  { debounce: 500 }
)

itemStore.fetchItemUnits({ limit: 'all' })

onUnmounted(() => {
  if (itemStore.selectAllField) {
    itemStore.selectAllItems()
  }
})

function clearFilter() {
  filters.name = ''
  filters.unit_id = ''
  filters.price = ''
  filters.brand_id = ''
  filters.active = ''
  filters.without_brand = false
  filters.family_id = ''
  filters.without_family = false
  filters.include_descendants = true
}

function hasAbilities() {
  return userStore.hasAbilities([abilities.DELETE_ITEM, abilities.EDIT_ITEM])
}

function toggleFilter() {
  if (showFilters.value) {
    clearFilter()
  }

  showFilters.value = !showFilters.value
}

function refreshTable() {
  table.value && table.value.refresh()
}

function setFilters() {
  refreshTable()
}

async function searchUnits(search) {
  let res = await itemStore.fetchItemUnits({ search })

  return res.data.data
}

async function searchBrands(search) {
  let res = await itemStore.fetchItemBrands({ search })

  return res.data.data
}

// Without-brand and brand_id are mutually exclusive on both the
// controller and the request layer. Selecting one clears the other
// in the UI so the filter group doesn't accept contradictory values.
function onBrandFilterChange(value) {
  if (value) {
    filters.without_brand = false
  }
}

function toggleWithoutBrand(value) {
  filters.without_brand = value
  if (value) {
    filters.brand_id = ''
  }
}

// Family filter mirrors the brand filter contract: without_family
// and family_id are mutually exclusive; include_descendants only
// applies when a specific family is selected.
function onFamilyFilterChange(value) {
  if (value) {
    filters.without_family = false
  }
}

function toggleWithoutFamily(value) {
  filters.without_family = value
  if (value) {
    filters.family_id = ''
  }
}

function toggleIncludeDescendants(value) {
  filters.include_descendants = !!value
}

// Search the family catalog for the multiselect dropdown. The
// controller returns the flat list, so we decorate each entry's
// `name` with the breadcrumb path to disambiguate nodes that share
// the same text under different parents.
async function searchFamilies(search) {
  const res = await itemStore.fetchItemFamilies({
    search,
    limit: 'all',
  })

  const families = Array.isArray(res.data)
    ? res.data
    : res.data.data ?? []
  const byParent = new Map()
  families.forEach((f) => {
    const key = f.parent_id ?? '__root__'
    if (!byParent.has(key)) byParent.set(key, [])
    byParent.get(key).push(f)
  })

  const segmentsFor = new Map()
  segmentsFor.set('__root__', [])
  for (const key of [...byParent.keys()].sort((a, b) =>
    a === '__root__' ? -1 : b === '__root__' ? 1 : Number(a) - Number(b)
  )) {
    for (const node of byParent.get(key) ?? []) {
      const prefix = segmentsFor.get(key) ?? []
      segmentsFor.set(node.id, [...prefix, node.name])
    }
  }
  for (const family of families) {
    const segs = segmentsFor.get(family.id) ?? [family.name]
    if (segs.length > 1) {
      family.name = segs.join(' / ')
    }
  }

  return families
}

function familyPathFor(row) {
  if (!row.family) return ''
  const segments = []
  let cursor = row.family
  let depth = 0
  const maxDepth = 64

  while (cursor && depth < maxDepth) {
    segments.unshift(cursor.name)
    if (!cursor.parent_id) break
    cursor = familyCache.value[cursor.parent_id]
    depth++
  }

  return segments.join(' / ')
}

const familyCache = computed(() => {
  const map = {}
  for (const family of itemStore.itemFamilies ?? []) {
    map[family.id] = family
  }
  return map
})

async function fetchData({ page, filter, sort }) {
  let data = {
    search: filters.name,
    unit_id: filters.unit_id !== null ? filters.unit_id : '',
    price: Math.round(filters.price * 100),
    active: filters.active !== '' && filters.active !== null && filters.active !== undefined ? filters.active : '',
    brand_id: filters.brand_id !== null && filters.brand_id !== '' ? filters.brand_id : '',
    without_brand: filters.without_brand ? '1' : '',
    family_id: filters.family_id !== null && filters.family_id !== '' ? filters.family_id : '',
    without_family: filters.without_family ? '1' : '',
    include_descendants: filters.family_id && filters.without_brand === false && filters.without_family === false
      ? (filters.include_descendants ? '1' : '0')
      : '',
    orderByField: sort.fieldName || 'created_at',
    orderBy: sort.order || 'desc',
    page,
  }

  isFetchingInitialData.value = true

  let response = await itemStore.fetchItems(data)

  isFetchingInitialData.value = false

  return {
    data: response.data.data,
    pagination: {
      totalPages: response.data.meta.last_page,
      currentPage: page,
      totalCount: response.data.meta.total,
      limit: 10,
    },
  }
}

function removeMultipleItems() {
  dialogStore
    .openDialog({
      title: t('general.are_you_sure'),
      message: t('items.confirm_delete', 2),
      yesLabel: t('general.ok'),
      noLabel: t('general.cancel'),
      variant: 'danger',
      hideNoButton: false,
      size: 'lg',
    })
    .then((res) => {
      if (res) {
        itemStore.deleteMultipleItems().then((response) => {
          if (response.data.success) {
            table.value && table.value.refresh()
          }
        })
      }
    })
}
</script>
