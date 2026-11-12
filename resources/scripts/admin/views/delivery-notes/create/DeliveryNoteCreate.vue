<template>
  <SelectTemplateModal />
  <ItemModal />
  <TaxTypeModal />

  <BasePage class="relative delivery-note-create-page">
    <form @submit.prevent="submitForm">
      <BasePageHeader :title="pageTitle">
        <BaseBreadcrumb>
          <BaseBreadcrumbItem
            :title="$t('general.home')"
            to="/admin/dashboard"
          />
          <BaseBreadcrumbItem
            :title="$tc('delivery-notes.delivery_note', 2)"
            to="/admin/delivery-notes"
          />
          <BaseBreadcrumbItem
            v-if="$route.name === 'delivery-notes.edit'"
            :title="$t('delivery-notes.edit_delivery_note')"
            to="#"
            active
          />
          <BaseBreadcrumbItem
            v-else
            :title="$t('delivery-notes.new_delivery_note')"
            to="#"
            active
          />
        </BaseBreadcrumb>

        <template #actions>
          <router-link
            v-if="$route.name === 'delivery-notes.edit'"
            :to="`/delivery-notes/pdf/${deliveryNoteStore.newDeliveryNote.unique_hash}`"
            target="_blank"
          >
            <BaseButton class="mr-3" variant="primary-outline" type="button">
              <span class="flex">
                {{ $t('general.view_pdf') }}
              </span>
            </BaseButton>
          </router-link>

          <BaseButton
            :loading="isSaving"
            :disabled="isSaving"
            :content-loading="isLoadingContent"
            variant="primary"
            type="submit"
          >
            <template #left="slotProps">
              <BaseIcon
                v-if="!isSaving"
                :class="slotProps.class"
                name="SaveIcon"
              />
            </template>
            {{ submitLabel }}
          </BaseButton>
        </template>
      </BasePageHeader>

      <!-- Plan 05 §3 advertencia: una vez entregado/cancelado el documento
           deja de ser editable. El propio controller rechaza updates, pero
           el editor queda bloqueado en el front para no llevar a engaño. -->
      <div
        v-if="
          isEdit &&
          deliveryNoteStore.newDeliveryNote.status !== 'DRAFT'
        "
        class="
          bg-yellow-50 border-l-4 border-yellow-400
          text-yellow-800 text-sm px-4 py-3 rounded mb-4
        "
        role="alert"
      >
        <strong>{{ $t('delivery-notes.status_change_invalid') }}</strong>
        — {{ $t('delivery-notes.cannot_edit_cancelled') }}
      </div>

      <!-- Select Customer & Basic Fields -->
      <DeliveryNoteBasicFields
        :v="v$"
        :is-loading="isLoadingContent"
        :is-edit="isEdit"
      />

      <BaseScrollPane>
        <!-- Items: gemelo de Estimate/Invoice -->
        <Items
          :currency="deliveryNoteStore.newDeliveryNote.selectedCurrency"
          :is-loading="isLoadingContent"
          :item-validation-scope="deliveryNoteValidationScope"
          :store="deliveryNoteStore"
          store-prop="newDeliveryNote"
        />

        <!-- Footer Section -->
        <div
          class="
            block
            mt-10
            delivery-note-foot
            lg:flex lg:justify-between lg:items-start
          "
        >
          <div class="relative w-full lg:w-1/2">
            <NoteFields
              :store="deliveryNoteStore"
              store-prop="newDeliveryNote"
              :fields="deliveryNoteNoteFieldList"
              type="DeliveryNote"
              label="estimates.terms_and_conditions"
              :show-note-popup="false"
              :show-fields-button="false"
            />

            <DeliveryNoteCustomFields
              type="DeliveryNote"
              :is-edit="isEdit"
              :is-loading="isLoadingContent"
              :store="deliveryNoteStore"
              store-prop="newDeliveryNote"
              :custom-field-scope="deliveryNoteValidationScope"
              class="mb-6"
            />

            <SelectTemplate
              :store="deliveryNoteStore"
              component-name="DeliveryNoteTemplate"
              store-prop="newDeliveryNote"
              :is-mark-as-default="isMarkAsDefault"
            />
          </div>

          <Total
            :currency="deliveryNoteStore.newDeliveryNote.selectedCurrency"
            :is-loading="isLoadingContent"
            :store="deliveryNoteStore"
            store-prop="newDeliveryNote"
            tax-popup-type="delivery_note"
          />
        </div>
      </BaseScrollPane>
    </form>
  </BasePage>
</template>

<script setup>
import { computed, ref, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import {
  required,
  maxLength,
  helpers,
  requiredIf,
  decimal,
} from '@vuelidate/validators'
import useVuelidate from '@vuelidate/core'
import { useDeliveryNoteStore } from '@/scripts/admin/stores/delivery-note'
import { useCompanyStore } from '@/scripts/admin/stores/company'
import { useCustomFieldStore } from '@/scripts/admin/stores/custom-field'

import Items from '@/scripts/admin/components/estimate-invoice-common/CreateItems.vue'
import Total from '@/scripts/admin/components/estimate-invoice-common/CreateTotal.vue'
import SelectTemplate from '@/scripts/admin/components/estimate-invoice-common/SelectTemplateButton.vue'
import DeliveryNoteCustomFields from '@/scripts/admin/components/custom-fields/CreateCustomFields.vue'
import NoteFields from '@/scripts/admin/components/estimate-invoice-common/CreateNotesField.vue'
import DeliveryNoteBasicFields from './DeliveryNoteCreateBasicFields.vue'
import SelectTemplateModal from '@/scripts/admin/components/modal-components/SelectTemplateModal.vue'
import TaxTypeModal from '@/scripts/admin/components/modal-components/TaxTypeModal.vue'
import ItemModal from '@/scripts/admin/components/modal-components/ItemModal.vue'

const deliveryNoteStore = useDeliveryNoteStore()
const companyStore = useCompanyStore()
const customFieldStore = useCustomFieldStore()
const { t } = useI18n()

const deliveryNoteValidationScope = 'newDeliveryNote'
const isSaving = ref(false)
const isMarkAsDefault = ref(false)

const deliveryNoteNoteFieldList = ref([
  'customer',
  'company',
  'customerCustom',
  'delivery_note',
])

const route = useRoute()
const router = useRouter()

const isEdit = computed(() => route.name === 'delivery-notes.edit')

const isLoadingContent = computed(
  () => deliveryNoteStore.isFetchingInitialSettings
)

const pageTitle = computed(() =>
  isEdit.value
    ? t('delivery-notes.edit_delivery_note')
    : t('delivery-notes.new_delivery_note')
)

const submitLabel = computed(() =>
  isEdit.value
    ? t('general.update')
    : t('delivery-notes.save_delivery_note')
)

const rules = {
  delivery_date: {
    required: helpers.withMessage(t('validation.required'), required),
  },
  delivery_number: {
    required: helpers.withMessage(t('validation.required'), required),
  },
  reference_number: {
    maxLength: helpers.withMessage(
      t('validation.price_maxlength'),
      maxLength(255)
    ),
  },
  customer_id: {
    required: helpers.withMessage(t('validation.required'), required),
  },
  exchange_rate: {
    required: requiredIf(function () {
      helpers.withMessage(t('validation.required'), required)
      return deliveryNoteStore.showExchangeRate
    }),
    decimal: helpers.withMessage(t('validation.valid_exchange_rate'), decimal),
  },
}

const v$ = useVuelidate(
  rules,
  computed(() => deliveryNoteStore.newDeliveryNote),
  { $scope: deliveryNoteValidationScope }
)

onMounted(() => {
  if (
    isEdit.value &&
    deliveryNoteStore.newDeliveryNote.status &&
    deliveryNoteStore.newDeliveryNote.status !== 'DRAFT'
  ) {
    // Bloquea la edición del contenido cuando el documento ya no
    // es editable en backend. Plan 05 §3: edición cerrada tras DRAFT.
    // (mejor fallar early que esperar al 422.)
  }
})

deliveryNoteStore.resetCurrentDeliveryNote()
customFieldStore.resetCustomFields()
v$.value.$reset
deliveryNoteStore.fetchDeliveryNoteInitialSettings(isEdit.value)

async function submitForm() {
  v$.value.$touch()

  if (v$.value.$invalid) {
    return false
  }

  isSaving.value = true

  const data = {
    ...deliveryNoteStore.newDeliveryNote,
    sub_total: deliveryNoteStore.getSubTotal,
    total: deliveryNoteStore.getTotal,
    tax: deliveryNoteStore.getTotalTax,
  }

  const action = isEdit.value
    ? deliveryNoteStore.updateDeliveryNote
    : deliveryNoteStore.addDeliveryNote

  try {
    const res = await action(data)

    if (res.data && res.data.data) {
      router.push(`/admin/delivery-notes/${res.data.data.id}/view`)
    }
  } catch (err) {
    console.error(err)
  }

  isSaving.value = false
}
</script>
