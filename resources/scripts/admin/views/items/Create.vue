<template>
  <BasePage>
    <BasePageHeader :title="pageTitle">
      <BaseBreadcrumb>
        <BaseBreadcrumbItem :title="$t('general.home')" to="dashboard" />
        <BaseBreadcrumbItem :title="$t('items.services')" to="/admin/items" />
        <BaseBreadcrumbItem :title="pageTitle" to="#" active />
      </BaseBreadcrumb>
    </BasePageHeader>

    <ItemUnitModal />

    <form
      class="grid lg:grid-cols-2 mt-6"
      action="submit"
      @submit.prevent="submitItem"
    >
      <BaseCard class="w-full">
        <BaseInputGrid layout="one-column">
          <BaseInputGroup
            :label="$t('items.service_name')"
            :content-loading="isFetchingInitialData"
            required
            :error="
              v$.currentItem.name.$error &&
              v$.currentItem.name.$errors[0].$message
            "
          >
            <BaseInput
              v-model="itemStore.currentItem.name"
              :content-loading="isFetchingInitialData"
              :invalid="v$.currentItem.name.$error"
              @input="v$.currentItem.name.$touch()"
            />
          </BaseInputGroup>

          <BaseInputGroup
            :label="$t('items.active_service')"
            :content-loading="isFetchingInitialData"
          >
            <BaseSwitch
              v-model="itemStore.currentItem.is_active"
              :label-right="$t('items.active_service_help')"
              class="mt-2"
            />
          </BaseInputGroup>

          <BaseInputGroup
            :label="$t('items.description')"
            :content-loading="isFetchingInitialData"
            :error="
              v$.currentItem.description.$error &&
              v$.currentItem.description.$errors[0].$message
            "
          >
            <BaseTextarea
              v-model="itemStore.currentItem.description"
              :content-loading="isFetchingInitialData"
              name="description"
              rows="2"
              @input="v$.currentItem.description.$touch()"
            />
          </BaseInputGroup>

          <BaseInputGroup
            :label="$t('items.deliverables')"
            :content-loading="isFetchingInitialData"
          >
            <BaseTextarea
              v-model="itemStore.currentItem.deliverables"
              :content-loading="isFetchingInitialData"
              name="deliverables"
              rows="2"
            />
          </BaseInputGroup>

          <BaseInputGroup
            :content-loading="isFetchingInitialData"
            :label="$t('items.unit')"
          >
            <BaseMultiselect
              v-model="itemStore.currentItem.unit_id"
              :content-loading="isFetchingInitialData"
              label="name"
              :options="itemStore.itemUnits"
              value-prop="id"
              :placeholder="$t('items.select_a_unit')"
              searchable
              track-by="name"
            >
              <template #action>
                <BaseSelectAction @click="addItemUnit">
                  <BaseIcon
                    name="PlusIcon"
                    class="h-4 mr-2 -ml-2 text-center text-primary-400"
                  />
                  {{ $t('settings.customization.items.add_item_unit') }}
                </BaseSelectAction>
              </template>
            </BaseMultiselect>
          </BaseInputGroup>

          <BaseInputGroup
            :content-loading="isFetchingInitialData"
            :label="$t('items.brand')"
          >
            <BaseMultiselect
              v-model="itemStore.currentItem.brand_id"
              :content-loading="isFetchingInitialData"
              label="name"
              :options="brandOptions"
              value-prop="id"
              :placeholder="$t('items.select_a_brand')"
              :allow-empty="true"
              :clearable="true"
              searchable
              track-by="name"
            >
              <template
                v-if="userStore.hasAbilities(abilities.CREATE_BRAND)"
                #action
              >
                <BaseSelectAction @click="addItemBrand">
                  <BaseIcon
                    name="PlusIcon"
                    class="h-4 mr-2 -ml-2 text-center text-primary-400"
                  />
                  {{ $t('brands.add_new_brand_inline') }}
                </BaseSelectAction>
              </template>
            </BaseMultiselect>
          </BaseInputGroup>

          <BaseInputGroup
            :label="$t('items.unit_price')"
            :content-loading="isFetchingInitialData"
          >
            <BaseMoney
              v-model="price"
              :content-loading="isFetchingInitialData"
            />
          </BaseInputGroup>

          <BaseInputGroup
            v-if="isTaxPerItem"
            :label="$t('items.taxes')"
            :content-loading="isFetchingInitialData"
          >
            <BaseMultiselect
              v-model="taxes"
              :content-loading="isFetchingInitialData"
              :options="getTaxTypes"
              mode="tags"
              label="tax_name"
              class="w-full"
              value-prop="id"
              :can-deselect="false"
              :can-clear="false"
              searchable
              track-by="tax_name"
              object
            />
          </BaseInputGroup>

          <BaseInputGroup
            :label="$t('items.default_quantity')"
            :content-loading="isFetchingInitialData"
            :error="
              v$.currentItem.default_quantity.$error &&
              v$.currentItem.default_quantity.$errors[0].$message
            "
          >
            <BaseInput
              v-model="defaultQuantity"
              type="number"
              min="0"
              step="any"
              :content-loading="isFetchingInitialData"
              :invalid="v$.currentItem.default_quantity.$error"
              @input="v$.currentItem.default_quantity.$touch()"
            />
          </BaseInputGroup>

          <BaseInputGroup
            :label="$t('items.delivery_delay')"
            :content-loading="isFetchingInitialData"
            :error="
              v$.currentItem.delivery_delay.$error &&
              v$.currentItem.delivery_delay.$errors[0].$message
            "
          >
            <BaseTextarea
              v-model="itemStore.currentItem.delivery_delay"
              :content-loading="isFetchingInitialData"
              name="delivery_delay"
              rows="2"
              @input="v$.currentItem.delivery_delay.$touch()"
            />
          </BaseInputGroup>

          <BaseInputGroup
            :label="$t('items.terms')"
            :content-loading="isFetchingInitialData"
          >
            <BaseTextarea
              v-model="itemStore.currentItem.terms"
              :content-loading="isFetchingInitialData"
              name="terms"
              rows="2"
            />
          </BaseInputGroup>

          <div>
            <BaseButton
              :content-loading="isFetchingInitialData"
              type="submit"
              :loading="isSaving"
            >
              <template #left="slotProps">
                <BaseIcon
                  v-if="!isSaving"
                  name="SaveIcon"
                  :class="slotProps.class"
                />
              </template>

              {{ isEdit ? $t('items.update_service') : $t('items.save_service') }}
            </BaseButton>
          </div>
        </BaseInputGrid>
      </BaseCard>
    </form>
  </BasePage>
</template>

<script setup>
import { computed, ref } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useI18n } from 'vue-i18n'
import {
  required,
  minLength,
  numeric,
  minValue,
  maxLength,
  helpers,
} from '@vuelidate/validators'
import useVuelidate from '@vuelidate/core'
import { useItemStore } from '@/scripts/admin/stores/item'
import { useCompanyStore } from '@/scripts/admin/stores/company'
import { useTaxTypeStore } from '@/scripts/admin/stores/tax-type'
import { useModalStore } from '@/scripts/stores/modal'
import ItemUnitModal from '@/scripts/admin/components/modal-components/ItemUnitModal.vue'
import { useUserStore } from '@/scripts/admin/stores/user'
import abilities from '@/scripts/admin/stub/abilities'

const itemStore = useItemStore()
const taxTypeStore = useTaxTypeStore()
const modalStore = useModalStore()
const companyStore = useCompanyStore()
const { t } = useI18n()
const route = useRoute()
const router = useRouter()
const userStore = useUserStore()

const isSaving = ref(false)
const taxPerItem = ref(companyStore.selectedCompanySettings.tax_per_item)

let isFetchingInitialData = ref(false)

itemStore.$reset()
loadData()

const price = computed({
  get: () => itemStore.currentItem.price / 100,
  set: (value) => {
    itemStore.currentItem.price = Math.round(value * 100)
  },
})

const defaultQuantity = computed({
  get: () => itemStore.currentItem.default_quantity,
  set: (value) => {
    itemStore.currentItem.default_quantity =
      value === '' || value === null ? null : Number(value)
  },
})

const taxes = computed({
  get: () =>
    itemStore?.currentItem?.taxes?.map((tax) => {
      if (tax) {
        return {
          ...tax,
          tax_type_id: tax.id,
          tax_name: tax.name + ' (' + tax.percent + '%)',
        }
      }
    }),
  set: (value) => {
    itemStore.currentItem.taxes = value
  },
})

const isEdit = computed(() => route.name === 'items.edit')

const pageTitle = computed(() =>
  isEdit.value ? t('items.edit_service') : t('items.new_service')
)

const getTaxTypes = computed(() => {
  return taxTypeStore.taxTypes.map((tax) => {
    return {
      ...tax,
      tax_type_id: tax.id,
      tax_name: tax.name + ' (' + tax.percent + '%)',
    }
  })
})

const isTaxPerItem = computed(() => {
  // 1) Company policy wins: when the owner enabled "Tax Per Service" in
  //    Settings → Preferences, every item shows the Taxes multiselect —
  //    even articles that pre-date the toggle and have tax_per_item = 0
  //    in the DB.
  if (taxPerItem.value === 'YES') return true

  // 2) Backward compatibility: articles that already had taxes configured
  //    before the company-wide toggle existed keep showing the field so
  //    users can still edit them. (The 2021 migration marks tax_per_item=1
  //    only on items that already had taxes at that time.)
  return itemStore.currentItem.tax_per_item === 1
})

const rules = computed(() => {
  return {
    currentItem: {
      name: {
        required: helpers.withMessage(t('validation.required'), required),
        minLength: helpers.withMessage(
          t('validation.name_min_length', { count: 3 }),
          minLength(3)
        ),
      },

      description: {
        maxLength: helpers.withMessage(
          t('validation.description_maxlength'),
          maxLength(65000)
        ),
      },

      delivery_delay: {
        maxLength: helpers.withMessage(
          t('validation.delivery_delay_maxlength'),
          maxLength(255)
        ),
      },

      default_quantity: {
        positiveNumber: helpers.withMessage(
          t('validation.numbers_only'),
          (value) =>
            value === null ||
            value === '' ||
            (!isNaN(Number(value)) && Number(value) >= 0)
        ),
      },
    },
  }
})

const v$ = useVuelidate(rules, itemStore)

async function addItemUnit() {
  modalStore.openModal({
    title: t('settings.customization.items.add_item_unit'),
    componentName: 'ItemUnitModal',
    size: 'sm',
  })
}

// Brand list shown in the selector. The catalog may legitimately not
// include the brand already assigned to this item (e.g. it was the
// 51st entry when the limit was set elsewhere); we splice it in so
// the operator can see and replace the assignment without surprise.
const brandOptions = computed(() => {
  const list = [...itemStore.itemBrands]
  const currentId = itemStore.currentItem.brand_id

  if (currentId && ! list.some((b) => b.id === currentId)) {
    const inline = itemStore.currentItem.brand
    if (inline && inline.id) {
      list.unshift(inline)
    }
  }

  return list
})

async function addItemBrand() {
  modalStore.openModal({
    title: t('brands.add_brand'),
    componentName: 'BrandModal',
    size: 'sm',
    refreshData: async (created) => {
      const data = created?.data?.data ?? created
      if (!data) return
      // Reload the catalog and select the brand that the operator
      // just created so the form keeps its context (item data
      // untouched).
      await itemStore.fetchItemBrands({ limit: 'all' })
      itemStore.currentItem.brand_id = data.id
    },
  })
}

async function loadData() {
  isFetchingInitialData.value = true

  await itemStore.fetchItemUnits({ limit: 'all' })
  if (userStore.hasAbilities(abilities.VIEW_TAX_TYPE)) {
    await taxTypeStore.fetchTaxTypes({ limit: 'all' })
  }

  if (isEdit.value) {
    let id = route.params.id
    await itemStore.fetchItem(id)
    // Tax display policy is now fully driven by `isTaxPerItem` (unión
    // de la company setting + la flag per-item). No mutamos taxPerItem
    // acá — ver comentario en `isTaxPerItem`.
  }

  // Brand catalog is fetched every time the form opens. This is the
  // safe choice because the form may be the operator's first entry
  // point after subscribing to a new company: a stale or missing
  // catalog would silently strip the assignment on save. The brand
  // dropdown will fall back to the just-fetched item (see
  // `brandOptions`) so editing preserves even out-of-page results.
  await itemStore.fetchItemBrands({ limit: 'all' })

  isFetchingInitialData.value = false
}

async function submitItem() {
  v$.value.currentItem.$touch()

  if (v$.value.currentItem.$invalid) {
    return false
  }

  isSaving.value = true

  try {
    let data = {
      id: route.params.id,
      ...itemStore.currentItem,
    }

    if (itemStore.currentItem && itemStore.currentItem.taxes) {
      data.taxes = itemStore.currentItem.taxes.map((tax) => {
        return {
          tax_type_id: tax.tax_type_id,
          amount: price.value * tax.percent,
          percent: tax.percent,
          name: tax.name,
          collective_tax: 0,
        }
      })
    }

    const action = isEdit.value ? itemStore.updateItem : itemStore.addItem

    await action(data)
    isSaving.value = false
    router.push('/admin/items')
    closeItemModal()
  } catch (err) {
    isSaving.value = false
    return
  }
  function closeItemModal() {
    modalStore.closeModal()
    setTimeout(() => {
      itemStore.resetCurrentItem()
      modalStore.$reset()
      v$.value.$reset()
    }, 300)
  }
}
</script>
