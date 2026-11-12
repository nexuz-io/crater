export default {
  DASHBOARD: 'dashboard',

  // customers
  CREATE_CUSTOMER: 'create-customer',
  DELETE_CUSTOMER: 'delete-customer',
  EDIT_CUSTOMER: 'edit-customer',
  VIEW_CUSTOMER: 'view-customer',

  // Items
  CREATE_ITEM: 'create-item',
  DELETE_ITEM: 'delete-item',
  EDIT_ITEM: 'edit-item',
  VIEW_ITEM: 'view-item',

  // Brands (article manufacturers / trademarks). Mirrors the
  // view-item / create-item / edit-item / delete-item contract.
  CREATE_BRAND: 'create-brand',
  DELETE_BRAND: 'delete-brand',
  EDIT_BRAND: 'edit-brand',
  VIEW_BRAND: 'view-brand',

  // Item families: hierarchical self-join catalog (parent_id).
  CREATE_ITEM_FAMILY: 'create-item-family',
  DELETE_ITEM_FAMILY: 'delete-item-family',
  EDIT_ITEM_FAMILY: 'edit-item-family',
  VIEW_ITEM_FAMILY: 'view-item-family',

  // Tax Types
  CREATE_TAX_TYPE: 'create-tax-type',
  DELETE_TAX_TYPE: 'delete-tax-type',
  EDIT_TAX_TYPE: 'edit-tax-type',
  VIEW_TAX_TYPE: 'view-tax-type',

  // Calculators
  CREATE_CALCULATOR: 'create-calculator',
  DELETE_CALCULATOR: 'delete-calculator',
  EDIT_CALCULATOR: 'edit-calculator',
  VIEW_CALCULATOR: 'view-calculator',

  // Estimates
  CREATE_ESTIMATE: 'create-estimate',
  DELETE_ESTIMATE: 'delete-estimate',
  EDIT_ESTIMATE: 'edit-estimate',
  VIEW_ESTIMATE: 'view-estimate',
  SEND_ESTIMATE: 'send-estimate',

  // Invoices
  CREATE_INVOICE: 'create-invoice',
  DELETE_INVOICE: 'delete-invoice',
  EDIT_INVOICE: 'edit-invoice',
  VIEW_INVOICE: 'view-invoice',
  SEND_INVOICE: 'send-invoice',

  // Recurring Invoices
  CREATE_RECURRING_INVOICE: 'create-recurring-invoice',
  DELETE_RECURRING_INVOICE: 'delete-recurring-invoice',
  EDIT_RECURRING_INVOICE: 'edit-recurring-invoice',
  VIEW_RECURRING_INVOICE: 'view-recurring-invoice',

  // Delivery Notes (plan 09 §F11). Habilita la integración frontend:
  // las rutas /admin/delivery-notes/* consumen estos flags en su
  // `meta.ability` para esconder los enlaces del sidebar y los
  // botones de operaciones. El `*_ALL` no existe — la combinacion
  // `view + create + ...` es la forma natural (matriz plan 06).
  CREATE_DELIVERY_NOTE: 'create-delivery-note',
  DELETE_DELIVERY_NOTE: 'delete-delivery-note',
  EDIT_DELIVERY_NOTE: 'edit-delivery-note',
  VIEW_DELIVERY_NOTE: 'view-delivery-note',
  SEND_DELIVERY_NOTE: 'send-delivery-note',
  CONVERT_DELIVERY_NOTE_INVOICE: 'convert-delivery-note-invoice',

  // Payment
  CREATE_PAYMENT: 'create-payment',
  DELETE_PAYMENT: 'delete-payment',
  EDIT_PAYMENT: 'edit-payment',
  VIEW_PAYMENT: 'view-payment',
  SEND_PAYMENT: 'send-payment',

  // Payment
  CREATE_EXPENSE: 'create-expense',
  DELETE_EXPENSE: 'delete-expense',
  EDIT_EXPENSE: 'edit-expense',
  VIEW_EXPENSE: 'view-expense',

  // Custom fields
  CREATE_CUSTOM_FIELDS: 'create-custom-field',
  DELETE_CUSTOM_FIELDS: 'delete-custom-field',
  EDIT_CUSTOM_FIELDS: 'edit-custom-field',
  VIEW_CUSTOM_FIELDS: 'view-custom-field',

  // Roles
  CREATE_ROLE: 'create-role',
  DELETE_ROLE: 'delete-role',
  EDIT_ROLE: 'edit-role',
  VIEW_ROLE: 'view-role',

  // exchange rates
  VIEW_EXCHANGE_RATE: 'view-exchange-rate-provider',
  CREATE_EXCHANGE_RATE: 'create-exchange-rate-provider',
  EDIT_EXCHANGE_RATE: 'edit-exchange-rate-provider',
  DELETE_EXCHANGE_RATE: 'delete-exchange-rate-provider',

  // Reports
  VIEW_FINANCIAL_REPORT: 'view-financial-reports',

  // settings
  MANAGE_NOTE: 'manage-all-notes',
  VIEW_NOTE: 'view-all-notes',
}
