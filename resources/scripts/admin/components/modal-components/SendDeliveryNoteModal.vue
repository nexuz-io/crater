<template>
  <BaseModal
    :show="modalActive"
    @close="closeSendDeliveryNoteModal"
    @open="setInitialData"
  >
    <template #header>
      <div class="flex justify-between w-full">
        {{ modalStore.title }}
        <BaseIcon
          name="XIcon"
          class="h-6 w-6 text-gray-500 cursor-pointer"
          @click="closeSendDeliveryNoteModal"
        />
      </div>
    </template>

    <form v-if="!isPreview" action="">
      <div class="px-8 py-8 sm:p-6">
        <BaseInputGrid layout="one-column">
          <BaseInputGroup
            :label="$t('general.from')"
            required
            :error="v$.from.$error && v$.from.$errors[0].$message"
          >
            <BaseInput
              v-model="deliveryNoteMailForm.from"
              type="text"
              :invalid="v$.from.$error"
              @input="v$.from.$touch()"
            />
          </BaseInputGroup>
          <BaseInputGroup
            :label="$t('general.to')"
            required
            :error="v$.to.$error && v$.to.$errors[0].$message"
          >
            <BaseInput
              v-model="deliveryNoteMailForm.to"
              type="text"
              :invalid="v$.to.$error"
              @input="v$.to.$touch()"
            />
          </BaseInputGroup>
          <BaseInputGroup
            :label="$t('general.subject')"
            required
            :error="v$.subject.$error && v$.subject.$errors[0].$message"
          >
            <BaseInput
              v-model="deliveryNoteMailForm.subject"
              type="text"
              :invalid="v$.subject.$error"
              @input="v$.subject.$touch()"
            />
          </BaseInputGroup>
          <BaseInputGroup :label="$t('general.body')" required>
            <BaseCustomInput
              v-model="deliveryNoteMailForm.body"
              :fields="deliveryNoteMailFields"
            />
          </BaseInputGroup>
        </BaseInputGrid>
      </div>
      <div
        class="z-0 flex justify-end p-4 border-t border-gray-200 border-solid"
      >
        <BaseButton
          class="mr-3"
          variant="primary-outline"
          type="button"
          @click="closeSendDeliveryNoteModal"
        >
          {{ $t('general.cancel') }}
        </BaseButton>

        <BaseButton
          :loading="isLoading"
          :disabled="isLoading"
          variant="primary"
          type="button"
          class="mr-3"
          @click="submitForm"
        >
          <BaseIcon v-if="!isLoading" name="PhotographIcon" class="h-5 mr-2" />
          {{ $t('general.preview') }}
        </BaseButton>
      </div>
    </form>
    <div v-else>
      <div class="my-6 mx-4 border border-gray-200 relative">
        <BaseButton
          class="absolute top-4 right-4"
          :disabled="isLoading"
          variant="primary-outline"
          @click="cancelPreview"
        >
          <BaseIcon name="PencilIcon" class="h-5 mr-2" />
          Edit
        </BaseButton>
        <iframe
          :src="templateUrl"
          frameborder="0"
          class="w-full"
          style="min-height: 500px"
        ></iframe>
      </div>

      <div
        class="z-0 flex justify-end p-4 border-t border-gray-200 border-solid"
      >
        <BaseButton
          class="mr-3"
          variant="primary-outline"
          type="button"
          @click="closeSendDeliveryNoteModal"
        >
          {{ $t('general.cancel') }}
        </BaseButton>
        <BaseButton
          :loading="isLoading"
          :disabled="isLoading"
          variant="primary"
          type="button"
          @click="submitForm"
        >
          <BaseIcon v-if="!isLoading" name="PaperAirplaneIcon" class="mr-2" />
          {{ $t('general.send') }}
        </BaseButton>
      </div>
    </div>
  </BaseModal>
</template>

<script setup>
import { computed, ref, reactive } from 'vue'
import { useI18n } from 'vue-i18n'
import { required, email, helpers } from '@vuelidate/validators'
import useVuelidate from '@vuelidate/core'
import { useModalStore } from '@/scripts/stores/modal'
import { useDeliveryNoteStore } from '@/scripts/admin/stores/delivery-note'
import { useNotificationStore } from '@/scripts/stores/notification'
import { useCompanyStore } from '@/scripts/admin/stores/company'

const modalStore = useModalStore()
const deliveryNoteStore = useDeliveryNoteStore()
const notificationStore = useNotificationStore()
const companyStore = useCompanyStore()

const { t } = useI18n()
const isLoading = ref(false)
const templateUrl = ref('')
const isPreview = ref(false)

const deliveryNoteMailFields = ref([
  'customer',
  'customerCustom',
  'delivery_note',
  'deliveryNoteCustom',
  'company',
])

let deliveryNoteMailForm = reactive({
  id: null,
  from: null,
  to: null,
  subject: 'New Delivery Note',
  body: null,
})

const emit = defineEmits(['update'])

const modalActive = computed(() => {
  return modalStore.active && modalStore.componentName === 'SendDeliveryNoteModal'
})

const modalData = computed(() => {
  return modalStore.data
})

const rules = {
  from: {
    required: helpers.withMessage(t('validation.required'), required),
    email: helpers.withMessage(t('validation.email_incorrect'), email),
  },
  to: {
    required: helpers.withMessage(t('validation.required'), required),
    email: helpers.withMessage(t('validation.email_incorrect'), email),
  },
  subject: {
    required: helpers.withMessage(t('validation.required'), required),
  },
  body: {
    required: helpers.withMessage(t('validation.required'), required),
  },
}

const v$ = useVuelidate(
  rules,
  computed(() => deliveryNoteMailForm)
)

function cancelPreview() {
  isPreview.value = false
}

async function setInitialData() {
  const admin = await companyStore.fetchBasicMailConfig()

  deliveryNoteMailForm.id = modalStore.id

  if (admin.data) {
    deliveryNoteMailForm.from = admin.data.from_mail
  }

  if (modalData.value) {
    deliveryNoteMailForm.to = modalData.value.customer.email
  }

  if (companyStore.selectedCompanySettings) {
    deliveryNoteMailForm.body =
      companyStore.selectedCompanySettings.delivery_note_mail_body
  }
}

async function submitForm() {
  v$.value.$touch()

  if (v$.value.$invalid) {
    return true
  }

  try {
    isLoading.value = true

    if (!isPreview.value) {
      const previewResponse = await deliveryNoteStore.previewDeliveryNote(
        deliveryNoteMailForm
      )
      isLoading.value = false

      isPreview.value = true
      const blob = new Blob([previewResponse.data], { type: 'text/html' })
      templateUrl.value = URL.createObjectURL(blob)

      return
    }

    const response = await deliveryNoteStore.sendDeliveryNote(
      deliveryNoteMailForm
    )

    isLoading.value = false

    if (response.data && response.data.success) {
      emit('update')
      closeSendDeliveryNoteModal()
      return true
    }
  } catch (error) {
    console.error(error)
    isLoading.value = false
    notificationStore.showNotification({
      type: 'error',
      message: t('delivery-notes.something_went_wrong'),
    })
  }
}

function closeSendDeliveryNoteModal() {
  modalStore.closeModal()

  setTimeout(() => {
    v$.value.$reset()
    isPreview.value = false
    templateUrl.value = null
  }, 300)
}
</script>
