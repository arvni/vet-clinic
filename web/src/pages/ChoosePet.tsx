import { useTranslation } from 'react-i18next'
import { Link, Navigate, useNavigate } from 'react-router-dom'
import { AppShell, Card, ChoiceCard, EmptyState, buttonClass } from '../components/AppShell'
import { PawIcon, PlusIcon } from '../components/Icons'
import { usePets } from '../lib/pets'

/** Booking starts from a pet; with one pet there is nothing to ask. */
export function ChoosePet() {
  const { t } = useTranslation()
  const navigate = useNavigate()
  const pets = usePets()

  if (pets.isLoading) return <AppShell>{t('dashboard.loading')}</AppShell>
  if (pets.data?.length === 1) return <Navigate to={`/pets/${pets.data[0].id}/book`} replace />

  return (
    <AppShell>
      <Card title={t('booking.whichPet')}>
        {pets.data?.length === 0 && (
          <EmptyState
            icon={<PawIcon width={32} height={32} />}
            title={t('dashboard.noPets')}
            hint={t('booking.addPetFirst')}
            action={
              <Link to="/pets/new" className={buttonClass}>
                <PlusIcon />
                {t('dashboard.addPet')}
              </Link>
            }
          />
        )}
        <div className="space-y-2">
          {pets.data?.map((pet) => (
            <ChoiceCard
              key={pet.id}
              icon={<PawIcon />}
              title={pet.name}
              detail={pet.species.label}
              onClick={() => navigate(`/pets/${pet.id}/book`)}
            />
          ))}
        </div>
      </Card>
    </AppShell>
  )
}
