<template>
  <DeliveryNotesTabDeliveryNoteNumber />

  <BaseDivider class="mt-6 mb-2" />

  <BaseInputGroup
    :label="$t('delivery-notes.delivery_note_default_terms_label')"
    :content-loading="isLoading"
  >
    <BaseCustomInput
      v-model="form.terms_and_conditions"
      :fields="termsFields"
      @update:modelValue="onUpdateTermsAndConditions"
    />
  </BaseInputGroup>

  <BaseDivider class="my-8" />

  <ul class="divide-y divide-gray-200">
    <BaseSwitchSection
      v-model="sendAsAttachmentField"
      :title="$t('delivery-notes.delivery_note_email_attachment')"
      :description="
        $t('delivery-notes.delivery_note_email_attachment_description')
      "
    />

    <BaseSwitchSection
      v-model="displayPricesField"
      :title="$t('delivery-notes.display_prices_on_pdf')"
      :description="
        $t('delivery-notes.display_prices_on_pdf_description')
      "
    />
  </ul>
</template>

<script setup>
import { computed, reactive, inject, ref, onMounted } from 'vue'
import { useCompanyStore } from '@/scripts/admin/stores/company'
import DeliveryNotesTabDeliveryNoteNumber from './DeliveryNotesTabDeliveryNoteNumber.vue'

/**
 * Plan 05 §7 + plan 09 §F11 — gemelo estructural de `InvoicesTab.vue`,
 * `EstimatesTab.vue` y `PaymentsTab.vue`.
 *
 * Tres bloques configurables:
 *  1. Numeración (DeliveryNotesTabDeliveryNoteNumber): series
 *     default "DN", formato `{{SERIES:DN}}{{DELIMITER:-}}{{SEQUENCE:6}}`,
 *     prefijo customer/sequence_number.
 *  2. Términos y condiciones por defecto del albarán: idéntico al de
 *     Estimate/Invoice pero contra `delivery_note_default_terms_and_conditions`.
 *  3. Switches booleanos:
 *     - delivery_note_email_attachment   (¿adjuntar PDF al email?)
 *     - delivery_note_display_prices     (plan 05 §1: precios ocultos
 *       por defecto en el PDF; este switch los activa.)
 */
const utils = inject('utils')
const companyStore = useCompanyStore()

const isLoading = ref(true)

const termsFields = ref([
  'customer',
  'company',
  'customerCustom',
  'delivery_note',
])

const form = reactive({
  terms_and_conditions: '',
})

const sendAsAttachmentField = computed({
  get: () => currentSettings.delivery_note_email_attachment === 'YES',
  set: async (val) => {
    const value = val ? 'YES' : 'NO'
    currentSettings.delivery_note_email_attachment = value
    await utils.updateSettings([
      'delivery_note_email_attachment',
    ])
  },
})

const displayPricesField = computed({
  get: () => currentSettings.delivery_note_display_prices === 'YES',
  set: async (val) => {
    const value = val ? 'YES' : 'NO'
    currentSettings.delivery_note_display_prices = value
    await utils.updateSettings([
      'delivery_note_display_prices',
    ])
  },
})

async function onUpdateTermsAndConditions() {
  currentSettings.delivery_note_default_terms_and_conditions =
    form.terms_and_conditions
  await utils.updateSettings([
    'delivery_note_default_terms_and_conditions',
  ])
}

onMounted(() => {
  form.terms_and_conditions =
    currentSettings.delivery_note_default_terms_and_conditions || ''
  isLoading.value = false
})

// Mirror of `companyStore.selectedCompanySettings`. We keep our own
// reactive snapshot to avoid clobbering the live store during partial
// saves, matching the pattern used by InvoicesTab / EstimatesTab.
const currentSettings = reactive({})
Object.assign(
  currentSettings,
  companyStore.selectedCompanySettings || {}
)
</script>
