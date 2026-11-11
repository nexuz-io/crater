<template>
  <BasePage>
    <BasePageHeader :title="$t('brands.title')">
      <BaseBreadcrumb>
        <BaseBreadcrumbItem :title="$t('general.home')" to="dashboard" />
        <BaseBreadcrumbItem :title="$t('brands.title')" to="#" active />
      </BaseBreadcrumb>

      <template #actions>
        <div
          class="
            flex flex-wrap
            items-center justify-end
            gap-2
            sm:flex-nowrap sm:gap-5
          "
        >
          <BaseButton
            v-if="userStore.hasAbilities(abilities.CREATE_BRAND)"
            class="
              shrink-0
              w-full sm:w-auto
            "
            @click="addNewBrand"
          >
            <template #left="slotProps">
              <BaseIcon name="PlusIcon" :class="slotProps.class" />
            </template>
            {{ $t('brands.add_brand') }}
          </BaseButton>
        </div>
      </template>
    </BasePageHeader>

    <BrandModal />

    <!-- Phase 9 · Pilot reuse of BaseFilterToolbar on a 1-filter
         listing. Even when the toolbar hides nothing, moving the
         search up here proves the contract: same `filter-primary`
         slot for the always-visible input, same `filter-panel`
         slot for the advanced surface, same chip plumbing. -->
    <BaseFilterToolbar
      id="brands-filter-toolbar"
      class="mt-4"
      :chips="activeFilterChips"
      :count-label="activeFilterChips.length || null"
      :clear-all-label="$t('general.clear_all')"
      aria-label="Brand filters toolbar"
      @remove="removeFilterByKey"
      @clear="clearFilter"
    >
      <template #filter-primary>
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
            id="brands-search"
            v-model="filters.name"
            name="brand-search"
            :placeholder="$t('brands.search_placeholder')"
            :clear-label="$t('general.clear')"
            class="min-w-0 flex-1"
            @submit="setFilters"
          />

          <BaseButton
            v-show="brandStore.totalBrands"
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
            aria-controls="brands-filter-toolbar"
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
        <BaseFilterWrapper
          v-show="showFilters"
          cols="auto"
          gap="md"
          aria-label="Brand filters"
          :has-active-filters="activeFilterChips.length > 0"
          @clear="clearFilter"
        >
          <BaseInputGroup
            :label="$t('brands.name')"
            :span="2"
            :active="!!filters.name?.trim()"
          >
            <BaseInput
              v-model="filters.name"
              type="text"
              name="name"
              autocomplete="off"
            />
          </BaseInputGroup>
        </BaseFilterWrapper>
      </template>
    </BaseFilterToolbar>

    <BaseEmptyPlaceholder
      v-show="showEmptyScreen"
      :title="$t('brands.no_brands')"
      :description="$t('brands.list_of_brands')"
    >
      <SatelliteIcon class="mt-5 mb-4" />

      <template #actions>
        <BaseButton
          v-if="userStore.hasAbilities(abilities.CREATE_BRAND)"
          variant="primary-outline"
          @click="addNewBrand"
        >
          <template #left="slotProps">
            <BaseIcon name="PlusIcon" :class="slotProps.class" />
          </template>
          {{ $t('brands.add_new_brand') }}
        </BaseButton>
      </template>
    </BaseEmptyPlaceholder>

    <div v-show="!showEmptyScreen" class="relative table-container">
      <BaseTable
        ref="table"
        :data="fetchData"
        :columns="brandColumns"
        :placeholder-count="brandStore.totalBrands >= 20 ? 10 : 5"
        class="mt-3"
      >
        <template #cell-name="{ row }">
          <router-link
            v-if="userStore.hasAbilities(abilities.EDIT_BRAND)"
            :to="{ path: `/admin/brands/${row.data.id}/edit` }"
            class="font-medium text-primary-500"
          >
            {{ row.data.name }}
          </router-link>
          <span v-else class="font-medium text-gray-900">
            {{ row.data.name }}
          </span>
        </template>

        <template v-if="hasAtLeastOneAbility()" #cell-actions="{ row }">
          <BrandIndexDropdown
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
import { computed, inject, onMounted, onUnmounted, reactive, ref } from 'vue'
import { debouncedWatch } from '@vueuse/core'
import { useI18n } from 'vue-i18n'
import { useBrandStore } from '@/scripts/admin/stores/brand'
import { useUserStore } from '@/scripts/admin/stores/user'
import { useModalStore } from '@/scripts/stores/modal'
import BrandModal from '@/scripts/admin/components/modal-components/BrandModal.vue'
import BrandIndexDropdown from '@/scripts/admin/components/dropdowns/BrandIndexDropdown.vue'
import SatelliteIcon from '@/scripts/components/icons/empty/SatelliteIcon.vue'
import abilities from '@/scripts/admin/stub/abilities'

const brandStore = useBrandStore()
const userStore = useUserStore()
const modalStore = useModalStore()
const { t } = useI18n()

const showFilters = ref(false)
const isFetchingInitialData = ref(true)
const table = ref(null)

const filters = reactive({
  name: '',
})

const brandColumns = computed(() => [
  {
    key: 'name',
    label: t('brands.name'),
    thClass: 'extra',
    tdClass: 'font-medium text-gray-900',
  },
  {
    key: 'actions',
    thClass: 'text-right',
    tdClass: 'text-right text-sm font-medium',
    sortable: false,
  },
])

const showEmptyScreen = computed(
  () => !brandStore.totalBrands && !isFetchingInitialData.value
)

function hasAtLeastOneAbility() {
  return userStore.hasAbilities([
    abilities.DELETE_BRAND,
    abilities.EDIT_BRAND,
  ])
}

function addNewBrand() {
  modalStore.openModal({
    title: t('brands.add_brand'),
    componentName: 'BrandModal',
    size: 'sm',
    refreshData: refreshTable,
  })
}

function clearFilter() {
  filters.name = ''
}

// Phase 9 · Chips de filtros activos compartidos con el toolbar
// patrón. Para brands sólo hay un filtro (name) así que el resumen
// es trivial, pero el contrato ya está en uso: si en el futuro se
// añaden filtros adicionales, basta con extender este computed.
const activeFilterChips = computed(() => {
  const chips = []
  if (filters.name?.trim()) {
    chips.push({ key: 'name', label: `"${filters.name}"` })
  }
  return chips
})

function removeFilterByKey(key) {
  if (key === 'name') {
    filters.name = ''
  }
}

function toggleFilter() {
  // El botón toggleFilter abre/cierra el panel pero NO limpia
  // filtros. La acción `Limpiar todo` queda en manos del usuario
  // (botón explícito o chip de resumen), evitando el footgun
  // histórico de "al cerrar el panel se vacían los filtros".
  showFilters.value = !showFilters.value
}

function setFilters() {
  refreshTable()
}

function refreshTable() {
  table.value && table.value.refresh()
}

debouncedWatch(
  filters,
  () => refreshTable(),
  { debounce: 500 }
)

async function fetchData({ page }) {
  isFetchingInitialData.value = true
  const response = await brandStore.fetchBrands({
    search: filters.name,
    orderByField: 'name',
    orderBy: 'asc',
    page,
  })
  isFetchingInitialData.value = false
  return {
    data: response.data.data,
    pagination: {
      totalPages: response.data.meta?.last_page ?? 1,
      currentPage: page,
      limit: 10,
      totalCount: response.data.meta?.total ?? 0,
    },
  }
}

onMounted(() => {
  refreshTable()
})

// Mirror the items pattern: clear totals on unmount so the next
// visit re-fetches from a known clean state. Stale counts would
// otherwise bleed into the placeholder vs table decision on
// subsequent navigations.
onUnmounted(() => {
  brandStore.totalBrands = 0
})
</script>
