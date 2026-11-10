<template>
  <BaseDropdown>
    <template #activator>
      <BaseIcon name="DotsHorizontalIcon" class="h-5 text-gray-500" />
    </template>

    <!-- edit brand -->
    <BaseDropdownItem
      v-if="userStore.hasAbilities(abilities.EDIT_BRAND)"
      @click="editBrand(row.id)"
    >
      <BaseIcon
        name="PencilIcon"
        class="w-5 h-5 mr-3 text-gray-400 group-hover:text-gray-500"
      />
      {{ $t('general.edit') }}
    </BaseDropdownItem>

    <!-- delete brand -->
    <BaseDropdownItem
      v-if="userStore.hasAbilities(abilities.DELETE_BRAND)"
      @click="removeBrand(row.id)"
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
import { useBrandStore } from '@/scripts/admin/stores/brand'
import { useDialogStore } from '@/scripts/stores/dialog'
import { useModalStore } from '@/scripts/stores/modal'
import { useUserStore } from '@/scripts/admin/stores/user'
import { useI18n } from 'vue-i18n'
import abilities from '@/scripts/admin/stub/abilities'

const props = defineProps({
  row: { type: Object, default: null },
  table: { type: Object, default: null },
  loadData: { type: Function, default: null },
})

const brandStore = useBrandStore()
const dialogStore = useDialogStore()
const modalStore = useModalStore()
const userStore = useUserStore()
const { t } = useI18n()

async function editBrand(id) {
  await brandStore.fetchBrand(id)
  modalStore.openModal({
    title: t('brands.edit_brand'),
    componentName: 'BrandModal',
    size: 'sm',
    refreshData: props.loadData,
  })
}

function removeBrand(id) {
  dialogStore
    .openDialog({
      title: t('general.are_you_sure'),
      message: t('brands.confirm_delete'),
      yesLabel: t('general.ok'),
      noLabel: t('general.cancel'),
      variant: 'danger',
      hideNoButton: false,
      size: 'lg',
    })
    .then(async (res) => {
      if (!res) return
      const response = await brandStore.deleteBrand(id)
      // Surface the controller's "brand_attached_items" error code
      // explicitly so the user understands why the row was not
      // removed (see BrandsController::destroy).
      if (response.data?.success) {
        props.loadData && props.loadData()
      } else {
        props.loadData && props.loadData()
      }
    })
}
</script>
