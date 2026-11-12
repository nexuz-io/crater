<template>
  <SendDeliveryNoteModal @update="updateSentDeliveryNote" />
  <BasePage v-if="deliveryNoteData" class="xl:pl-96 xl:ml-8">
    <BasePageHeader :title="pageTitle">
      <template #actions>
        <div class="mr-3 text-sm flex gap-3">
          <BaseButton
            v-if="
              deliveryNoteData.status === 'DRAFT' &&
              !deliveryNoteData.already_billed &&
              userStore.hasAbilities(abilities.EDIT_DELIVERY_NOTE)
            "
            :content-loading="isLoadingDeliveryNote"
            variant="primary-outline"
            @click="onMarkAsDelivered"
          >
            {{ $t('delivery-notes.mark_as_delivered') }}
          </BaseButton>
        </div>

        <BaseButton
          v-if="userStore.hasAbilities(abilities.SEND_DELIVERY_NOTE)"
          :content-loading="isLoadingDeliveryNote"
          variant="primary"
          class="text-sm"
          @click="onSendDeliveryNote"
        >
          {{ $t('delivery-notes.send_delivery_note') }}
        </BaseButton>

        <BaseButton
          v-if="
            deliveryNoteData.status === 'DELIVERED' &&
            !deliveryNoteData.already_billed &&
            userStore.hasAbilities(abilities.CONVERT_DELIVERY_NOTE_INVOICE)
          "
          :content-loading="isLoadingDeliveryNote"
          variant="primary"
          class="ml-3 text-sm"
          @click="onConvertToInvoice"
        >
          <BaseIcon name="DocumentDuplicateIcon" class="h-4 w-4 mr-1" />
          {{ $t('delivery-notes.convert_to_invoice') }}
        </BaseButton>

        <DeliveryNoteIndexDropdown class="ml-3" :row="deliveryNoteData" />
      </template>
    </BasePageHeader>

    <!-- Sidebar listado -->
    <div
      class="
        fixed
        top-0
        left-0
        hidden
        h-full
        pt-16
        pb-[6.4rem]
        bg-white
        w-88
        xl:block
      "
      style="transition: margin 0.3s ease"
      :class="globalStore.isSidebarCollapsed ? 'xl:ml-16' : 'ml-56 xl:ml-64'"
    >
      <div
        class="
          flex
          items-center
          justify-between
          px-4
          pt-8
          pb-2
          border border-gray-200 border-solid
          height-full
        "
      >
        <div class="mb-6">
          <BaseInput
            v-model="searchData.searchText"
            :placeholder="$t('general.search')"
            type="text"
            variant="gray"
            @input="onSearched()"
          >
            <template #right>
              <BaseIcon name="SearchIcon" class="text-gray-400" />
            </template>
          </BaseInput>
        </div>

        <div class="flex mb-6 ml-3" role="group" aria-label="First group">
          <BaseDropdown
            class="ml-3"
            position="bottom-start"
            width-class="w-45"
            position-class="left-0"
          >
            <template #activator>
              <BaseButton size="md" variant="gray">
                <BaseIcon name="FilterIcon" />
              </BaseButton>
            </template>

            <div
              class="
                px-4
                py-1
                pb-2
                mb-1 mb-2
                text-sm
                border-b border-gray-200 border-solid
              "
            >
              {{ $t('general.sort_by') }}
            </div>

            <BaseDropdownItem class="flex px-4 py-2 cursor-pointer">
              <BaseInputGroup class="-mt-3 font-normal">
                <BaseRadio
                  id="filter_delivery_date"
                  v-model="searchData.orderByField"
                  :label="$t('delivery-notes.delivery_date')"
                  size="sm"
                  name="filter"
                  value="delivery_date"
                  @update:modelValue="onSearched"
                />
              </BaseInputGroup>
            </BaseDropdownItem>

            <BaseDropdownItem class="flex px-4 py-2 cursor-pointer">
              <BaseInputGroup class="-mt-3 font-normal">
                <BaseRadio
                  id="filter_delivery_number"
                  v-model="searchData.orderByField"
                  :label="$t('delivery-notes.delivery_note_number')"
                  value="delivery_number"
                  size="sm"
                  name="filter"
                  @update:modelValue="onSearched"
                />
              </BaseInputGroup>
            </BaseDropdownItem>
          </BaseDropdown>

          <BaseButton class="ml-1" size="md" variant="gray" @click="sortData">
            <BaseIcon v-if="getOrderBy" name="SortAscendingIcon" />
            <BaseIcon v-else name="SortDescendingIcon" />
          </BaseButton>
        </div>
      </div>

      <div
        ref="deliveryNoteListSection"
        class="
          h-full
          overflow-y-scroll
          border-l border-gray-200 border-solid
          base-scroll
        "
      >
        <div v-for="(dn, index) in deliveryNoteList" :key="index">
          <router-link
            v-if="dn"
            :id="'delivery-note-' + dn.id"
            :to="`/admin/delivery-notes/${dn.id}/view`"
            :class="[
              'flex justify-between side-delivery-note p-4 cursor-pointer hover:bg-gray-100 items-center border-l-4 border-transparent',
              {
                'bg-gray-100 border-l-4 border-primary-500 border-solid':
                  hasActiveUrl(dn.id),
              },
            ]"
            style="border-bottom: 1px solid rgba(185, 193, 209, 0.41)"
          >
            <div class="flex-2">
              <BaseText
                :text="dn.customer.name"
                :length="30"
                class="
                  pr-2
                  mb-2
                  text-sm
                  not-italic
                  font-normal
                  leading-5
                  text-black
                  capitalize
                  truncate
                "
              />

              <div
                class="
                  mt-1
                  mb-2
                  text-xs
                  not-italic
                  font-medium
                  leading-5
                  text-gray-600
                "
              >
                {{ dn.delivery_number }}
              </div>

              <BaseDeliveryNoteStatusBadge
                :status="dn.status"
                class="px-1 text-xs"
              >
                {{ $t(`delivery-notes.${dn.status.toLowerCase()}`) }}
              </BaseDeliveryNoteStatusBadge>
            </div>

            <div class="flex-1 whitespace-nowrap right">
              <div
                class="
                  block
                  mb-2
                  text-xl
                  not-italic
                  font-semibold
                  leading-8
                  text-right text-gray-900
                "
              >
                #{{ dn.delivery_number }}
              </div>

              <div
                class="
                  text-sm
                  not-italic
                  font-normal
                  leading-5
                  text-right text-gray-600
                  dn-date
                "
              >
                {{ dn.formatted_delivery_date }}
              </div>
            </div>
          </router-link>
        </div>
        <div v-if="isLoading" class="flex justify-center p-4 items-center">
          <LoadingIcon class="h-6 m-1 animate-spin text-primary-400" />
        </div>
        <p
          v-if="!deliveryNoteList?.length && !isLoading"
          class="flex justify-center px-4 mt-5 text-sm text-gray-600"
        >
          {{ $t('delivery-notes.no_matching_delivery_notes') }}
        </p>
      </div>
    </div>

    <div
      class="flex flex-col min-h-0 mt-8 overflow-hidden"
      style="height: 75vh"
    >
      <iframe
        :src="`${shareableLink}`"
        class="
          flex-1
          border border-gray-400 border-solid
          rounded-md
          bg-white
          frame-style
        "
      />
    </div>
  </BasePage>
</template>

<script setup>
import { useI18n } from 'vue-i18n'
import { computed, reactive, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { debounce } from 'lodash'

import { useDeliveryNoteStore } from '@/scripts/admin/stores/delivery-note'
import { useModalStore } from '@/scripts/stores/modal'
import { useDialogStore } from '@/scripts/stores/dialog'
import { useGlobalStore } from '@/scripts/admin/stores/global'
import { useUserStore } from '@/scripts/admin/stores/user'

import DeliveryNoteIndexDropdown from '@/scripts/admin/components/dropdowns/DeliveryNoteIndexDropdown.vue'
import SendDeliveryNoteModal from '@/scripts/admin/components/modal-components/SendDeliveryNoteModal.vue'
import LoadingIcon from '@/scripts/components/icons/LoadingIcon.vue'

import abilities from '@/scripts/admin/stub/abilities'

const modalStore = useModalStore()
const deliveryNoteStore = useDeliveryNoteStore()
const dialogStore = useDialogStore()
const userStore = useUserStore()
const globalStore = useGlobalStore()

const { t } = useI18n()
const deliveryNoteData = ref(null)
const route = useRoute()
const router = useRouter()

const isLoading = ref(false)
const isLoadingDeliveryNote = ref(false)

const deliveryNoteList = ref(null)
const currentPageNumber = ref(1)
const lastPageNumber = ref(1)
const deliveryNoteListSection = ref(null)

const searchData = reactive({
  orderBy: null,
  orderByField: null,
  searchText: null,
})

const pageTitle = computed(() => deliveryNoteData.value.delivery_number)

const getOrderBy = computed(() => {
  if (searchData.orderBy === 'asc' || searchData.orderBy == null) {
    return true
  }
  return false
})

const shareableLink = computed(() => {
  return `/delivery-notes/pdf/${deliveryNoteData.value.unique_hash}`
})

watch(route, (to) => {
  if (to.name === 'delivery-notes.view') {
    loadDeliveryNote()
  }
})

loadDeliveryNotes()
loadDeliveryNote()

onSearched = debounce(onSearched, 500)

function hasActiveUrl(id) {
  return route.params.id == id
}

async function loadDeliveryNotes(pageNumber, fromScrollListener = false) {
  if (isLoading.value) {
    return
  }

  let params = {}
  if (
    searchData.searchText !== '' &&
    searchData.searchText !== null &&
    searchData.searchText !== undefined
  ) {
    params.search = searchData.searchText
  }

  if (searchData.orderBy !== null && searchData.orderBy !== undefined) {
    params.orderBy = searchData.orderBy
  }

  if (
    searchData.orderByField !== null &&
    searchData.orderByField !== undefined
  ) {
    params.orderByField = searchData.orderByField
  }

  isLoading.value = true
  const response = await deliveryNoteStore.fetchDeliveryNotes({
    page: pageNumber,
    ...params,
  })
  isLoading.value = false

  deliveryNoteList.value = deliveryNoteList.value ? deliveryNoteList.value : []
  deliveryNoteList.value = [...deliveryNoteList.value, ...response.data.data]

  currentPageNumber.value = pageNumber ? pageNumber : 1
  lastPageNumber.value = response.data.meta.last_page
  const dnFound = deliveryNoteList.value.find(
    (dn) => dn.id == route.params.id
  )

  if (
    fromScrollListener == false &&
    !dnFound &&
    currentPageNumber.value < lastPageNumber.value &&
    Object.keys(params).length === 0
  ) {
    loadDeliveryNotes(++currentPageNumber.value)
  }

  if (dnFound) {
    setTimeout(() => {
      if (fromScrollListener == false) {
        scrollToDeliveryNote()
      }
    }, 500)
  }
}

function scrollToDeliveryNote() {
  const el = document.getElementById(`delivery-note-${route.params.id}`)
  if (el) {
    el.scrollIntoView({ behavior: 'smooth' })
    el.classList.add('shake')
    addScrollListener()
  }
}

function addScrollListener() {
  deliveryNoteListSection.value.addEventListener('scroll', (ev) => {
    if (
      ev.target.scrollTop > 0 &&
      ev.target.scrollTop + ev.target.clientHeight >
        ev.target.scrollHeight - 200
    ) {
      if (currentPageNumber.value < lastPageNumber.value) {
        loadDeliveryNotes(++currentPageNumber.value, true)
      }
    }
  })
}

async function loadDeliveryNote() {
  isLoadingDeliveryNote.value = true
  const response = await deliveryNoteStore.fetchDeliveryNote(route.params.id)

  if (response.data) {
    isLoadingDeliveryNote.value = false
    deliveryNoteData.value = { ...response.data.data }
  }
}

async function onSearched() {
  deliveryNoteList.value = []
  loadDeliveryNotes()
}

function sortData() {
  if (searchData.orderBy === 'asc') {
    searchData.orderBy = 'desc'
    onSearched()
    return true
  }
  searchData.orderBy = 'asc'
  onSearched()
  return true
}

async function onMarkAsDelivered() {
  dialogStore
    .openDialog({
      title: t('general.are_you_sure'),
      message: t('delivery-notes.confirm_mark_as_delivered'),
      yesLabel: t('general.ok'),
      noLabel: t('general.cancel'),
      variant: 'primary',
      hideNoButton: false,
      size: 'lg',
    })
    .then((response) => {
      if (response) {
        deliveryNoteStore.markAsDelivered({
          id: deliveryNoteData.value.id,
          status: 'DELIVERED',
        })
        deliveryNoteData.value.status = 'DELIVERED'
        // Refresca la lista lateral.
        const idx = deliveryNoteList.value?.findIndex(
          (dn) => dn.id === deliveryNoteData.value.id
        )
        if (idx > -1 && deliveryNoteList.value[idx]) {
          deliveryNoteList.value[idx].status = 'DELIVERED'
        }
      }
    })
}

async function onSendDeliveryNote() {
  modalStore.openModal({
    title: t('delivery-notes.send_delivery_note'),
    componentName: 'SendDeliveryNoteModal',
    id: deliveryNoteData.value.id,
    data: deliveryNoteData.value,
  })
}

async function onConvertToInvoice() {
  dialogStore
    .openDialog({
      title: t('general.are_you_sure'),
      message: t('delivery-notes.convert_delivery_note_to_invoice_message'),
      yesLabel: t('general.ok'),
      noLabel: t('general.cancel'),
      variant: 'primary',
      hideNoButton: false,
      size: 'lg',
    })
    .then((res) => {
      if (res) {
        deliveryNoteStore.convertToInvoice(deliveryNoteData.value.id).then((response) => {
          if (response && response.data && response.data.data) {
            router.push(`/admin/invoices/${response.data.data.id}/edit`)
          }
        })
      }
    })
}

function updateSentDeliveryNote() {
  const pos = deliveryNoteList.value.findIndex(
    (dn) => dn.id === deliveryNoteData.value.id
  )

  // El endpoint `send` no cambia status, pero conservamos la
  // firma del callback por simetría con `updateSentEstimate`.
  if (deliveryNoteList.value[pos]) {
    deliveryNoteList.value[pos] = { ...deliveryNoteList.value[pos] }
  }
}
</script>
