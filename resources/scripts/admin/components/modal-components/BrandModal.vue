<template>
  <BaseModal
    :show="modalStore.active && modalStore.componentName === 'BrandModal'"
    @close="closeBrandModal"
  >
    <template #header>
      <div class="flex justify-between w-full">
        {{ modalStore.title }}
        <BaseIcon
          name="XIcon"
          class="h-6 w-6 text-gray-500 cursor-pointer"
          @click="closeBrandModal"
        />
      </div>
    </template>

    <form action="" @submit.prevent="submitBrandData">
      <div class="p-4 sm:p-6 space-y-4">
        <BaseInputGrid layout="one-column">
          <BaseInputGroup
            :label="$t('brands.name')"
            variant="horizontal"
            :error="
              v$.currentBrand.name.$error &&
              v$.currentBrand.name.$errors[0].$message
            "
            required
          >
            <BaseInput
              v-model="brandStore.currentBrand.name"
              :invalid="v$.currentBrand.name.$error"
              type="text"
              @input="v$.currentBrand.name.$touch()"
            />
          </BaseInputGroup>
        </BaseInputGrid>
      </div>

      <div
        class="
          z-0
          flex
          justify-end
          p-4
          bg-gray-50 border-modal-bg
        "
      >
        <BaseButton
          class="mr-3 text-sm"
          variant="primary-outline"
          type="button"
          @click="closeBrandModal"
        >
          {{ $t('general.cancel') }}
        </BaseButton>
        <BaseButton
          :loading="isSaving"
          :disabled="isSaving"
          variant="primary"
          type="submit"
        >
          <template #left="slotProps">
            <BaseIcon
              v-if="!isSaving"
              name="SaveIcon"
              :class="slotProps.class"
            />
          </template>
          {{ brandStore.isEdit ? $t('general.update') : $t('general.save') }}
        </BaseButton>
      </div>
    </form>
  </BaseModal>
</template>

<script setup>
import { computed } from 'vue'
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useBrandStore } from '@/scripts/admin/stores/brand'
import { useModalStore } from '@/scripts/stores/modal'
import { required, minLength, maxLength, helpers } from '@vuelidate/validators'
import useVuelidate from '@vuelidate/core'

const brandStore = useBrandStore()
const modalStore = useModalStore()
const { t } = useI18n()

const isSaving = ref(false)

const rules = computed(() => ({
  currentBrand: {
    name: {
      required: helpers.withMessage(t('validation.required'), required),
      minLength: helpers.withMessage(
        t('validation.name_min_length', { count: 3 }),
        minLength(3)
      ),
      maxLength: helpers.withMessage(
        t('validation.description_maxlength', { count: 255 }),
        maxLength(255)
      ),
    },
  },
}))

const v$ = useVuelidate(rules, computed(() => brandStore))

async function submitBrandData() {
  v$.value.currentBrand.$touch()
  if (v$.value.currentBrand.$invalid) {
    return true
  }
  try {
    isSaving.value = true
    const action = brandStore.isEdit ? brandStore.updateBrand : brandStore.addBrand
    const res = await action(brandStore.currentBrand)
    isSaving.value = false
    // `refreshData` is the table.fetch the brand admin page set, so
    // created brands immediately show up in the listing.
    if (modalStore.refreshData) {
      const payload = res?.data?.data ?? res
      modalStore.refreshData(payload)
    }
    closeBrandModal()
  } catch (err) {
    isSaving.value = false
    return true
  }
}

function closeBrandModal() {
  modalStore.closeModal()
  setTimeout(() => {
    brandStore.resetCurrentBrand()
    v$.value.$reset()
  }, 300)
}
</script>
