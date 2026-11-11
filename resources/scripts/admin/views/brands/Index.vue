<template>
  <BasePage>
    <BasePageHeader :title="$t('brands.title')">
      <BaseBreadcrumb>
        <BaseBreadcrumbItem :title="$t('general.home')" to="dashboard" />
        <BaseBreadcrumbItem :title="$t('brands.title')" to="#" active />
      </BaseBreadcrumb>

      <template #actions>
        <div class="flex items-center justify-end space-x-5">
          <BaseButton
            v-show="brandStore.totalBrands"
            variant="primary-outline"
            :aria-expanded="showFilters ? 'true' : 'false'"
            aria-controls="brand-filter-wrapper"
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
            v-if="userStore.hasAbilities(abilities.CREATE_BRAND)"
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

    <BaseFilterWrapper
      id="brand-filter-wrapper"
      :show="showFilters"
      class="mt-5"
      cols="auto"
      gap="md"
      aria-label="Brand filters"
      @clear="clearFilter"
    >
      <BaseInputGroup :label="$t('brands.name')" :span="2">
        <BaseInput
          v-model="filters.name"
          type="text"
          name="name"
          autocomplete="off"
        />
      </BaseInputGroup>
    </BaseFilterWrapper>

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

function toggleFilter() {
  if (showFilters.value) {
    clearFilter()
  }
  showFilters.value = !showFilters.value
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
