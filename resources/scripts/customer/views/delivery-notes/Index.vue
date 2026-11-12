<template>
  <BasePage>
    <!-- Page Header -->
    <BasePageHeader :title="$t('delivery-notes.title')">
      <BaseBreadcrumb>
        <BaseBreadcrumbItem
          :title="$t('general.home')"
          :to="`/${globalStore.companySlug}/customer/dashboard`"
        />
        <BaseBreadcrumbItem
          :title="$tc('delivery-notes.delivery_note', 2)"
          to="#"
          active
        />
      </BaseBreadcrumb>
      <template #actions>
        <BaseButton
          v-if="deliveryNoteStore.totalDeliveryNotes"
          variant="primary-outline"
          :aria-expanded="showFilters ? 'true' : 'false'"
          aria-controls="customer-delivery-note-filter-wrapper"
          @click="toggleFilter"
        >
          {{ $t('general.filter') }}
          <template #right="slotProps">
            <BaseIcon
              v-if="!showFilters"
              name="FilterIcon"
              :class="slotProps.class"
            />
            <BaseIcon v-else name="XIcon" :class="slotProps.class" />
          </template>
        </BaseButton>
      </template>
    </BasePageHeader>

    <BaseFilterWrapper
      id="customer-delivery-note-filter-wrapper"
      :show="showFilters"
      class="mt-7"
      cols="auto"
      gap="md"
      aria-label="Customer delivery note filters"
      @clear="clearFilter"
    >
      <BaseInputGroup :label="$t('delivery-notes.status')">
        <BaseSelectInput
          v-model="filters.status"
          :options="status"
          searchable
          :show-labels="false"
          :allow-empty="false"
          :placeholder="$t('general.select_a_status')"
        />
      </BaseInputGroup>

      <BaseInputGroup
        :label="$t('delivery-notes.delivery_note_number')"
        color="black-light"
      >
        <BaseInput v-model="filters.delivery_number">
          <BaseIcon name="HashtagIcon" class="h-5 mr-3 text-gray-600" />
        </BaseInput>
      </BaseInputGroup>

      <BaseInputGroup :label="$t('general.from')">
        <BaseDatePicker
          v-model="filters.from_date"
          :calendar-button="true"
          calendar-button-icon="calendar"
        />
      </BaseInputGroup>

      <BaseInputGroup :label="$t('general.to')">
        <BaseDatePicker
          v-model="filters.to_date"
          :calendar-button="true"
          calendar-button-icon="calendar"
        />
      </BaseInputGroup>
    </BaseFilterWrapper>

    <BaseEmptyPlaceholder
      v-if="showEmptyScreen"
      :title="$t('delivery-notes.no_delivery_notes')"
      :description="$t('delivery-notes.list_of_delivery_notes')"
    >
      <ObservatoryIcon class="mt-5 mb-4" />
    </BaseEmptyPlaceholder>

    <div v-show="!showEmptyScreen" class="relative table-container">
      <BaseTable
        ref="table"
        :data="fetchData"
        :columns="deliveryNoteColumns"
        :placeholder-count="
          deliveryNoteStore.totalDeliveryNotes >= 20 ? 10 : 5
        "
        class="mt-10"
      >
        <template #cell-delivery_date="{ row }">
          {{ row.data.formatted_delivery_date }}
        </template>

        <template #cell-delivery_number="{ row }">
          <router-link
            :to="{ path: `delivery-notes/${row.data.id}/view` }"
            class="font-medium text-primary-500"
          >
            {{ row.data.delivery_number }}
          </router-link>
        </template>

        <template #cell-status="{ row }">
          <BaseDeliveryNoteStatusBadge
            :status="row.data.status"
            class="px-3 py-1"
          >
            {{ $t(`delivery-notes.${row.data.status.toLowerCase()}`) }}
          </BaseDeliveryNoteStatusBadge>
        </template>

        <template #cell-actions="{ row }">
          <BaseDropdown>
            <template #activator>
              <BaseIcon name="DotsHorizontalIcon" class="h-5 text-gray-500" />
            </template>
            <router-link :to="`delivery-notes/${row.data.id}/view`">
              <BaseDropdownItem>
                <BaseIcon name="EyeIcon" class="h-5 mr-3 text-gray-600" />
                {{ $t('general.view') }}
              </BaseDropdownItem>
            </router-link>
          </BaseDropdown>
        </template>
      </BaseTable>
    </div>
  </BasePage>
</template>

<script setup>
import { debouncedWatch } from '@vueuse/core'
import BaseTable from '@/scripts/components/base/base-table/BaseTable.vue'
import { ref, computed, reactive } from 'vue'
import { useGlobalStore } from '@/scripts/customer/stores/global'
import { useDeliveryNoteStore } from '@/scripts/customer/stores/delivery-note'
import ObservatoryIcon from '@/scripts/components/icons/empty/ObservatoryIcon.vue'
import { useI18n } from 'vue-i18n'

const { t } = useI18n()

const table = ref(null)
const showFilters = ref(false)
const isFetchingInitialData = ref(true)

const status = ref(['DRAFT', 'DELIVERED', 'CANCELLED'])
const filters = reactive({
  status: '',
  from_date: '',
  to_date: '',
  delivery_number: '',
})

const globalStore = useGlobalStore()
const deliveryNoteStore = useDeliveryNoteStore()

const deliveryNoteColumns = computed(() => {
  return [
    {
      key: 'delivery_date',
      label: t('delivery-notes.delivery_date'),
      thClass: 'extra',
      tdClass: 'font-medium text-gray-900',
    },
    {
      key: 'delivery_number',
      label: t('delivery-notes.delivery_note_number'),
    },
    { key: 'status', label: t('delivery-notes.status') },
    {
      key: 'actions',
      thClass: 'text-right',
      tdClass: 'text-right text-sm font-medium',
      sortable: false,
    },
  ]
})

const showEmptyScreen = computed(
  () => !deliveryNoteStore.totalDeliveryNotes && !isFetchingInitialData.value
)

debouncedWatch(
  filters,
  () => {
    setFilters()
  },
  { debounce: 500 }
)

function refreshTable() {
  table.value && table.value.refresh()
}

function setFilters() {
  refreshTable()
}

function clearFilter() {
  filters.status = ''
  filters.from_date = ''
  filters.to_date = ''
  filters.delivery_number = ''
}

function toggleFilter() {
  if (showFilters.value) {
    clearFilter()
  }

  showFilters.value = !showFilters.value
}

async function fetchData({ page, sort }) {
  const data = {
    status: filters.status,
    delivery_number: filters.delivery_number,
    from_date: filters.from_date,
    to_date: filters.to_date,
    orderByField: sort.fieldName || 'created_at',
    orderBy: sort.order || 'desc',
    page,
  }

  isFetchingInitialData.value = true

  const response = await deliveryNoteStore.fetchDeliveryNotes(
    data,
    globalStore.companySlug
  )

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
</script>
