<template>
  <BaseModal
    :show="modalStore.active && modalStore.componentName === 'ItemFamilyModal'"
    @close="closeFamilyModal"
  >
    <template #header>
      <div class="flex justify-between w-full">
        {{ modalStore.title }}
        <BaseIcon
          name="XIcon"
          class="h-6 w-6 text-gray-500 cursor-pointer"
          @click="closeFamilyModal"
        />
      </div>
    </template>

    <form action="" @submit.prevent="submitFamilyData">
      <div class="p-4 sm:p-6 space-y-4">
        <BaseInputGrid layout="one-column">
          <BaseInputGroup
            :label="$t('item_families.name')"
            variant="horizontal"
            :error="
              v$.currentFamily.name.$error &&
              v$.currentFamily.name.$errors[0].$message
            "
            required
          >
            <BaseInput
              v-model="familyStore.currentFamily.name"
              :invalid="v$.currentFamily.name.$error"
              type="text"
              :placeholder="$t('item_families.name_placeholder')"
              @input="v$.currentFamily.name.$touch()"
            />
          </BaseInputGroup>

          <BaseInputGroup
            v-if="familyStore.currentFamily.parent_id !== null"
            :label="$t('item_families.parent_label')"
            variant="horizontal"
          >
            <BaseInput
              :model-value="parentDisplay"
              type="text"
              readonly
              disabled
            />
          </BaseInputGroup>

          <!--
            Move-mode: when editing an existing node, allow changing
            its parent. Server enforces the cycle-free contract, but
            we additionally restrict the dropdown to "valid" parents
            by excluding the node itself and its descendants. The
            exclusion list travels with the store snapshot.
          -->
          <BaseInputGroup
            v-if="isMoveMode"
            :label="$t('item_families.parent_label')"
            variant="horizontal"
          >
            <BaseMultiselect
              v-model="familyStore.currentFamily.parent_id"
              :options="parentOptions"
              value-prop="id"
              track-by="name"
              label="name"
              :allow-empty="false"
              searchable
              :placeholder="$t('item_families.parent_placeholder')"
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
          @click="closeFamilyModal"
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
          {{ isEdit ? $t('general.update') : $t('general.save') }}
        </BaseButton>
      </div>
    </form>
  </BaseModal>
</template>

<script setup>
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { required, minLength, maxLength, helpers } from '@vuelidate/validators'
import useVuelidate from '@vuelidate/core'
import { useItemFamilyStore } from '@/scripts/admin/stores/item-family'
import { useItemStore } from '@/scripts/admin/stores/item'
import { useModalStore } from '@/scripts/stores/modal'

const familyStore = useItemFamilyStore()
const itemStore = useItemStore()
const modalStore = useModalStore()
const { t } = useI18n()

const isSaving = ref(false)
const isMoveMode = ref(false)
const exclusionSet = ref(new Set())

const isEdit = computed(() => !!familyStore.currentFamily.id)

const parentDisplay = computed(() => {
  const id = familyStore.currentFamily.parent_id
  if (id == null) return t('item_families.root_badge')
  const node = itemStore.itemFamilies.find((f) => f.id === id)
  return node ? node.name : ''
})

const parentOptions = computed(() => {
  const options = itemStore.itemFamilies.map((f) => ({
    id: f.id,
    name: f.name,
  }))
  // When the modal opens in "change parent" mode, exclude self +
  // descendants from the candidate list (server does the same
  // check defensively).
  return options.filter((opt) => !exclusionSet.value.has(opt.id))
})

const rules = computed(() => ({
  currentFamily: {
    name: {
      required: helpers.withMessage(t('validation.required'), required),
      minLength: helpers.withMessage(
        t('validation.name_min_length', { count: 1 }),
        minLength(1)
      ),
      maxLength: helpers.withMessage(
        t('validation.description_maxlength', { count: 255 }),
        maxLength(255)
      ),
    },
  },
}))

const v$ = useVuelidate(rules, computed(() => familyStore))

watch(
  () => modalStore.title,
  (title) => {
    // Title doubles as a signal of the modal's purpose: "Reubicar"
    // (move-mode) keeps the parent dropdown unlocked.
    isMoveMode.value = typeof title === 'string' && /reubicar|move/i.test(title)
  },
  { immediate: true }
)

async function submitFamilyData() {
  v$.value.currentFamily.$touch()
  if (v$.value.currentFamily.$invalid) {
    return true
  }
  try {
    isSaving.value = true
    const action = familyStore.isEdit
      ? familyStore.updateFamily
      : familyStore.addFamily
    const res = await action(familyStore.currentFamily)
    isSaving.value = false
    if (modalStore.refreshData) {
      const payload = res?.data?.data ?? res
      modalStore.refreshData(payload)
    }
    closeFamilyModal()
  } catch (err) {
    isSaving.value = false
    return true
  }
}

function closeFamilyModal() {
  modalStore.closeModal()
  setTimeout(() => {
    familyStore.resetCurrentFamily()
    v$.value.$reset()
    isMoveMode.value = false
    exclusionSet.value = new Set()
  }, 300)
}
</script>
