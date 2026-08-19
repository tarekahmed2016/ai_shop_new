import { createI18n } from 'vue-i18n'
import en from './Locales/en.json'
import ar from './Locales/ar.json'

const savedLocale = localStorage.getItem('locale') || 'ar'

document.dir = savedLocale === 'ar' ? 'rtl' : 'ltr'
document.documentElement.lang = savedLocale

const i18n = createI18n({
  legacy: false,
  locale: savedLocale,
  fallbackLocale: 'en',
  messages: {
    en,
    ar,
  },
})

export default i18n
