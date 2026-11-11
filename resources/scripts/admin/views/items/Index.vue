<template>
  <BasePage>
    <BasePageHeader :title="$t('items.title')">
      <BaseBreadcrumb>
        <BaseBreadcrumbItem :title="$t('general.home')" to="dashboard" />
        <BaseBreadcrumbItem :title="$t('items.services')" to="#" active />
      </BaseBreadcrumb>

      <template #actions>
        <!-- Action cluster. On phones the cluster wraps to two
             rows so the new-article CTA (the most important action)
             never gets clipped behind the right edge of the viewport,
             and the secondary shortcuts (Marcas, Familias) keep
             their own row up top. From `sm` upward the same elements
             collapse back into a single horizontal row. `shrink-0`
             keeps each button at its full intrinsic width so flex
             never collapses them to icons-only. -->
        <div
          class="
            flex flex-wrap
            items-center justify-end
            gap-2
            sm:flex-nowrap sm:gap-5
          "
        >
          <BaseButton
            v-if="userStore.hasAbilities(abilities.VIEW_BRAND)"
            variant="primary-outline"
            class="shrink-0"
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
            class="shrink-0"
            @click="$router.push('/admin/item-families')"
          >
            <template #left="slotProps">
              <BaseIcon name="PuzzleIcon" :class="slotProps.class" />
            </template>
            {{ $t('item_families.title') }}
          </BaseButton>

          <BaseButton
            v-if="userStore.hasAbilities(abilities.CREATE_ITEM)"
            class="
              shrink-0
              w-full sm:w-auto
            "
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

    <!-- Phase 1+3 · Toolbar persistente. La búsqueda principal queda
         siempre visible y el botón "Filtrar" controla la visibilidad
         del panel avanzado (slot filter-panel). El slot filter-actions
         se reserva para futuros consumidores que necesiten acciones
         complementarias en la barra. -->
    <BaseFilterToolbar
      id="items-filter-toolbar"
      class="mt-4"
      :chips="activeFilterChips"
      :count-label="activeFilterChips.length || null"
      :clear-all-label="$t('general.clear_all')"
      aria-label="Item filters toolbar"
      @remove="removeFilterByKey"
      @clear="clearFilter"
    >
      <template #filter-primary>
        <!-- Container común: search + botón viven en una sola unidad
             visual (rounded-xl + bg-surface-secondary + border + shadow).
             En < sm se apilan para no comprimir ninguno de los dos;
             desde sm+ se ponen lado a lado, con el botón explícitamente
             empujado a la derecha via lg:ml-auto. El padding interno
             (p-1.5) y el border permiten que cada control conserve su
             propio background y se distingan como elementos dentro
             del card contenedor. -->
        <div
          class="
            flex flex-col sm:flex-row
            w-full
            items-stretch
            gap-2 sm:gap-3
            p-2.5
            bg-surface-secondary
            border border-line-light
            rounded-xl
            shadow-soft
          "
        >
          <BaseFilterSearchBar
            id="items-search"
            v-model="filters.name"
            name="items-search"
            :placeholder="$t('items.search_placeholder')"
            :clear-label="$t('general.clear')"
            class="min-w-0 flex-1"
            @submit="setFilters"
          />

          <BaseButton
            v-show="itemStore.totalItems"
            variant="primary-outline"
            class="
              h-11 shrink-0
              px-3 sm:px-4
              w-full sm:w-auto
              justify-between sm:justify-start
              gap-2 sm:gap-1.5
              lg:ml-auto
            "
            :aria-expanded="showFilters ? 'true' : 'false'"
            aria-controls="items-filter-toolbar"
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
        </div>
      </template>

      <template #filter-panel>
        <!-- Desktop distribution (md+):
             Row 1: unit · status (1/3 c/u — search vive en el toolbar)
             Row 2: brand · family · price (1/3 c/u)
             Row 3: no-brand · no-family (1/2 c/u — toggles)
             Row 4 (conditional): descendants (span=full)

             The previous 4+4+1 layout left 2 empty cells on every
             row; this one fills the 6-col template cleanly in both
             the default (8 controls) and "family picked" (9
             controls) states. We pin every desktop breakpoint
             (lg/xl/2xl) to the 6-col template so the 2xl wide
             monitor does not push the layout into an awkward
             4+3+1 auto-place pattern. -->
        <BaseFilterWrapper
          v-show="showFilters"
          class="hidden md:block"
          cols="auto-6-6-6"
          gap="md"
          aria-label="Item filters"
          :has-active-filters="activeFilterChips.length > 0"
          @clear="clearFilter"
        >
      <!-- Row 1 · Unidad + estado (1/3 c/u; el campo de búsqueda vive
           en el toolbar persistente). -->
      <BaseInputGroup
        :label="$tc('items.unit')"
        :span="2"
        :active="isFilterActive('unit_id')"
      >
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

      <BaseInputGroup
        :label="$t('items.service_status')"
        :span="2"
        :active="isFilterActive('active')"
      >
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

      <!-- Row 2 · Marca + Familia + Precio (1/3 c/u). -->
      <BaseInputGroup
        :label="$t('items.brand')"
        :span="2"
        :active="isFilterActive('brand_id')"
      >
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

      <BaseInputGroup
        :label="$t('items.family')"
        :span="2"
        :active="isFilterActive('family_id')"
      >
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

      <BaseInputGroup
        :label="$t('items.unit_price')"
        :span="2"
        :active="isFilterActive('price')"
      >
        <BaseMoney v-model="filters.price" />
      </BaseInputGroup>

      <!-- Row 3 · Toggles mutuamente excluyentes (1/2 c/u). -->
      <BaseInputGroup
        :label="$t('brands.filter_without_brand')"
        :span="3"
        :active="isFilterActive('without_brand')"
      >
        <BaseSwitch
          :model-value="filters.without_brand"
          @update:model-value="toggleWithoutBrand"
        />
      </BaseInputGroup>

      <BaseInputGroup
        :label="$t('item_families.without_family')"
        :span="3"
        :active="isFilterActive('without_family')"
      >
        <BaseSwitch
          :model-value="filters.without_family"
          @update:model-value="toggleWithoutFamily"
        />
      </BaseInputGroup>
    </BaseFilterWrapper>

    <!-- Mobile / tablet layout: stacked row-based sheet with explicit
         Limpiar / Aplicar acciones y un header mínimo (solo título +
         colapsar, sin icono, según decisión de UI móvil). Visible
         below `md` only; the desktop wrapper above owns md+. La
         búsqueda global vive en el toolbar, no en este panel. -->
    <BaseFilterSheet
      v-show="showFilters"
      class="md:hidden"
      :show="showFilters"
      aria-label="Item filters"
      :clear-label="$t('general.clear_all')"
      :apply-label="$t('general.apply')"
      @update:show="showFilters = $event"
      @clear="clearFilter"
      @apply="onApply"
    >
      <!-- Row 1: unit + status, semantically related dropdowns
           (catalogue + lifecycle). 2-col on sm+, 1-col on tiny phones. -->
      <BaseFilterRow variant="pair" align="start">
        <BaseInputGroup
          :label="$tc('items.unit')"
          :active="isFilterActive('unit_id')"
        >
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
        <BaseInputGroup
          :label="$t('items.service_status')"
          :active="isFilterActive('active')"
        >
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
      </BaseFilterRow>

      <!-- Row 2: brand + family, semantically related classification
           filters. Same 2-col responsive curve as row 1. -->
      <BaseFilterRow variant="pair" align="start">
        <BaseInputGroup
          :label="$t('items.brand')"
          :active="isFilterActive('brand_id')"
        >
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
        <BaseInputGroup
          :label="$t('items.family')"
          :active="isFilterActive('family_id')"
        >
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
      </BaseFilterRow>

      <!-- Row 3: full-width price. -->
      <BaseFilterRow variant="full">
        <BaseInputGroup
          :label="$t('items.unit_price')"
          :active="isFilterActive('price')"
        >
          <BaseMoney v-model="filters.price" />
        </BaseInputGroup>
      </BaseFilterRow>

      <!-- Row 4: without-brand + without-family toggles. Pair layout
           so switches line up beside each other on phablet+ layouts. -->
      <BaseFilterRow variant="pair" align="center">
        <BaseInputGroup
          :label="$t('brands.filter_without_brand')"
          :active="isFilterActive('without_brand')"
        >
          <BaseSwitch
            :model-value="filters.without_brand"
            @update:model-value="toggleWithoutBrand"
          />
        </BaseInputGroup>
        <BaseInputGroup
          :label="$t('item_families.without_family')"
          :active="isFilterActive('without_family')"
        >
          <BaseSwitch
            :model-value="filters.without_family"
            @update:model-value="toggleWithoutFamily"
          />
        </BaseInputGroup>
      </BaseFilterRow>
    </BaseFilterSheet>
      </template>
    </BaseFilterToolbar>

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

// Phase 2 · Resumen de filtros activos. Cada chip representa un
// filtro con valor significativo (no vacío, no false). El listado
// decide qué mostrar y con qué etiqueta; el componente
// `BaseFilterChip` se encarga del rendering removible.
//
// `unit_id` y los selectores vienen como objeto { id, name }; leemos
// `name` para mostrarlo legible. `price` es string (lo emite
// `BaseMoney`); sólo lo incluimos si tiene valor. Los toggles
// booleanos aparecen cuando están activos.
const activeFilterChips = computed(() => {
  const chips = []

  if (filters.name?.trim()) {
    chips.push({ key: 'name', label: `"${filters.name}"` })
  }

  // `value-prop="id"` makes `v-model` store a scalar (the option id)
  // instead of the full option object. We look up the label via the
  // dedicated caches so the chip shows the user-facing name and not a
  // numeric id while the selection is alive in the dropdown. If the
  // catalog is still loading, fall back to the raw id so the chip is
  // informative rather than missing.
  if (filters.unit_id) {
    const u = unitCache.value[filters.unit_id]
    chips.push({ key: 'unit_id', label: u?.name ?? `#${filters.unit_id}` })
  }

  if (filters.active !== '' && filters.active !== null &&
      filters.active !== undefined) {
    const isActive = filters.active.value === 1 ||
                     filters.active === 1 ||
                     filters.active === true
    chips.push({
      key: 'active',
      label: isActive ? t('items.active') : t('items.inactive'),
    })
  }

  if (filters.brand_id) {
    const b = brandCache.value[filters.brand_id]
    chips.push({ key: 'brand_id', label: b?.name ?? `#${filters.brand_id}` })
  }

  if (filters.family_id) {
    // Family chips benefit from the breadcrumb path so deeply
    // nested selections stay disambiguated (otherwise two families
    // named "Servicios" at different depths would appear identical).
    const f = familyCache.value[filters.family_id]
    const segments = []
    if (f) {
      let cursor = f
      const maxDepth = 64
      let depth = 0
      while (cursor && depth < maxDepth) {
        segments.unshift(cursor.name)
        if (!cursor.parent_id) break
        cursor = familyCache.value[cursor.parent_id]
        depth++
      }
    }
    chips.push({
      key: 'family_id',
      label: segments.length ? segments.join(' / ') : `#${filters.family_id}`,
    })
  }

  if (filters.price !== '' && filters.price !== null &&
      filters.price !== undefined) {
    chips.push({ key: 'price', label: formatPriceChip(filters.price) })
  }

  if (filters.without_brand) {
    chips.push({
      key: 'without_brand',
      label: t('brands.filter_without_brand'),
    })
  }

  if (filters.without_family) {
    chips.push({
      key: 'without_family',
      label: t('item_families.without_family'),
    })
  }

  return chips
})

function formatPriceChip(value) {
  const numeric = Number(value)
  if (Number.isNaN(numeric)) return `${value}`
  const currency = companyStore.selectedCompanyCurrency
  // Lightweight formatter — full money rendering happens in the
  // table cell; for chip readability, raw currency code + number
  // is enough. MoneyFormatter from currency.js is exposed via the
  // inject('utils') helper.
  try {
    return utils?.formatMoney?.(numeric, currency) ?? `$${numeric}`
  } catch {
    return `$${numeric}`
  }
}

// Phase 7 · Active indicator hook. Each BaseInputGroup reads
// `:active="isFilterActive('unit_id')"` (or whatever key) and
// shows a tinted label + small dot + ring on the input slot when
// the corresponding filter holds a meaningful value. Centralising
// the predicate in this helper keeps the markup terse and ensures
// the dot / ring state agrees with the chips in `activeFilterChips`.
function isFilterActive(key) {
  switch (key) {
    case 'name':
      return !!filters.name?.trim()
    case 'unit_id':
      return !!filters.unit_id
    case 'active':
      return (
        filters.active !== '' &&
        filters.active !== null &&
        filters.active !== undefined
      )
    case 'brand_id':
      return !!filters.brand_id
    case 'family_id':
      return !!filters.family_id
    case 'price':
      return (
        filters.price !== '' &&
        filters.price !== null &&
        filters.price !== undefined
      )
    case 'without_brand':
      return !!filters.without_brand
    case 'without_family':
      return !!filters.without_family
    default:
      return false
  }
}

function removeFilterByKey(key) {
  switch (key) {
    case 'name':
      filters.name = ''
      break
    case 'unit_id':
      filters.unit_id = ''
      break
    case 'active':
      filters.active = ''
      break
    case 'brand_id':
      filters.brand_id = ''
      break
    case 'family_id':
      filters.family_id = ''
      break
    case 'price':
      filters.price = ''
      break
    case 'without_brand':
      filters.without_brand = false
      break
    case 'without_family':
      filters.without_family = false
      break
    default:
      break
  }
}

function hasAbilities() {
  return userStore.hasAbilities([abilities.DELETE_ITEM, abilities.EDIT_ITEM])
}

function toggleFilter() {
  // Show / hide only. Resetting filters is now an explicit action
  // owned by the `Limpiar` button in the mobile sheet footer (and
  // the legacy `Limpiar todo` button in the desktop wrapper). This
  // avoids the previous "clicking the toggle button while open
  // discards the user's filter state" footgun.
  showFilters.value = !showFilters.value
}

function onApply() {
  // With auto-apply (debouncedWatch) the table is already in sync
  // with the current `filters` state by the time the user presses
  // `Aplicar filtros`. The button's only job here is to dismiss the
  // mobile sheet so the user lands back on the table view. If we
  // later migrate to manual-apply, the relevant logic would slot in
  // here (commit a `draftFilters` snapshot to `filters` and refresh).
  refreshTable()
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
// and family_id are mutually exclusive. include_descendants is not
// user-controlled — selecting a family always includes its
// descendants, so we don't expose a toggle for it.
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

const unitCache = computed(() => {
  const map = {}
  for (const unit of itemStore.itemUnits ?? []) {
    map[unit.id] = unit
  }
  return map
})

const brandCache = computed(() => {
  const map = {}
  for (const brand of itemStore.itemBrands ?? []) {
    map[brand.id] = brand
  }
  return map
})

async function fetchData({ page, filter, sort }) {
  // Guard against the empty-string case: `Math.round('' * 100) === 0`,
  // so a cleared price would otherwise be sent as `0` and the backend
  // scopeApplyFilters would filter items whose price is exactly 0
  // instead of returning the unfiltered list.
  const normalisedPrice =
    filters.price === '' || filters.price == null
      ? ''
      : Math.round(Number(filters.price) * 100)

  let data = {
    search: filters.name,
    unit_id: filters.unit_id !== null ? filters.unit_id : '',
    price: normalisedPrice,
    active: filters.active !== '' && filters.active !== null && filters.active !== undefined ? filters.active : '',
    brand_id: filters.brand_id !== null && filters.brand_id !== '' ? filters.brand_id : '',
    without_brand: filters.without_brand ? '1' : '',
    family_id: filters.family_id !== null && filters.family_id !== '' ? filters.family_id : '',
    without_family: filters.without_family ? '1' : '',
    // include_descendants no longer user-controlled: a family_id
    // selection always implies including its sub-tree, so we send
    // '1' whenever the family is set and neither of the exclusion
    // toggles is on. The backend treats anything else as "match the
    // exact node and stop".
    include_descendants:
      filters.family_id && !filters.without_brand && !filters.without_family
        ? '1'
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
