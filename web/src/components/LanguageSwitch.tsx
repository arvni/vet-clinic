import { useTranslation } from 'react-i18next'
import { setLanguage } from '../i18n'
import { api, getToken } from '../lib/api'

export function LanguageSwitch() {
  const { t, i18n } = useTranslation()
  const next = i18n.language === 'fa' ? 'en' : 'fa'

  const switchLanguage = async () => {
    await setLanguage(next)
    if (getToken()) {
      // Persist on the account so SMS and PDFs follow the same language (FR-1.8).
      void api.patch('/me', { locale: next }).catch(() => undefined)
    }
  }

  return (
    <button
      type="button"
      onClick={switchLanguage}
      className="min-h-11 rounded-xl px-3 text-sm font-medium text-slate-600 hover:bg-slate-100"
    >
      {t('app.language')}
    </button>
  )
}
