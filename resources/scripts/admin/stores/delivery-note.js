import axios from 'axios'
import moment from 'moment'
import Guid from 'guid'
import _ from 'lodash'
import { defineStore } from 'pinia'
import { useRoute } from 'vue-router'
import { useCompanyStore } from './company'
import { useCustomerStore } from './customer'
import { useNotificationStore } from '@/scripts/stores/notification'
import { useItemStore } from './item'
import { useTaxTypeStore } from './tax-type'
import { handleError } from '@/scripts/helpers/error-handling'
import deliveryNoteStub from '../stub/delivery-note'
import deliveryNoteItemStub from '../stub/delivery-note-item'
import taxStub from '../stub/tax'

/**
 * useDeliveryNoteStore — gemelo de useEstimateStore adaptado a
 * DeliveryNote (plan 05 + plan 09 F11).
 *
 * Diferencias reseñables frente al estimate store:
 * - `markAsDelivered`, `cancelDeliveryNote` y `convertToInvoice`
 *   son las acciones de ciclo de vida propias del albarán.
 * - El número del documento es `delivery_number` (no
 *   `delivery_note_number`, alineado con la migración BD).
 * - La UI puede no imprimir precios por defecto (plan 05 §1)
 *   sin afectar al snapshot comercial que conserva el converter.
 */
export const useDeliveryNoteStore = (useWindow = false) => {
  const defineStoreFunc = useWindow ? window.pinia.defineStore : defineStore
  const { global } = window.i18n

  return defineStoreFunc({
    id: 'delivery-note',

    state: () => ({
      templates: [],

      deliveryNotes: [],
      selectAllField: false,
      selectedDeliveryNotes: [],
      totalDeliveryNoteCount: 0,
      isFetchingInitialSettings: false,
      showExchangeRate: false,

      newDeliveryNote: {
        ...deliveryNoteStub(),
      },
    }),

    getters: {
      getSubTotal() {
        return this.newDeliveryNote.items.reduce(function (a, b) {
          return a + b['total']
        }, 0)
      },
      getTotalSimpleTax() {
        return _.sumBy(this.newDeliveryNote.taxes, function (tax) {
          if (!tax.compound_tax) {
            return tax.amount
          }
          return 0
        })
      },

      getTotalCompoundTax() {
        return _.sumBy(this.newDeliveryNote.taxes, function (tax) {
          if (tax.compound_tax) {
            return tax.amount
          }
          return 0
        })
      },

      getTotalTax() {
        if (
          this.newDeliveryNote.tax_per_item === 'NO' ||
          this.newDeliveryNote.tax_per_item === null
        ) {
          return this.getTotalSimpleTax + this.getTotalCompoundTax
        }
        return _.sumBy(this.newDeliveryNote.items, function (tax) {
          return tax.tax
        })
      },

      getSubtotalWithDiscount() {
        return this.getSubTotal - this.newDeliveryNote.discount_val
      },

      getTotal() {
        return this.getSubtotalWithDiscount + this.getTotalTax
      },

      isEdit: (state) => (state.newDeliveryNote.id ? true : false),
    },

    actions: {
      resetCurrentDeliveryNote() {
        this.newDeliveryNote = {
          ...deliveryNoteStub(),
        }
      },

      previewDeliveryNote(params) {
        return new Promise((resolve, reject) => {
          axios
            .get(`/api/v1/delivery-notes/${params.id}/send/preview`, { params })
            .then((response) => {
              resolve(response)
            })
            .catch((err) => {
              handleError(err)
              reject(err)
            })
        })
      },

      fetchDeliveryNotes(params) {
        return new Promise((resolve, reject) => {
          axios
            .get(`/api/v1/delivery-notes`, { params })
            .then((response) => {
              this.deliveryNotes = response.data.data
              this.totalDeliveryNoteCount =
                response.data.meta.delivery_note_total_count ||
                response.data.meta.total_count ||
                (response.data.meta && Object.values(response.data.meta)[0]) ||
                this.deliveryNotes.length
              resolve(response)
            })
            .catch((err) => {
              handleError(err)
              reject(err)
            })
        })
      },

      getNextNumber(params, setState = false) {
        return new Promise((resolve, reject) => {
          axios
            .get(`/api/v1/next-number?key=delivery_note`, { params })
            .then((response) => {
              if (setState) {
                this.newDeliveryNote.delivery_number = response.data.nextNumber
              }
              resolve(response)
            })
            .catch((err) => {
              handleError(err)
              reject(err)
            })
        })
      },

      fetchDeliveryNote(id) {
        return new Promise((resolve, reject) => {
          axios
            .get(`/api/v1/delivery-notes/${id}`)
            .then((response) => {
              Object.assign(this.newDeliveryNote, response.data.data)
              resolve(response)
            })
            .catch((err) => {
              handleError(err)
              reject(err)
            })
        })
      },

      sendDeliveryNote(data) {
        const notificationStore = useNotificationStore()

        return new Promise((resolve, reject) => {
          axios
            .post(`/api/v1/delivery-notes/${data.id}/send`, data)
            .then((response) => {
              if (!data.is_preview) {
                notificationStore.showNotification({
                  type: 'success',
                  message: global.t('delivery-notes.send_delivery_note_successfully'),
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

      addDeliveryNote(data) {
        return new Promise((resolve, reject) => {
          axios
            .post('/api/v1/delivery-notes', data)
            .then((response) => {
              this.deliveryNotes = [...this.deliveryNotes, response.data.data]
              const notificationStore = useNotificationStore()

              notificationStore.showNotification({
                type: 'success',
                message: global.t('delivery-notes.created_message'),
              })

              resolve(response)
            })
            .catch((err) => {
              handleError(err)
              reject(err)
            })
        })
      },

      deleteDeliveryNote(id) {
        const notificationStore = useNotificationStore()

        return new Promise((resolve, reject) => {
          axios
            .post(`/api/v1/delivery-notes/delete`, id)
            .then((response) => {
              let index = this.deliveryNotes.findIndex(
                (d) => d.id === id
              )

              if (index >= 0) {
                this.deliveryNotes.splice(index, 1)
              }

              notificationStore.showNotification({
                type: 'success',
                message: global.t('delivery-notes.deleted_message', 1),
              })
              resolve(response)
            })
            .catch((err) => {
              handleError(err)
              reject(err)
            })
        })
      },

      deleteMultipleDeliveryNotes() {
        const notificationStore = useNotificationStore()

        return new Promise((resolve, reject) => {
          axios
            .post(`/api/v1/delivery-notes/delete`, { ids: this.selectedDeliveryNotes })
            .then((response) => {
              this.selectedDeliveryNotes.forEach((id) => {
                let index = this.deliveryNotes.findIndex(
                  (d) => d.id === id
                )
                if (index >= 0) {
                  this.deliveryNotes.splice(index, 1)
                }
              })
              this.selectedDeliveryNotes = []

              notificationStore.showNotification({
                type: 'success',
                message: global.tc('delivery-notes.deleted_message', 2),
              })
              resolve(response)
            })
            .catch((err) => {
              handleError(err)
              reject(err)
            })
        })
      },

      updateDeliveryNote(data) {
        return new Promise((resolve, reject) => {
          axios
            .put(`/api/v1/delivery-notes/${data.id}`, data)
            .then((response) => {
              let pos = this.deliveryNotes.findIndex(
                (d) => d.id === response.data.data.id
              )
              if (pos >= 0) {
                this.deliveryNotes[pos] = response.data.data
              }
              const notificationStore = useNotificationStore()
              notificationStore.showNotification({
                type: 'success',
                message: global.t('delivery-notes.updated_message'),
              })
              resolve(response)
            })
            .catch((err) => {
              handleError(err)
              reject(err)
            })
        })
      },

      markAsDelivered(data) {
        return new Promise((resolve, reject) => {
          axios
            .post(`/api/v1/delivery-notes/${data.id}/mark-delivered`, data)
            .then((response) => {
              let pos = this.deliveryNotes.findIndex(
                (d) => d.id === data.id
              )
              if (pos >= 0 && this.deliveryNotes[pos]) {
                this.deliveryNotes[pos].status = 'DELIVERED'
              }
              const notificationStore = useNotificationStore()
              notificationStore.showNotification({
                type: 'success',
                message: global.t('delivery-notes.marked_as_delivered_message'),
              })
              resolve(response)
            })
            .catch((err) => {
              handleError(err)
              reject(err)
            })
        })
      },

      cancelDeliveryNote(data) {
        return new Promise((resolve, reject) => {
          axios
            .post(`/api/v1/delivery-notes/${data.id}/cancel`, data)
            .then((response) => {
              let pos = this.deliveryNotes.findIndex(
                (d) => d.id === data.id
              )
              if (pos >= 0 && this.deliveryNotes[pos]) {
                this.deliveryNotes[pos].status = 'CANCELLED'
              }
              const notificationStore = useNotificationStore()
              notificationStore.showNotification({
                type: 'success',
                message: global.t('delivery-notes.marked_as_cancelled_message'),
              })
              resolve(response)
            })
            .catch((err) => {
              handleError(err)
              reject(err)
            })
        })
      },

      convertToInvoice(id) {
        const notificationStore = useNotificationStore()
        return new Promise((resolve, reject) => {
          axios
            .post(`/api/v1/delivery-notes/${id}/convert-to-invoice`)
            .then((response) => {
              notificationStore.showNotification({
                type: 'success',
                message: global.t('delivery-notes.conversion_message'),
              })
              resolve(response)
            })
            .catch((err) => {
              handleError(err)
              reject(err)
            })
        })
      },

      searchDeliveryNote(data) {
        return new Promise((resolve, reject) => {
          axios
            .get(`/api/v1/delivery-notes?${data}`)
            .then((response) => {
              resolve(response)
            })
            .catch((err) => {
              handleError(err)
              reject(err)
            })
        })
      },

      selectDeliveryNote(data) {
        this.selectedDeliveryNotes = data
        if (this.selectedDeliveryNotes.length === this.deliveryNotes.length) {
          this.selectAllField = true
        } else {
          this.selectAllField = false
        }
      },

      selectAllDeliveryNotes() {
        if (this.selectedDeliveryNotes.length === this.deliveryNotes.length) {
          this.selectedDeliveryNotes = []
          this.selectAllField = false
        } else {
          let allIds = this.deliveryNotes.map((d) => d.id)
          this.selectedDeliveryNotes = allIds
          this.selectAllField = true
        }
      },

      selectCustomer(id) {
        return new Promise((resolve, reject) => {
          axios
            .get(`/api/v1/customers/${id}`)
            .then((response) => {
              this.newDeliveryNote.customer = response.data.data
              this.newDeliveryNote.customer_id = response.data.data.id
              resolve(response)
            })
            .catch((err) => {
              handleError(err)
              reject(err)
            })
        })
      },

      fetchDeliveryNoteTemplates(params) {
        return new Promise((resolve, reject) => {
          axios
            .get(`/api/v1/delivery-notes/templates`, { params })
            .then((response) => {
              this.templates = response.data.deliveryNoteTemplates
              resolve(response)
            })
            .catch((err) => {
              handleError(err)
              reject(err)
            })
        })
      },

      setTemplate(data) {
        this.newDeliveryNote.template_name = data
      },

      resetSelectedCustomer() {
        this.newDeliveryNote.customer = null
        this.newDeliveryNote.customer_id = ''
      },

      selectNote(data) {
        this.newDeliveryNote.selectedNote = null
        this.newDeliveryNote.selectedNote = data
      },

      resetSelectedNote() {
        this.newDeliveryNote.selectedNote = null
      },

      addItem() {
        this.newDeliveryNote.items.push({
          ...deliveryNoteItemStub,
          id: Guid.raw(),
          taxes: [{ ...taxStub, id: Guid.raw() }],
        })
      },

      updateItem(data) {
        Object.assign(this.newDeliveryNote.items[data.index], { ...data })
      },

      removeItem(index) {
        this.newDeliveryNote.items.splice(index, 1)
      },

      deselectItem(index) {
        this.newDeliveryNote.items[index] = {
          ...deliveryNoteItemStub,
          id: Guid.raw(),
          taxes: [{ ...taxStub, id: Guid.raw() }],
        }
      },

      async fetchDeliveryNoteInitialSettings(isEdit) {
        const companyStore = useCompanyStore()
        const customerStore = useCustomerStore()
        const itemStore = useItemStore()
        const taxTypeStore = useTaxTypeStore()
        const route = useRoute()

        this.isFetchingInitialSettings = true
        this.newDeliveryNote.selectedCurrency = companyStore.selectedCompanyCurrency

        if (route.query.customer) {
          let response = await customerStore.fetchCustomer(route.query.customer)
          this.newDeliveryNote.customer = response.data.data
          this.newDeliveryNote.customer_id = response.data.data.id
        }

        let editActions = []

        if (!isEdit) {
          this.newDeliveryNote.tax_per_item =
            companyStore.selectedCompanySettings.tax_per_item
          this.newDeliveryNote.sales_tax_type =
            companyStore.selectedCompanySettings.sales_tax_type
          this.newDeliveryNote.sales_tax_address_type =
            companyStore.selectedCompanySettings.sales_tax_address_type
          this.newDeliveryNote.discount_per_item =
            companyStore.selectedCompanySettings.discount_per_item
          // Términos por defecto del albarán (plan 05 §4). Si la
          // empresa aún no lo configuró, se aplica fallback al
          // equivalente de estimate para mimetizar el resto de
          // documentos.
          this.newDeliveryNote.notes =
            companyStore.selectedCompanySettings.delivery_note_default_terms_and_conditions ||
            companyStore.selectedCompanySettings.estimate_default_terms_and_conditions ||
            ''
          this.newDeliveryNote.delivery_date = moment().format('YYYY-MM-DD')
        } else {
          editActions = [this.fetchDeliveryNote(route.params.id)]
        }

        Promise.all([
          itemStore.fetchItems({
            filter: {},
            orderByField: '',
            orderBy: '',
          }),
          this.resetSelectedNote(),
          this.fetchDeliveryNoteTemplates(),
          this.getNextNumber(),
          taxTypeStore.fetchTaxTypes({ limit: 'all' }),
          ...editActions,
        ])
          .then(async ([res1, res2, res3, res4, res5]) => {
            if (!isEdit) {
              if (res4.data) {
                this.newDeliveryNote.delivery_number = res4.data.nextNumber
              }

              this.setTemplate(this.templates[0]?.name || 'delivery-note-1')
            }
            this.isFetchingInitialSettings = false
          })
          .catch((err) => {
            handleError(err)
            this.isFetchingInitialSettings = false
          })
      },
    },
  })()
}
