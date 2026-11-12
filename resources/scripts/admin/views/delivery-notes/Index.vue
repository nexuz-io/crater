<template>
  <BasePage>
    <SendDeliveryNoteModal />

    <BasePageHeader :title="$t('delivery-notes.title')">
      <BaseBreadcrumb>
        <BaseBreadcrumbItem :title="$t('general.home')" to="dashboard" />

        <BaseBreadcrumbItem
          :title="$tc('delivery-notes.delivery_note', 2)"
          to="#"
          active
        />
      </BaseBreadcrumb>

      <template #actions>
        <BaseButton
          v-show="deliveryNoteStore.totalDeliveryNoteCount"
          variant="primary-outline"
          :aria-expanded="showFilters ? 'true' : 'false'"
          aria-controls="delivery-note-filter-wrapper"
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

        <router-link
          v-if="userStore.hasAbilities(abilities.CREATE_DELIVERY_NOTE)"
          to="delivery-notes/create"
        >
          <BaseButton variant="primary" class="ml-4">
            <template #left="slotProps">
              <BaseIcon name="PlusIcon" :class="slotProps.class" />
            </template>
            {{ $t('delivery-notes.new_delivery_note') }}
          </BaseButton>
        </router-link>
      </template>
    </BasePageHeader>

    <BaseFilterWrapper
      id="delivery-note-filter-wrapper"
      :show="showFilters"
      class="mt-5"
      cols="auto"
      gap="md"
      aria-label="Delivery note filters"
      @clear="clearFilter"
    >
      <BaseInputGroup :label="$tc('customers.customer', 1)">
        <BaseCustomerSelectInput
          v-model="filters.customer_id"
          :placeholder="$t('customers.type_or_click')"
          value-prop="id"
          label="name"
        />
      </BaseInputGroup>

      <BaseInputGroup :label="$t('delivery-notes.status')">
        <BaseMultiselect
          v-model="filters.status"
          :options="status"
          searchable
          :placeholder="$t('general.select_a_status')"
          @update:modelValue="setActiveTab"
          @remove="clearStatusSearch()"
        />
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

      <BaseInputGroup :label="$t('delivery-notes.delivery_note_number')">
        <BaseInput v-model="filters.delivery_number">
          <template #left="slotProps">
            <BaseIcon name="HashtagIcon" :class="slotProps.class" />
          </template>
        </BaseInput>
      </BaseInputGroup>
    </BaseFilterWrapper>

    <BaseEmptyPlaceholder
      v-show="showEmptyScreen"
      :title="$t('delivery-notes.no_delivery_notes')"
      :description="$t('delivery-notes.list_of_delivery_notes')"
    >
      <ObservatoryIcon class="mt-5 mb-4" />

      <template #actions>
        <BaseButton
          v-if="userStore.hasAbilities(abilities.CREATE_DELIVERY_NOTE)"
          variant="primary-outline"
          @click="$router.push('/admin/delivery-notes/create')"
        >
          <template #left="slotProps">
            <BaseIcon name="PlusIcon" :class="slotProps.class" />
          </template>
          {{ $t('delivery-notes.create_delivery_note') }}
        </BaseButton>
      </template>
    </BaseEmptyPlaceholder>

    <div v-show="!showEmptyScreen" class="relative table-container">
      <div
        class="
          relative
          flex
          items-center
          justify-between
          h-10
          mt-5
          list-none
          border-b-2 border-gray-200 border-solid
        "
      >
        <!-- Tabs: ciclo de vida del albarán. -->
        <BaseTabGroup class="-mb-5" @change="setStatusFilter">
          <BaseTab :title="$t('general.all')" filter="" />
          <BaseTab :title="$t('general.draft')" filter="DRAFT" />
          <BaseTab :title="$t('delivery-notes.delivered')" filter="DELIVERED" />
          <BaseTab :title="$t('delivery-notes.cancelled')" filter="CANCELLED" />
        </BaseTabGroup>

        <BaseDropdown
          v-if="
            deliveryNoteStore.selectedDeliveryNotes.length &&
            userStore.hasAbilities(abilities.DELETE_DELIVERY_NOTE)
          "
          class="absolute float-right"
        >
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

          <BaseDropdownItem @click="removeMultipleDeliveryNotes">
            <BaseIcon name="TrashIcon" class="mr-3 text-gray-600" />
            {{ $t('general.delete') }}
          </BaseDropdownItem>
        </BaseDropdown>
      </div>

      <BaseTable
        ref="tableComponent"
        :data="fetchData"
        :columns="deliveryNoteColumns"
        :placeholder-count="
          deliveryNoteStore.totalDeliveryNoteCount >= 20 ? 10 : 5
        "
        class="mt-10"
      >
        <template #header>
          <div class="absolute items-center left-6 top-2.5 select-none">
            <BaseCheckbox
              v-model="deliveryNoteStore.selectAllField"
              variant="primary"
              @change="deliveryNoteStore.selectAllDeliveryNotes"
            />
          </div>
        </template>

        <template #cell-checkbox="{ row }">
          <div class="relative block">
            <BaseCheckbox
              :id="row.id"
              v-model="selectField"
              :value="row.data.id"
            />
          </div>
        </template>

        <!-- Delivery date -->
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

        <template #cell-name="{ row }">
          <BaseText :text="row.data.customer.name" :length="30" />
        </template>

        <template #cell-status="{ row }">
          <BaseDeliveryNoteStatusBadge
            :status="row.data.status"
            class="px-3 py-1"
          >
            {{ $t(`delivery-notes.${row.data.status.toLowerCase()}`) }}
          </BaseDeliveryNoteStatusBadge>
          <span
            v-if="row.data.already_billed"
            class="
              ml-2
              px-2
              py-0.5
              text-[10px]
              font-semibold
              rounded
              bg-blue-100
              text-blue-700
              uppercase
            "
          >
            {{ $t('delivery-notes.billed') }}
          </span>
        </template>

        <template #cell-total="{ row }">
          <BaseFormatMoney
            :amount="row.data.total"
            :currency="row.data.customer.currency"
          />
        </template>

        <!-- Actions -->
        <template v-if="hasAtleastOneAbility()" #cell-actions="{ row }">
          <DeliveryNoteIndexDropdown :row="row.data" :table="tableComponent" />
        </template>
      </BaseTable>
    </div>
  </BasePage>
</template>

<script setup>
import { computed, onUnmounted, reactive, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useDeliveryNoteStore } from '@/scripts/admin/stores/delivery-note'
import { useDialogStore } from '@/scripts/stores/dialog'
import { useUserStore } from '@/scripts/admin/stores/user'
import { debouncedWatch } from '@vueuse/core'
import abilities from '@/scripts/admin/stub/abilities'

import ObservatoryIcon from '@/scripts/components/icons/empty/ObservatoryIcon.vue'
import DeliveryNoteIndexDropdown from '@/scripts/admin/components/dropdowns/DeliveryNoteIndexDropdown.vue'
import SendDeliveryNoteModal from '@/scripts/admin/components/modal-components/SendDeliveryNoteModal.vue'

const deliveryNoteStore = useDeliveryNoteStore()
const dialogStore = useDialogStore()
const userStore = useUserStore()

const tableComponent = ref(null)
const { t } = useI18n()
const showFilters = ref(false)
const status = ref(['DRAFT', 'DELIVERED', 'CANCELLED'])

const isRequestOngoing = ref(true)
const activeTab = ref('general.all')

let filters = reactive({
  customer_id: '',
  status: '',
  from_date: '',
  to_date: '',
  delivery_number: '',
})

const showEmptyScreen = computed(
  () => !deliveryNoteStore.totalDeliveryNoteCount && !isRequestOngoing.value
)

const selectField = computed({
  get: () => deliveryNoteStore.selectedDeliveryNotes,
  set: (val) => {
    deliveryNoteStore.selectDeliveryNote(val)
  },
})

const deliveryNoteColumns = computed(() => {
  return [
    {
      key: 'checkbox',
      thClass: 'extra w-10 pr-0',
      sortable: false,
      tdClass: 'font-medium text-gray-900 pr-0',
    },
    {
      key: 'delivery_date',
      label: t('delivery-notes.delivery_date'),
      thClass: 'extra',
      tdClass: 'font-medium text-gray-500',
    },
    {
      key: 'delivery_number',
      label: t('delivery-notes.delivery_note_number'),
    },
    { key: 'name', label: t('delivery-notes.customer') },
    { key: 'status', label: t('delivery-notes.status') },
    {
      key: 'total',
      label: t('general.total'),
      tdClass: 'font-medium text-gray-900',
    },
    {
      key: 'actions',
      tdClass: 'text-right text-sm font-medium pl-0',
      thClass: 'text-right pl-0',
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

onUnmounted(() => {
  if (deliveryNoteStore.selectAllField) {
    deliveryNoteStore.selectAllDeliveryNotes()
  }
})

function hasAtleastOneAbility() {
  return userStore.hasAbilities([
    abilities.CREATE_DELIVERY_NOTE,
    abilities.EDIT_DELIVERY_NOTE,
    abilities.VIEW_DELIVERY_NOTE,
    abilities.SEND_DELIVERY_NOTE,
  ])
}

async function clearStatusSearch() {
  filters.status = ''
  refreshTable()
}

function refreshTable() {
  tableComponent.value && tableComponent.value.refresh()
}

async function fetchData({ page, filter, sort }) {
  const data = {
    customer_id: filters.customer_id,
    status: filters.status,
    from_date: filters.from_date,
    to_date: filters.to_date,
    delivery_number: filters.delivery_number,
    orderByField: sort.fieldName || 'created_at',
    orderBy: sort.order || 'desc',
    page,
  }

  isRequestOngoing.value = true

  const response = await deliveryNoteStore.fetchDeliveryNotes(data)

  isRequestOngoing.value = false

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

function setStatusFilter(val) {
  if (activeTab.value == val.title) {
    return true
  }

  activeTab.value = val.title

  switch (val.title) {
    case t('general.draft'):
      filters.status = 'DRAFT'
      break
    case t('delivery-notes.delivered'):
      filters.status = 'DELIVERED'
      break
    case t('delivery-notes.cancelled'):
      filters.status = 'CANCELLED'
      break
    default:
      filters.status = ''
      break
  }
}

function setFilters() {
  deliveryNoteStore.$patch((state) => {
    state.selectedDeliveryNotes = []
    state.selectAllField = false
  })

  refreshTable()
}

function clearFilter() {
  filters.customer_id = ''
  filters.status = ''
  filters.from_date = ''
  filters.to_date = ''
  filters.delivery_number = ''

  activeTab.value = t('general.all')
}

function toggleFilter() {
  if (showFilters.value) {
    clearFilter()
  }

  showFilters.value = !showFilters.value
}

async function removeMultipleDeliveryNotes() {
  dialogStore
    .openDialog({
      title: t('general.are_you_sure'),
      message: t('delivery-notes.confirm_delete'),
      yesLabel: t('general.ok'),
      noLabel: t('general.cancel'),
      variant: 'danger',
      hideNoButton: false,
      size: 'lg',
    })
    .then((res) => {
      if (res) {
        deliveryNoteStore.deleteMultipleDeliveryNotes().then(() => {
          refreshTable()
          deliveryNoteStore.$patch((state) => {
            state.selectedDeliveryNotes = []
            state.selectAllField = false
          })
        })
      }
    })
}

function setActiveTab(val) {
  switch (val) {
    case 'DRAFT':
      activeTab.value = t('general.draft')
      break
    case 'DELIVERED':
      activeTab.value = t('delivery-notes.delivered')
      break
    case 'CANCELLED':
      activeTab.value = t('delivery-notes.cancelled')
      break
    default:
      activeTab.value = t('general.all')
      break
  }
}
</script>
