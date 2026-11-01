import type { ReactNode } from 'react'
import { useTranslation } from 'react-i18next'
import { Link } from 'react-router-dom'
import { useAuth } from '../auth/AuthContext'
import { LanguageSwitch } from './LanguageSwitch'

export function AppShell({ children }: { children: ReactNode }) {
  const { t } = useTranslation()
  const { logout } = useAuth()

  return (
    <div className="min-h-screen">
      <header className="sticky top-0 z-10 flex items-center justify-between border-b border-slate-200 bg-white px-4 py-3">
        <Link to="/" className="font-semibold text-brand-700">
          {t('app.name')}
        </Link>
        <div className="flex items-center gap-2">
          <LanguageSwitch />
          <button type="button" onClick={logout} className="px-3 py-1.5 text-sm text-slate-600">
            {t('auth.logout')}
          </button>
        </div>
      </header>
      <main className="mx-auto max-w-3xl space-y-6 p-4">{children}</main>
    </div>
  )
}

export function Card({ title, action, children }: { title: string; action?: ReactNode; children: ReactNode }) {
  return (
    <section className="rounded-2xl bg-white p-4 ring-1 ring-slate-200">
      <div className="mb-3 flex items-center justify-between gap-2">
        <h2 className="font-medium">{title}</h2>
        {action}
      </div>
      {children}
    </section>
  )
}

export const inputClass =
  'w-full rounded-lg border border-slate-300 bg-white px-3 py-2 focus:border-brand-500 focus:outline-none'

export const buttonClass =
  'rounded-lg bg-brand-600 px-4 py-2 font-medium text-white hover:bg-brand-700 disabled:opacity-60'

export function Field({ label, error, children }: { label: string; error?: string; children: ReactNode }) {
  return (
    <label className="block">
      <span className="mb-1 block text-sm text-slate-600">{label}</span>
      {children}
      {error && <span className="mt-1 block text-sm text-danger-600">{error}</span>}
    </label>
  )
}
