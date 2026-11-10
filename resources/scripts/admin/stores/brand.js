import axios from 'axios'
import { defineStore } from 'pinia'
import { useNotificationStore } from '@/scripts/stores/notification'
import { handleError } from '@/scripts/helpers/error-handling'

export const useBrandStore = (useWindow = false) => {
  const defineStoreFunc = useWindow ? window.pinia.defineStore : defineStore
  const { global } = window.i18n

  return defineStoreFunc({
    id: 'brand',

    state: () => ({
      brands: [],
      totalBrands: 0,
      selectAllField: false,
      selectedBrands: [],
      currentBrand: {
        id: null,
        name: '',
      },
    }),

    getters: {
      isEdit: (state) => !!state.currentBrand.id,
    },

    actions: {
      resetCurrentBrand() {
        this.currentBrand = {
          id: null,
          name: '',
        }
      },

      fetchBrands(params) {
        return new Promise((resolve, reject) => {
          axios
            .get('/api/v1/brands', { params })
            .then((response) => {
              this.brands = response.data.data
              this.totalBrands =
                response.data.meta?.total ??
                response.data.data?.length ??
                0

              resolve(response)
            })
            .catch((err) => {
              handleError(err)
              reject(err)
            })
        })
      },

      // Lightweight fetch used by the brand selector inside the item
      // form. Same endpoint, but never dereferences totalBrands so we
      // do not poison the admin table count.
      fetchBrandsForSelect(params) {
        return new Promise((resolve, reject) => {
          axios
            .get('/api/v1/brands', { params })
            .then((response) => {
              resolve(response)
            })
            .catch((err) => {
              handleError(err)
              reject(err)
            })
        })
      },

      fetchBrand(id) {
        return new Promise((resolve, reject) => {
          axios
            .get(`/api/v1/brands/${id}`)
            .then((response) => {
              if (response.data) {
                this.currentBrand = {
                  id: response.data.data.id,
                  name: response.data.data.name,
                }
              }
              resolve(response)
            })
            .catch((err) => {
              handleError(err)
              reject(err)
            })
        })
      },

      addBrand(data) {
        return new Promise((resolve, reject) => {
          axios
            .post('/api/v1/brands', data)
            .then((response) => {
              const notificationStore = useNotificationStore()

              notificationStore.showNotification({
                type: 'success',
                message: global.t('brands.created_message'),
              })

              resolve(response)
            })
            .catch((err) => {
              handleError(err)
              reject(err)
            })
        })
      },

      updateBrand(data) {
        return new Promise((resolve, reject) => {
          axios
            .put(`/api/v1/brands/${data.id}`, data)
            .then((response) => {
              const notificationStore = useNotificationStore()

              notificationStore.showNotification({
                type: 'success',
                message: global.t('brands.updated_message'),
              })

              resolve(response)
            })
            .catch((err) => {
              handleError(err)
              reject(err)
            })
        })
      },

      deleteBrand(id) {
        return new Promise((resolve, reject) => {
          axios
            .delete(`/api/v1/brands/${id}`)
            .then((response) => {
              const notificationStore = useNotificationStore()

              if (response.data?.success) {
                notificationStore.showNotification({
                  type: 'success',
                  message: global.t('brands.deleted_message'),
                })
              } else if (response.data?.error === 'brand_attached_items') {
                notificationStore.showNotification({
                  type: 'error',
                  message: global.t('brands.brand_attached_message'),
                })
              }

              resolve(response)
            })
            .catch((err) => {
              handleError(err)
              reject(err)
            })
        })
      },
    },
  })()
}
