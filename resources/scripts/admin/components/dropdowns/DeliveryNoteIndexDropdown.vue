<template>
  <BaseDropdown width-class="w-72">
    <template #activator>
      <BaseButton
        v-if="route.name === 'delivery-notes.view'"
        variant="primary"
      >
        <BaseIcon name="DotsHorizontalIcon" class="text-white" />
      </BaseButton>
      <BaseIcon v-else class="text-gray-500" name="DotsHorizontalIcon" />
    </template>

    <!-- Mark as Delivered -->
    <BaseDropdownItem
      v-if="
        row.status === 'DRAFT' &&
        !row.already_billed &&
        userStore.hasAbilities(abilities.EDIT_DELIVERY_NOTE)
      "
      @click="onMarkAsDelivered(row.id)"
    >
      <BaseIcon
        name="CheckCircleIcon"
        class="w-5 h-5 mr-3 text-gray-400 group-hover:text-gray-500"
      />
      {{ $t('delivery-notes.mark_as_delivered') }}
    </BaseDropdownItem>

    <!-- Cancel -->
    <BaseDropdownItem
      v-if="
        row.status !== 'CANCELLED' &&
        !row.already_billed &&
        userStore.hasAbilities(abilities.EDIT_DELIVERY_NOTE)
      "
      @click="onCancel(row.id)"
    >
      <BaseIcon
        name="XCircleIcon"
        class="w-5 h-5 mr-3 text-gray-400 group-hover:text-gray-500"
      />
      {{ $t('delivery-notes.cancel_delivery_note') }}
    </BaseDropdownItem>

    <!-- Send Delivery Note -->
    <BaseDropdownItem
      v-if="
        route.name !== 'delivery-notes.view' &&
        userStore.hasAbilities(abilities.SEND_DELIVERY_NOTE)
      "
      @click="sendDeliveryNote(row)"
    >
      <BaseIcon
        name="PaperAirplaneIcon"
        class="w-5 h-5 mr-3 text-gray-400 group-hover:text-gray-500"
      />
      {{ $t('delivery-notes.send_delivery_note') }}
    </BaseDropdownItem>

    <div
      v-if="hasStatusActions && hasDocumentActions"
      class="mx-4 my-1 border-t border-gray-200"
    />

    <!-- Edit Delivery Note (sólo en DRAFT, plan 05 §3) -->
    <router-link
      v-if="
        row.status === 'DRAFT' &&
        userStore.hasAbilities(abilities.EDIT_DELIVERY_NOTE)
      "
      :to="`/admin/delivery-notes/${row.id}/edit`"
    >
      <BaseDropdownItem>
        <BaseIcon
          name="PencilIcon"
          class="w-5 h-5 mr-3 text-gray-400 group-hover:text-gray-500"
        />
        {{ $t('general.edit') }}
      </BaseDropdownItem>
    </router-link>

    <!-- View Delivery Note -->
    <router-link
      v-if="
        route.name !== 'delivery-notes.view' &&
        userStore.hasAbilities(abilities.VIEW_DELIVERY_NOTE)
      "
      :to="`delivery-notes/${row.id}/view`"
    >
      <BaseDropdownItem>
        <BaseIcon
          name="EyeIcon"
          class="w-5 h-5 mr-3 text-gray-400 group-hover:text-gray-500"
        />
        {{ $t('general.view') }}
      </BaseDropdownItem>
    </router-link>

    <!-- Copy PDF url  -->
    <BaseDropdownItem
      v-if="route.name === 'delivery-notes.view'"
      @click="copyPdfUrl"
    >
      <BaseIcon
        name="LinkIcon"
        class="w-5 h-5 mr-3 text-gray-400 group-hover:text-gray-500"
      />
      {{ $t('general.copy_pdf_url') }}
    </BaseDropdownItem>

    <!-- Ver factura vinculada -->
    <router-link
      v-if="row.already_billed"
      :to="`/admin/invoices/${row.invoice_id}/view`"
    >
      <BaseDropdownItem>
        <BaseIcon
          name="DocumentTextIcon"
          class="w-5 h-5 mr-3 text-gray-400 group-hover:text-gray-500"
        />
        {{ $t('delivery-notes.view_invoice') }}
      </BaseDropdownItem>
    </router-link>

    <!-- Convert to Invoice -->
    <BaseDropdownItem
      v-if="
        row.status === 'DELIVERED' &&
        !row.already_billed &&
        userStore.hasAbilities(
          abilities.CONVERT_DELIVERY_NOTE_INVOICE,
        )
      "
      @click="convertToInvoice(row.id)"
    >
      <BaseIcon
        name="DocumentDuplicateIcon"
        class="w-5 h-5 mr-3 text-gray-400 group-hover:text-gray-500"
      />
      {{ $t('delivery-notes.convert_to_invoice') }}
    </BaseDropdownItem>

    <div
      v-if="(hasStatusActions || hasDocumentActions) && canDelete"
      class="mx-4 my-1 border-t border-gray-200"
    />

    <!-- Delete Delivery Note (sólo DRAFT + no facturado, plan 05 §3) -->
    <BaseDropdownItem
      v-if="
        row.status === 'DRAFT' &&
        !row.already_billed &&
        userStore.hasAbilities(abilities.DELETE_DELIVERY_NOTE)
      "
      @click="removeDeliveryNote(row.id)"
    >
      <BaseIcon
        name="TrashIcon"
        class="w-5 h-5 mr-3 text-gray-400 group-hover:text-gray-500"
      />
      {{ $t('general.delete') }}
    </BaseDropdownItem>
  </BaseDropdown>
</template>

<script setup>
import { useDeliveryNoteStore } from '@/scripts/admin/stores/delivery-note'
import { useNotificationStore } from '@/scripts/stores/notification'
import { useModalStore } from '@/scripts/stores/modal'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import { useDialogStore } from '@/scripts/stores/dialog'
import { inject, computed } from 'vue'
import { useUserStore } from '@/scripts/admin/stores/user'
import abilities from '@/scripts/admin/stub/abilities'

const props = defineProps({
  row: {
    type: Object,
    default: null,
  },

  table: {
    type: Object,
    default: null,
  },
})

const utils = inject('utils')

const deliveryNoteStore = useDeliveryNoteStore()
const modalStore = useModalStore()
const notificationStore = useNotificationStore()
const dialogStore = useDialogStore()
const userStore = useUserStore()

const { t } = useI18n()
const route = useRoute()
const router = useRouter()

const hasStatusActions = computed(() => {
  return (
    userStore.hasAbilities(abilities.EDIT_DELIVERY_NOTE) ||
    userStore.hasAbilities(abilities.SEND_DELIVERY_NOTE)
  )
})

const hasDocumentActions = computed(() => {
  return (
    (route.name !== 'delivery-notes.view' &&
      userStore.hasAbilities(abilities.VIEW_DELIVERY_NOTE)) ||
    route.name === 'delivery-notes.view' ||
    userStore.hasAbilities(abilities.CONVERT_DELIVERY_NOTE_INVOICE)
  )
})

const canDelete = computed(() =>
  userStore.hasAbilities(abilities.DELETE_DELIVERY_NOTE)
)

function removeDeliveryNote(id) {
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
        deliveryNoteStore
          .deleteDeliveryNote(id)
          .then((response) => {
            response = response
            props.table && props.table.refresh()

            deliveryNoteStore.$patch((state) => {
              state.selectedDeliveryNotes = []
              state.selectAllField = false
            })
          })
      }
    })
}

function onMarkAsDelivered(id) {
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
      const data = { id: id, status: 'DELIVERED' }
      if (response) {
        deliveryNoteStore.markAsDelivered(data).then((response) => {
          props.table && props.table.refresh()
        })
      }
    })
}

function onCancel(id) {
  dialogStore
    .openDialog({
      title: t('general.are_you_sure'),
      message: t('delivery-notes.confirm_cancel'),
      yesLabel: t('general.ok'),
      noLabel: t('general.cancel'),
      variant: 'primary',
      hideNoButton: false,
      size: 'lg',
    })
    .then((response) => {
      const data = { id: id, status: 'CANCELLED' }
      if (response) {
        deliveryNoteStore.cancelDeliveryNote(data).then((response) => {
          props.table && props.table.refresh()
        })
      }
    })
}

function sendDeliveryNote(deliveryNote) {
  modalStore.openModal({
    title: t('delivery-notes.send_delivery_note'),
    componentName: 'SendDeliveryNoteModal',
    id: deliveryNote.id,
    data: deliveryNote,
    variant: 'lg',
  })
}

function convertToInvoice(id) {
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
        deliveryNoteStore.convertToInvoice(id).then((response) => {
          if (response && response.data && response.data.data) {
            router.push(`/admin/invoices/${response.data.data.id}/edit`)
          }
        })
      }
    })
}

function copyPdfUrl() {
  const pdfUrl = `${window.location.origin}/delivery-notes/pdf/${props.row.unique_hash}`

  utils.copyTextToClipboard(pdfUrl)
  notificationStore.showNotification({
    type: 'success',
    message: t('general.copied_pdf_url_clipboard'),
  })
}
</script>
