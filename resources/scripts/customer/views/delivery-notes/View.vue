<template>
  <BasePage class="xl:pl-96">
    <BasePageHeader :title="pageTitle.delivery_number">
      <template #actions>
        <!--
          El albarán, al contrario que el presupuesto, no es objeto de
          aceptación/rechazo del cliente: muestra simplemente el estado
          tal como lo dejó la empresa. Reservamos la ranura #actions
          por simetría con la cabecera del resto de documentos.
        -->
        <div
          v-if="deliveryNoteStore.selectedViewDeliveryNote?.status"
          class="text-sm"
        >
          <BaseDeliveryNoteStatusBadge
            :status="
              deliveryNoteStore.selectedViewDeliveryNote.status
            "
            class="px-3 py-1"
          >
            {{
              $t(
                `delivery-notes.${deliveryNoteStore.selectedViewDeliveryNote.status.toLowerCase()}`
              )
            }}
          </BaseDeliveryNoteStatusBadge>
        </div>
      </template>
    </BasePageHeader>

    <!-- Sidebar list -->
    <div
      class="fixed top-0 left-0 hidden h-full pt-16 pb-4 bg-white w-88 xl:block"
    >
      <div
        class="
          flex
          items-center
          justify-between
          px-4
          pt-8
          pb-6
          border border-gray-200 border-solid
        "
      >
        <BaseInput
          v-model="searchData.delivery_number"
          :placeholder="$t('general.search')"
          type="text"
          variant="gray"
          @input="onSearch"
        >
          <template #right>
            <BaseIcon name="SearchIcon" class="h-5 text-gray-400" />
          </template>
        </BaseInput>

        <div class="flex ml-3" role="group" aria-label="First group">
          <BaseDropdown
            position="bottom-start"
            width-class="w-50"
            position-class="left-0"
          >
            <template #activator>
              <BaseButton variant="gray">
                <BaseIcon name="FilterIcon" class="h-5" />
              </BaseButton>
            </template>

            <div
              class="
                px-4
                py-1
                pb-2
                mb-2
                text-sm
                border-b border-gray-200 border-solid
              "
            >
              {{ $t('general.sort_by') }}
            </div>

            <div class="px-2">
              <BaseDropdownItem class="rounded-md pt-3 hover:rounded-md">
                <BaseInputGroup class="-mt-3 font-normal">
                  <BaseRadio
                    id="filter_delivery_date"
                    v-model="searchData.orderByField"
                    :label="$t('delivery-notes.delivery_date')"
                    size="sm"
                    name="filter"
                    value="delivery_date"
                    @change="onSearch"
                  />
                </BaseInputGroup>
              </BaseDropdownItem>
            </div>

            <div class="px-2">
              <BaseDropdownItem class="rounded-md pt-3 hover:rounded-md">
                <BaseInputGroup class="-mt-3 font-normal">
                  <BaseRadio
                    id="filter_delivery_number"
                    v-model="searchData.orderByField"
                    :label="$t('delivery-notes.delivery_note_number')"
                    value="delivery_number"
                    size="sm"
                    name="filter"
                    @update:modelValue="onSearch"
                  />
                </BaseInputGroup>
              </BaseDropdownItem>
            </div>
          </BaseDropdown>

          <BaseButton class="ml-1" variant="white" @click="sortData">
            <BaseIcon v-if="getOrderBy" name="SortAscendingIcon" class="h-5" />
            <BaseIcon v-else name="SortDescendingIcon" class="h-5" />
          </BaseButton>
        </div>
      </div>

      <div
        class="
          h-full
          pb-32
          overflow-y-scroll
          border-l border-gray-200 border-solid
          sw-scroll
        "
      >
        <router-link
          v-for="(dn, index) in deliveryNoteStore.deliveryNotes"
          :id="'delivery-note-' + dn.id"
          :key="index"
          :to="`/${globalStore.companySlug}/customer/delivery-notes/${dn.id}/view`"
          :class="[
            'flex justify-between p-4 items-center cursor-pointer hover:bg-gray-100 border-l-4 border-transparent',
            {
              'bg-gray-100 border-l-4 border-primary-500 border-solid':
                hasActiveUrl(dn.id),
            },
          ]"
          style="border-bottom: 1px solid rgba(185, 193, 209, 0.41)"
        >
          <div class="flex-2">
            <div
              class="
                mb-1
                text-md
                not-italic
                font-medium
                leading-5
                text-gray-500
                capitalize
              "
            >
              {{ dn.delivery_number }}
            </div>

            <BaseDeliveryNoteStatusBadge :status="dn.status">
              {{ $t(`delivery-notes.${dn.status.toLowerCase()}`) }}
            </BaseDeliveryNoteStatusBadge>
          </div>

          <div class="flex-1 whitespace-nowrap right">
            <div
              class="
                mb-2
                text-xl
                not-italic
                font-semibold
                leading-8
                text-right text-gray-900
                block
              "
            >
              #{{ dn.delivery_number }}
            </div>
            <div class="text-sm text-right text-gray-500 non-italic">
              {{ dn.formatted_delivery_date }}
            </div>
          </div>
        </router-link>

        <p
          v-if="!deliveryNoteStore.deliveryNotes.length"
          class="flex justify-center px-4 mt-5 text-sm text-gray-600"
        >
          {{ $t('delivery-notes.no_matching_delivery_notes') }}
        </p>
      </div>
    </div>

    <!-- PDF -->
    <div
      class="flex flex-col min-h-0 mt-8 overflow-hidden"
      style="height: 75vh"
    >
      <iframe
        v-if="shareableLink"
        :src="shareableLink"
        class="flex-1 border border-gray-400 border-solid rounded-md"
      />
    </div>
  </BasePage>
</template>

<script setup>
import { useI18n } from 'vue-i18n'
import BaseDropdown from '@/scripts/components/base/BaseDropdown.vue'
import BaseDropdownItem from '@/scripts/components/base/BaseDropdownItem.vue'
import { debounce } from 'lodash'
import { ref, reactive, computed, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useNotificationStore } from '@/scripts/stores/notification'
import { useDeliveryNoteStore } from '@/scripts/customer/stores/delivery-note'
import { useGlobalStore } from '@/scripts/customer/stores/global'

const route = useRoute()
const router = useRouter()

const deliveryNoteStore = useDeliveryNoteStore()
const globalStore = useGlobalStore()
const { tm, t } = useI18n()

const deliveryNote = reactive({})
const searchData = reactive({
  orderBy: '',
  orderByField: '',
  delivery_number: '',
})

const isSearching = ref(false)
const notificationStore = useNotificationStore()

const pageTitle = computed(() => {
  return deliveryNoteStore.selectedViewDeliveryNote
})

const getOrderBy = computed(() => {
  if (searchData.orderBy === 'asc' || searchData.orderBy == null) {
    return true
  }
  return false
})

const shareableLink = computed(() => {
  return deliveryNote.unique_hash
    ? `/delivery-notes/pdf/${deliveryNote.unique_hash}`
    : false
})

watch(route, () => {
  loadDeliveryNote()
})

loadDeliveryNotes()
loadDeliveryNote()

onSearch = debounce(onSearch, 500)

function hasActiveUrl(id) {
  return route.params.id == id
}

async function loadDeliveryNotes() {
  await deliveryNoteStore.searchDeliveryNotes(
    { limit: 'all' },
    globalStore.companySlug
  )

  setTimeout(() => {
    scrollToDeliveryNote()
  }, 500)
}

async function loadDeliveryNote() {
  if (route && route.params.id) {
    const response = await deliveryNoteStore.fetchViewDeliveryNote(
      { id: route.params.id },
      globalStore.companySlug
    )

    if (response.data) {
      Object.assign(deliveryNote, response.data.data)
    }
  }
}

function scrollToDeliveryNote() {
  const el = document.getElementById(`delivery-note-${route.params.id}`)

  if (el) {
    el.scrollIntoView({ behavior: 'smooth' })
    el.classList.add('shake')
  }
}

async function onSearch() {
  const data = {}

  if (
    searchData.delivery_number !== '' &&
    searchData.delivery_number !== null &&
    searchData.delivery_number !== undefined
  ) {
    data.delivery_number = searchData.delivery_number
  }

  if (searchData.orderBy !== null && searchData.orderBy !== undefined) {
    data.orderBy = searchData.orderBy
  }

  if (
    searchData.orderByField !== null &&
    searchData.orderByField !== undefined
  ) {
    data.orderByField = searchData.orderByField
  }

  isSearching.value = true
  try {
    const response = await deliveryNoteStore.searchDeliveryNotes(
      data,
      globalStore.companySlug
    )
    isSearching.value = false

    if (response.data.data) {
      deliveryNoteStore.deliveryNotes = response.data.data
    }
  } catch (error) {
    isSearching.value = false
  }
}

function sortData() {
  if (searchData.orderBy === 'asc') {
    searchData.orderBy = 'desc'
    onSearch()
    return true
  }
  searchData.orderBy = 'asc'
  onSearch()
  return true
}

function copyPdfUrl() {
  const pdfUrl = `${window.location.origin}/delivery-notes/pdf/${deliveryNote?.unique_hash}`
  navigator.clipboard?.writeText(pdfUrl)
  notificationStore.showNotification({
    type: 'success',
    message: tm('general.copied_pdf_url_clipboard'),
  })
}
</script>
