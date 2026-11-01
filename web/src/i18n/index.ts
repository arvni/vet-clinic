import i18n from 'i18next'
import { initReactI18next } from 'react-i18next'
import fa from './fa.json'
import en from './en.json'

export const SUPPORTED_LANGUAGES = ['fa', 'en'] as const
export type Language = (typeof SUPPORTED_LANGUAGES)[number]

const STORAGE_KEY = 'lang'

function storedLanguage(): Language {
  try {
    const value = localStorage.getItem(STORAGE_KEY)
    return value === 'en' ? 'en' : 'fa'
  } catch {
    return 'fa'
  }
}

/** Keep <html lang/dir> in sync so RTL/LTR layout follows the language. */
export function applyDocumentLanguage(lang: Language): void {
  document.documentElement.lang = lang
  document.documentElement.dir = lang === 'fa' ? 'rtl' : 'ltr'
}

export async function setLanguage(lang: Language): Promise<void> {
  try {
    localStorage.setItem(STORAGE_KEY, lang)
  } catch {
    // storage unavailable (private mode); language still applies for this session
  }
  applyDocumentLanguage(lang)
  await i18n.changeLanguage(lang)
}

const initial = storedLanguage()
applyDocumentLanguage(initial)

void i18n.use(initReactI18next).init({
  resources: { fa: { translation: fa }, en: { translation: en } },
  lng: initial,
  fallbackLng: 'fa',
  interpolation: { escapeValue: false },
})

export default i18n
