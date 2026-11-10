<template>
  <div ref="companySwitchBar" class="relative rounded-lg">
    <CompanyModal />

    <button
      :id="triggerId"
      ref="triggerRef"
      type="button"
      :aria-label="$t('company_switcher.label')"
      :aria-haspopup="'menu'"
      :aria-expanded="isShow ? 'true' : 'false'"
      :aria-controls="panelId"
      :title="
        companyStore.selectedCompany && collapsed
          ? companyStore.selectedCompany.name
          : null
      "
      :class="[
        'group flex items-center w-full px-2.5 py-2 text-sm rounded-lg cursor-pointer transition-colors',
        'bg-white bg-opacity-[0.07] hover:bg-opacity-[0.13]',
        'focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-400',
        collapsed ? 'justify-center' : '',
      ]"
      @click="toggleDropdown"
      @keydown.esc="handleTriggerEscape"
    >
      <span
        v-if="companyStore.selectedCompany"
        class="
          flex
          items-center
          justify-center
          shrink-0
          overflow-hidden
          text-base
          font-semibold
          bg-chrome-raised
          rounded-md
          w-9
          h-9
          text-chrome-accent
        "
      >
        <span v-if="!companyStore.selectedCompany.logo || logoFailed">
          {{ initGenerator(companyStore.selectedCompany.name) }}
        </span>
        <img
          v-else
          :src="companyStore.selectedCompany.logo"
          alt="Company logo"
          class="w-full h-full object-contain"
          @error="logoFailed = true"
        />
      </span>

      <span
        v-else
        class="
          flex
          items-center
          justify-center
          shrink-0
          overflow-hidden
          bg-chrome-raised
          rounded-md
          w-9
          h-9
          text-chrome-muted
        "
        aria-hidden="true"
      >
        <BaseIcon name="BriefcaseIcon" class="h-5 w-5" />
      </span>

      <span
        v-if="companyStore.selectedCompany && !collapsed"
        class="flex-1 ml-3 font-medium text-chrome-fg truncate"
      >
        {{ companyStore.selectedCompany.name }}
      </span>

      <BaseIcon
        v-if="!collapsed"
        name="ChevronDownIcon"
        class="h-5 ml-2 shrink-0 text-chrome-muted"
      />
    </button>

    <transition
      enter-active-class="transition duration-200 ease-out"
      enter-from-class="translate-y-1 opacity-0"
      enter-to-class="translate-y-0 opacity-100"
      leave-active-class="transition duration-150 ease-in"
      leave-from-class="translate-y-0 opacity-100"
      leave-to-class="translate-y-1 opacity-0"
    >
      <div
        v-if="isShow"
        :id="panelId"
        ref="panelRef"
        role="menu"
        tabindex="-1"
        :aria-labelledby="triggerId"
        class="
          absolute
          z-30
          bg-surface
          rounded-lg
          border border-line-light
          shadow-card
        "
        :class="
          collapsed
            ? 'top-0 left-full ml-2 mt-0 w-[260px]'
            : 'left-0 right-auto mt-2 w-[260px]'
        "
        @keydown.esc="handlePanelEscape"
      >
        <div
          class="
            overflow-y-auto
            scrollbar-thin scrollbar-thumb-rounded-full
            w-full
            min-w-[200px]
            max-h-[60vh]
            scrollbar-thumb-gray-300 scrollbar-track-gray-10
            pb-2
          "
        >
          <label
            class="
              px-3
              py-2
              text-xs
              font-semibold
              text-gray-400
              mb-0.5
              block
              uppercase
            "
          >
            {{ $t('company_switcher.label') }}
          </label>

          <div
            v-if="!companyStore.companies.length"
            class="
              flex flex-col
              items-center
              justify-center
              p-2
              px-3
              mt-4
              text-base text-gray-400
            "
          >
            <BaseIcon name="ExclamationCircleIcon" class="h-5 text-gray-400" />
            {{ $t('company_switcher.no_results_found') }}
          </div>
          <div v-else class="pt-1">
            <button
              v-for="company in companyStore.companies"
              :key="company.id"
              type="button"
              role="menuitem"
              tabindex="0"
              :aria-current="
                companyStore.selectedCompany &&
                companyStore.selectedCompany.id === company.id
                  ? 'true'
                  : 'false'
              "
              class="
                flex
                items-center
                w-full
                mx-1.5
                p-2
                px-2
                rounded-md
                cursor-pointer
                hover:bg-gray-100
                focus:bg-gray-100
                focus:outline-none
                focus-visible:ring-2
                focus-visible:ring-primary-400
              "
              :class="
                companyStore.selectedCompany &&
                companyStore.selectedCompany.id === company.id
                  ? 'bg-surface-secondary'
                  : ''
              "
              @click="changeCompany(company)"
            >
              <span
                class="
                  flex
                  items-center
                  justify-center
                  mr-3
                  overflow-hidden
                  text-base
                  font-semibold
                  bg-gray-200
                  rounded-md
                  w-9
                  h-9
                  text-primary-500
                "
              >
                <span v-if="!company.logo">
                  {{ initGenerator(company.name) }}
                </span>
                <img
                  v-else
                  :src="company.logo"
                  alt="Company logo"
                  class="w-full h-full object-contain"
                />
              </span>
              <div class="flex flex-col min-w-0">
                <span class="text-sm text-gray-900 truncate">
                  {{ company.name }}
                </span>
              </div>
              <BaseIcon
                v-if="
                  companyStore.selectedCompany &&
                  companyStore.selectedCompany.id === company.id
                "
                name="CheckIcon"
                class="h-5 ml-auto shrink-0 text-primary-500"
              />
            </button>
          </div>
        </div>
        <button
          v-if="userStore.currentUser && userStore.currentUser.is_owner"
          type="button"
          class="
            flex
            items-center
            w-full
            p-3
            border-t border-gray-100
            cursor-pointer
            text-primary-400
            hover:text-primary-500
            focus:outline-none
            focus-visible:bg-gray-50
          "
          @click="addNewCompany"
        >
          <BaseIcon name="PlusCircleIcon" class="h-5 mr-2" />

          <span class="font-medium">
            {{ $t('company_switcher.add_new_company') }}
          </span>
        </button>
      </div>
    </transition>
  </div>
</template>

<script setup>
import { nextTick, ref, watch } from 'vue'
import { useId } from 'vue'
import { useCompanyStore } from '@/scripts/admin/stores/company'
import { onClickOutside } from '@vueuse/core'
import { useRoute, useRouter } from 'vue-router'
import { useModalStore } from '@/scripts/stores/modal'
import { useI18n } from 'vue-i18n'
import { useGlobalStore } from '@/scripts/admin/stores/global'
import { useUserStore } from '@/scripts/admin/stores/user'

import CompanyModal from '@/scripts/admin/components/modal-components/CompanyModal.vue'

defineProps({
  collapsed: { type: Boolean, default: false },
})

// Unique IDs shared by the trigger button (`aria-controls`) and the panel.
// Both must point at the same element so assistive tech can associate them.
const uid = useId()
const triggerId = `company-switcher-trigger-${uid}`
const panelId = `company-switcher-panel-${uid}`

const companyStore = useCompanyStore()
const modalStore = useModalStore()
const route = useRoute()
const router = useRouter()
const globalStore = useGlobalStore()
const { t } = useI18n()
const userStore = useUserStore()

const isShow = ref(false)
// Tracks when the active company's logo URL fails to load, so we fall back to
// initials instead of an empty avatar box. Reset between company switches.
const logoFailed = ref(false)
const companySwitchBar = ref(null)
const triggerRef = ref(null)
const panelRef = ref(null)

function toggleDropdown() {
  isShow.value = !isShow.value
  if (isShow.value) {
    logoFailed.value = false
    nextTick(() => {
      panelRef.value?.focus({ preventScroll: true })
    })
  }
}

function closeAndFocus() {
  isShow.value = false
  nextTick(() => {
    triggerRef.value?.focus({ preventScroll: true })
  })
}

// Escape on either the trigger button or anywhere inside the panel closes the
// dropdown. Without the conditional stop, pressing Escape with the panel open
// would also close the mobile Dialog. We only consume the keystroke while the
// panel is actually open so a second Escape can still dismiss the panel above.
function handleTriggerEscape(e) {
  if (!isShow.value) return
  e.stopPropagation()
  e.preventDefault()
  closeAndFocus()
}

function handlePanelEscape(e) {
  if (!isShow.value) return
  e.stopPropagation()
  e.preventDefault()
  closeAndFocus()
}

watch(
  () => companyStore.selectedCompany?.logo,
  () => {
    logoFailed.value = false
  }
)

watch(
  () => companyStore.selectedCompany?.id,
  () => {
    logoFailed.value = false
    isShow.value = false
  }
)

// Any navigation hides the menu so the next interaction starts clean.
watch(
  () => route.fullPath,
  () => {
    isShow.value = false
  }
)

// Closing the mobile drawer should always hide the menu so it does not
// reappear with stale focus the next time the drawer opens.
watch(
  () => globalStore.isSidebarOpen,
  (open) => {
    if (!open) isShow.value = false
  }
)

onClickOutside(companySwitchBar, () => {
  isShow.value = false
})

function initGenerator(name) {
  if (!name) return ''
  const firstWord = name.trim().split(/\s+/)[0] || ''
  return firstWord.charAt(0).toUpperCase()
}

function addNewCompany() {
  modalStore.openModal({
    title: t('company_switcher.new_company'),
    componentName: 'CompanyModal',
    size: 'sm',
  })
}

async function changeCompany(company) {
  isShow.value = false
  await companyStore.setSelectedCompany(company)
  router.push('/admin/dashboard')
  await globalStore.setIsAppLoaded(false)
  await globalStore.bootstrap()
}
</script>
