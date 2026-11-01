import { useTranslation } from 'react-i18next'
import { Link } from 'react-router-dom'
import { useAuth } from '../auth/AuthContext'
import { AppShell, Card, EmptyState, buttonClass } from '../components/AppShell'
import { BookingStatus } from '../components/BookingStatus'
import { EmergencyButton } from '../components/EmergencyButton'
import { CalendarIcon, ChevronIcon, PawIcon, PlusIcon } from '../components/Icons'
import { PetAge } from '../components/PetAge'
import { formatDateTime, nextBooking, useBookings } from '../lib/bookings'
import { usePets } from '../lib/pets'

export function Dashboard() {
  const { t, i18n } = useTranslation()
  const { user } = useAuth()
  const pets = usePets()
  const bookings = useBookings()
  const next = nextBooking(bookings.data ?? [])
  const hasPets = !!pets.data?.length

  return (
    <AppShell>
      <h1 className="text-2xl font-bold">
        {t('dashboard.welcome')}
        {user?.name ? (i18n.language === 'fa' ? `، ${user.name}` : `, ${user.name}`) : ''}
      </h1>

      {next ? (
        <Link to="/bookings" className="block rounded-2xl bg-brand-600 p-4 text-white shadow-sm">
          <span className="flex items-center justify-between gap-2">
            <span className="flex items-center gap-2 text-sm opacity-90">
              <CalendarIcon width={18} height={18} />
              {t('dashboard.nextVisit')}
            </span>
            <BookingStatus status={next.status} />
          </span>
          <span className="mt-2 block text-lg font-bold">{formatDateTime(next.starts_at, i18n.language)}</span>
          <span className="block text-sm opacity-90">
            {next.service.name} · {next.pet.name}
          </span>
        </Link>
      ) : (
        hasPets && (
          <Link to="/book" className={`${buttonClass} w-full text-lg`}>
            <CalendarIcon />
            {t('booking.book')}
          </Link>
        )
      )}

      <EmergencyButton />

      <Card
        title={t('dashboard.pets')}
        action={
          hasPets && (
            <Link to="/pets/new" className="flex items-center gap-1 text-sm font-semibold text-brand-700">
              <PlusIcon width={18} height={18} />
              {t('dashboard.addPet')}
            </Link>
          )
        }
      >
        {pets.isLoading && <p className="text-sm text-slate-500">{t('dashboard.loading')}</p>}
        {pets.isError && <p className="text-sm text-danger-600">{t('errors.generic')}</p>}
        {pets.data?.length === 0 && (
          <EmptyState
            icon={<PawIcon width={32} height={32} />}
            title={t('dashboard.noPets')}
            hint={t('dashboard.noPetsHint')}
            action={
              <Link to="/pets/new" className={buttonClass}>
                <PlusIcon />
                {t('dashboard.addPet')}
              </Link>
            }
          />
        )}

        <ul className="space-y-2">
          {pets.data?.map((pet) => (
            <li key={pet.id}>
              <Link to={`/pets/${pet.id}`} className="flex items-center gap-3 rounded-xl p-2 hover:bg-slate-50">
                {pet.photo_url ? (
                  <img src={pet.photo_url} alt="" className="h-14 w-14 rounded-full object-cover" />
                ) : (
                  <div className="flex h-14 w-14 items-center justify-center rounded-full bg-brand-50 text-xl font-bold text-brand-700">
                    {pet.name.charAt(0)}
                  </div>
                )}
                <div className="min-w-0 flex-1">
                  <p className="font-semibold">{pet.name}</p>
                  <p className="truncate text-sm text-slate-600">
                    {pet.species.label}
                    {pet.breed.name ? ` · ${pet.breed.name}` : ''} · <PetAge age={pet.age} />
                  </p>
                </div>
                <ChevronIcon width={20} height={20} className="text-slate-400" />
              </Link>
            </li>
          ))}
        </ul>
      </Card>
    </AppShell>
  )
}
