import type { ComponentType, ReactNode, SVGProps } from 'react'
import { useTranslation } from 'react-i18next'
import { Link, NavLink } from 'react-router-dom'
import { useAuth } from '../auth/AuthContext'
import { CalendarIcon, ChevronIcon, HomeIcon, LogoutIcon, PawIcon, PlusIcon } from './Icons'
import { LanguageSwitch } from './LanguageSwitch'

export function AppShell({ children }: { children: ReactNode }) {
  const { t } = useTranslation()
  const { logout } = useAuth()

  return (
    <div className="min-h-screen pb-24">
      <header className="sticky top-0 z-10 border-b border-slate-200 bg-white/95 backdrop-blur">
        <div className="mx-auto flex max-w-2xl items-center justify-between px-4 py-2.5">
          <Link to="/" className="flex items-center gap-2 font-bold text-brand-700">
            <span className="flex h-9 w-9 items-center justify-center rounded-xl bg-brand-600 text-white">
              <PawIcon width={22} height={22} />
            </span>
            {t('app.name')}
          </Link>
          <div className="flex items-center gap-1">
            <LanguageSwitch />
            <button
              type="button"
              onClick={logout}
              aria-label={t('auth.logout')}
              title={t('auth.logout')}
              className="flex h-11 w-11 items-center justify-center rounded-xl text-slate-500 hover:bg-slate-100"
            >
              <LogoutIcon width={22} height={22} />
            </button>
          </div>
        </div>
      </header>

      <main className="mx-auto max-w-2xl space-y-5 p-4">{children}</main>

      <nav
        aria-label={t('nav.label')}
        className="fixed inset-x-0 bottom-0 z-10 border-t border-slate-200 bg-white pb-[env(safe-area-inset-bottom)]"
      >
        <div className="mx-auto grid max-w-2xl grid-cols-3">
          <NavItem to="/" end icon={HomeIcon} label={t('nav.home')} />
          <NavItem to="/book" icon={PlusIcon} label={t('nav.book')} />
          <NavItem to="/bookings" icon={CalendarIcon} label={t('nav.bookings')} />
        </div>
      </nav>
    </div>
  )
}

function NavItem({
  to,
  end,
  icon: Icon,
  label,
}: {
  to: string
  end?: boolean
  icon: ComponentType<SVGProps<SVGSVGElement>>
  label: string
}) {
  return (
    <NavLink
      to={to}
      end={end}
      className={({ isActive }) =>
        `flex flex-col items-center gap-0.5 py-2 text-xs font-medium ${isActive ? 'text-brand-700' : 'text-slate-500'}`
      }
    >
      <Icon width={24} height={24} />
      {label}
    </NavLink>
  )
}

export function BackLink({ to, label }: { to: string; label: string }) {
  return (
    <Link to={to} className="inline-flex items-center gap-1 py-1 text-sm font-medium text-slate-600">
      <ChevronIcon width={18} height={18} className="rotate-180" />
      {label}
    </Link>
  )
}

export function Card({ title, action, children }: { title: string; action?: ReactNode; children: ReactNode }) {
  return (
    <section className="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-200">
      <div className="mb-3 flex items-center justify-between gap-2">
        <h2 className="text-lg font-semibold">{title}</h2>
        {action}
      </div>
      {children}
    </section>
  )
}

/** A large tappable option: one idea, one tap. */
export function ChoiceCard({
  selected,
  onClick,
  icon,
  title,
  detail,
  trailing,
}: {
  selected?: boolean
  onClick: () => void
  icon?: ReactNode
  title: string
  detail?: string
  trailing?: ReactNode
}) {
  return (
    <button
      type="button"
      aria-pressed={selected}
      onClick={onClick}
      className={`flex min-h-16 w-full items-center gap-3 rounded-2xl bg-white p-3 text-start ring-1 transition ${
        selected ? 'ring-2 ring-brand-600' : 'ring-slate-200 hover:ring-brand-500'
      }`}
    >
      {icon && (
        <span className="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-brand-50 text-brand-700">
          {icon}
        </span>
      )}
      <span className="min-w-0 flex-1">
        <span className="block font-semibold">{title}</span>
        {detail && <span className="block text-sm text-slate-600">{detail}</span>}
      </span>
      {trailing}
    </button>
  )
}

export function EmptyState({ icon, title, hint, action }: { icon: ReactNode; title: string; hint: string; action?: ReactNode }) {
  return (
    <div className="flex flex-col items-center gap-2 py-6 text-center">
      <span className="flex h-16 w-16 items-center justify-center rounded-full bg-brand-50 text-brand-600">{icon}</span>
      <p className="font-semibold">{title}</p>
      <p className="max-w-xs text-sm text-slate-600">{hint}</p>
      {action && <div className="mt-2">{action}</div>}
    </div>
  )
}

export const inputClass =
  'w-full min-h-12 rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-base focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-100'

export const buttonClass =
  'inline-flex min-h-12 items-center justify-center gap-2 rounded-xl bg-brand-600 px-5 py-2.5 font-semibold text-white hover:bg-brand-700 disabled:opacity-60'

export const secondaryButtonClass =
  'inline-flex min-h-12 items-center justify-center gap-2 rounded-xl bg-white px-5 py-2.5 font-semibold text-slate-700 ring-1 ring-slate-300 hover:bg-slate-50 disabled:opacity-60'

export function Field({ label, error, children }: { label: string; error?: string; children: ReactNode }) {
  return (
    <label className="block">
      <span className="mb-1.5 block text-sm font-medium text-slate-700">{label}</span>
      {children}
      {error && <span className="mt-1 block text-sm text-danger-600">{error}</span>}
    </label>
  )
}
