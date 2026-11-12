const { defineStore } = window.pinia
import axios from 'axios'
import { handleError } from '@/scripts/customer/helpers/error-handling'

/**
 * Portal de cliente — Delivery Note store.
 *
 * Plan 05 §8 — el cliente autenticado puede listar y ver sus albaranes
 * (sin acciones de edición; sólo lectura + descarga de PDF). El
 * gemelo del `useEstimateStore` del cliente, sin métodos de
 * transición de estado (los albaranes no son aceptables/rechazables
 * por el cliente como los presupuestos).
 */
export const useDeliveryNoteStore = defineStore({
  id: 'customerDeliveryNoteStore',
  state: () => ({
    deliveryNotes: [],
    totalDeliveryNotes: 0,
    selectedViewDeliveryNote: [],
  }),
  actions: {
    fetchDeliveryNotes(params, slug) {
      return new Promise((resolve, reject) => {
        axios
          .get(`/api/v1/${slug}/customer/delivery-notes`, { params })
          .then((response) => {
            this.deliveryNotes = response.data.data
            this.totalDeliveryNotes =
              response.data.meta?.deliveryNoteTotalCount ??
              response.data.meta?.total ??
              this.deliveryNotes.length
            resolve(response)
          })
          .catch((err) => {
            handleError(err)
            reject(err)
          })
      })
    },

    fetchViewDeliveryNote(params, slug) {
      return new Promise((resolve, reject) => {
        axios
          .get(`/api/v1/${slug}/customer/delivery-notes/${params.id}`, {
            params,
          })
          .then((response) => {
            this.selectedViewDeliveryNote = response.data.data
            resolve(response)
          })
          .catch((err) => {
            handleError(err)
            reject(err)
          })
      })
    },

    searchDeliveryNotes(params, slug) {
      return new Promise((resolve, reject) => {
        axios
          .get(`/api/v1/${slug}/customer/delivery-notes`, { params })
          .then((response) => {
            this.deliveryNotes = response.data.data
            resolve(response)
          })
          .catch((err) => {
            handleError(err)
            reject(err)
          })
      })
    },
  },
})
