import { useTranslation } from 'react-i18next'
import { Link } from 'react-router-dom'
import { useAuth } from '../auth/AuthContext'
import { AppShell, Card } from '../components/AppShell'
import { PetAge } from '../components/PetAge'
import { localizeDigits } from '../lib/digits'
import { usePets } from '../lib/pets'

export function Dashboard() {
  const { t, i18n } = useTranslation()
  const { user } = useAuth()
  const pets = usePets()

  return (
    <AppShell>
      <h1 className="text-xl font-semibold">
        {t('dashboard.welcome')}
        {user?.name ? (i18n.language === 'fa' ? `، ${user.name}` : `, ${user.name}`) : ''}
      </h1>

      {/* Emergency stays visible on owner screens (FR-3.4); booking flow arrives in S2. */}
      <button type="button" className="w-full rounded-xl bg-danger-600 py-3 text-lg font-semibold text-white shadow-sm">
        {t('dashboard.emergency')}
      </button>

      <Link to="/bookings" className="block rounded-2xl bg-white p-4 text-sm font-medium ring-1 ring-slate-200">
        {t('booking.mine')}
      </Link>
      <Card
        title={t('dashboard.pets')}
        action={
          <Link to="/pets/new" className="rounded-lg bg-brand-600 px-3 py-1.5 text-sm font-medium text-white">
            {t('dashboard.addPet')}
          </Link>
        }
      >
        {pets.isLoading && <p className="text-sm text-slate-500">{t('dashboard.loading')}</p>}
        {pets.isError && <p className="text-sm text-danger-600">{t('errors.generic')}</p>}
        {pets.data?.length === 0 && <p className="text-sm text-slate-500">{t('dashboard.noPets')}</p>}

        <ul className="divide-y divide-slate-100">
          {pets.data?.map((pet) => (
            <li key={pet.id}>
              <Link to={`/pets/${pet.id}`} className="flex items-center gap-3 py-3 hover:bg-slate-50">
                {pet.photo_url ? (
                  <img src={pet.photo_url} alt="" className="h-12 w-12 rounded-full object-cover" />
                ) : (
                  <div className="flex h-12 w-12 items-center justify-center rounded-full bg-brand-50 text-lg text-brand-700">
                    {pet.name.charAt(0)}
                  </div>
                )}
                <div className="min-w-0 flex-1">
                  <p className="font-medium">{pet.name}</p>
                  <p className="truncate text-sm text-slate-500">
                    {pet.species.label}
                    {pet.breed.name ? ` · ${pet.breed.name}` : ''} · <PetAge age={pet.age} />
                  </p>
                </div>
                {pet.latest_weight && (
                  <span className="text-sm text-slate-500" dir="ltr">
                    {localizeDigits(pet.latest_weight.kg.replace(/\.?0+$/, ''), i18n.language)} kg
                  </span>
                )}
              </Link>
            </li>
          ))}
        </ul>
      </Card>
    </AppShell>
  )
}
