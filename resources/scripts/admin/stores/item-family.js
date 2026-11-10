import axios from 'axios'
import { defineStore } from 'pinia'
import { useNotificationStore } from '@/scripts/stores/notification'
import { handleError } from '@/scripts/helpers/error-handling'

export const useItemFamilyStore = (useWindow = false) => {
  const defineStoreFunc = useWindow ? window.pinia.defineStore : defineStore
  const { global } = window.i18n

  return defineStoreFunc({
    id: 'item-family',

    state: () => ({
      families: [],
      totalFamilies: 0,
      // Convenience cache of the tree built out of `families`. The
      // admin view populates this on every fetch via `applyTree()`
      // so re-renders don't recompute the parent/children map.
      rootIds: [],
      // Per-fetch snapshot of the in-flight fetch so the admin view
      // can show a spinner without leaking that flag to other
      // components.
      isFetching: false,
      // The family currently being edited in the modal — also used
      // for "create root" (id = null, parent_id = null).
      currentFamily: {
        id: null,
        name: '',
        parent_id: null,
      },
    }),

    getters: {
      isEdit: (state) => !!state.currentFamily.id,
    },

    actions: {
      resetCurrentFamily() {
        this.currentFamily = {
          id: null,
          name: '',
          parent_id: null,
        }
      },

      // Flat list. The admin view assembles the tree locally; the
      // controller never returns a recursive payload.
      fetchFamilies(params) {
        this.isFetching = true
        return new Promise((resolve, reject) => {
          axios
            .get('/api/v1/item-families', { params })
            .then((response) => {
              // The tree endpoint does not paginate — server returns
              // the full per-company catalog. Fallback to a length
              // count if no pagination metadata is included.
              const payload = Array.isArray(response.data)
                ? response.data
                : response.data.data ?? []
              this.families = payload
              this.totalFamilies = this.families.length
              this.isFetching = false
              resolve(response)
            })
            .catch((err) => {
              this.isFetching = false
              handleError(err)
              reject(err)
            })
        })
      },

      fetchFamily(id) {
        return new Promise((resolve, reject) => {
          axios
            .get(`/api/v1/item-families/${id}`)
            .then((response) => {
              if (response.data) {
                this.currentFamily = {
                  id: response.data.data.id,
                  name: response.data.data.name,
                  parent_id: response.data.data.parent_id ?? null,
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

      addFamily(data) {
        return new Promise((resolve, reject) => {
          axios
            .post('/api/v1/item-families', data)
            .then((response) => {
              const notificationStore = useNotificationStore()

              notificationStore.showNotification({
                type: 'success',
                message: global.t('item_families.created_message'),
              })

              resolve(response)
            })
            .catch((err) => {
              handleError(err)
              reject(err)
            })
        })
      },

      updateFamily(data) {
        return new Promise((resolve, reject) => {
          axios
            .put(`/api/v1/item-families/${data.id}`, data)
            .then((response) => {
              const notificationStore = useNotificationStore()

              notificationStore.showNotification({
                type: 'success',
                message: global.t('item_families.updated_message'),
              })

              resolve(response)
            })
            .catch((err) => {
              handleError(err)
              reject(err)
            })
        })
      },

      deleteFamily(id) {
        return new Promise((resolve, reject) => {
          axios
            .delete(`/api/v1/item-families/${id}`)
            .then((response) => {
              const notificationStore = useNotificationStore()

              if (response.data?.success) {
                notificationStore.showNotification({
                  type: 'success',
                  message: global.t('item_families.deleted_message'),
                })
              } else if (
                response.data?.error === 'inventory_attachment' ||
                response.data?.message
              ) {
                notificationStore.showNotification({
                  type: 'error',
                  message: global.t('item_families.family_attached_message'),
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
