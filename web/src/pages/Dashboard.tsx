import { useTranslation } from 'react-i18next'
import { useAuth } from '../auth/AuthContext'
import { LanguageSwitch } from '../components/LanguageSwitch'

export function Dashboard() {
  const { t } = useTranslation()
  const { user, logout } = useAuth()

  return (
    <div className="min-h-screen">
      <header className="flex items-center justify-between border-b border-slate-200 bg-white px-4 py-3">
        <span className="font-semibold text-brand-700">{t('app.name')}</span>
        <div className="flex items-center gap-2">
          <LanguageSwitch />
          <button type="button" onClick={logout} className="px-3 py-1.5 text-sm text-slate-600">
            {t('auth.logout')}
          </button>
        </div>
      </header>

      <main className="mx-auto max-w-3xl space-y-6 p-4">
        <h1 className="text-xl font-semibold">
          {t('dashboard.welcome')}
          {user?.name ? `، ${user.name}` : ''}
        </h1>

        {/* Emergency button stays visible on every owner screen (FR-3.4); wired up in S2. */}
        <button
          type="button"
          className="w-full rounded-xl bg-danger-600 py-3 text-lg font-semibold text-white shadow-sm"
        >
          {t('dashboard.emergency')}
        </button>

        <section className="rounded-2xl bg-white p-4 ring-1 ring-slate-200">
          <h2 className="mb-2 font-medium">{t('dashboard.pets')}</h2>
          <p className="text-sm text-slate-500">{t('dashboard.noPets')}</p>
        </section>
      </main>
    </div>
  )
}
