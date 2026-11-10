import { createI18n } from 'vue-i18n'

export default (messages) => {
  return createI18n({
    legacy: false,
    locale: 'en',
    fallbackLocale: 'en',
    messages
  })
}
