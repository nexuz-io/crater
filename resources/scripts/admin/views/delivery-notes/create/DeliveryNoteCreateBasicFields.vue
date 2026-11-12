<template>
  <div
    class="
      grid grid-cols-1 gap-y-5 gap-x-4
      mt-6 mb-8
      lg:grid-cols-12 lg:gap-x-6 lg:gap-y-0
    "
  >
    <BaseCustomerSelectPopup
      v-model="deliveryNoteStore.newDeliveryNote.customer"
      :valid="v.customer_id"
      :content-loading="isLoading"
      type="delivery-note"
      class="lg:col-span-6"
    />

    <div
      class="
        grid grid-cols-1 gap-y-5 gap-x-4
        p-5
        bg-surface
        border border-line-light border-solid
        rounded-2xl
        shadow-card
        sm:grid-cols-2
        lg:col-span-6
        lg:grid-cols-2 lg:self-start
      "
    >
      <BaseInputGroup
        :label="$t('delivery-notes.delivery_date')"
        :content-loading="isLoading"
        required
        :error="
          v.delivery_date.$error && v.delivery_date.$errors[0].$message
        "
      >
        <BaseDatePicker
          v-model="deliveryNoteStore.newDeliveryNote.delivery_date"
          :content-loading="isLoading"
          :calendar-button="true"
          calendar-button-icon="calendar"
        />
      </BaseInputGroup>

      <BaseInputGroup
        :label="$t('delivery-notes.delivery_address')"
        :content-loading="isLoading"
      >
        <BaseTextarea
          v-model="deliveryNoteStore.newDeliveryNote.delivery_address"
          :placeholder="$t('delivery-notes.delivery_address_placeholder')"
          rows="3"
        />
      </BaseInputGroup>

      <BaseInputGroup
        :label="$t('delivery-notes.delivery_note_number')"
        :content-loading="isLoading"
        required
        :error="
          v.delivery_number.$error && v.delivery_number.$errors[0].$message
        "
      >
        <BaseInput
          v-model="deliveryNoteStore.newDeliveryNote.delivery_number"
          :content-loading="isLoading"
        />
      </BaseInputGroup>

      <ExchangeRateConverter
        :store="deliveryNoteStore"
        store-prop="newDeliveryNote"
        :v="v"
        :is-loading="isLoading"
        :is-edit="isEdit"
        :customer-currency="deliveryNoteStore.newDeliveryNote.currency_id"
      />
    </div>
  </div>
</template>

<script setup>
import { useDeliveryNoteStore } from '@/scripts/admin/stores/delivery-note'
import ExchangeRateConverter from '@/scripts/admin/components/estimate-invoice-common/ExchangeRateConverter.vue'

const props = defineProps({
  v: {
    type: Object,
    default: null,
  },
  isLoading: {
    type: Boolean,
    default: false,
  },
  isEdit: {
    type: Boolean,
    default: false,
  },
})

const deliveryNoteStore = useDeliveryNoteStore()
</script>
