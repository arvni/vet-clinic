import { useTranslation } from 'react-i18next'
import { Link, useSearchParams } from 'react-router-dom'
import { AppShell, Card } from '../components/AppShell'

const OUTCOMES = ['paid', 'pending', 'needs_review', 'failed'] as const
type Outcome = (typeof OUTCOMES)[number]

export function PaymentResult() {
  const { t } = useTranslation()
  const [params] = useSearchParams()
  const status = params.get('status')
  const outcome: Outcome = OUTCOMES.find((o) => o === status) ?? 'failed'

  return (
    <AppShell>
      <Card title={t('payment.title')}>
        <p role="status">{t(`payment.${outcome}`)}</p>
        <Link to="/bookings" className="mt-3 block text-sm text-slate-600 underline">
          {t('booking.mine')}
        </Link>
      </Card>
    </AppShell>
  )
}
