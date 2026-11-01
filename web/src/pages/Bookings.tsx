import { useTranslation } from 'react-i18next'
import { Link } from 'react-router-dom'
import { AppShell, Card } from '../components/AppShell'
import { formatDateTime, useBookings, type Booking } from '../lib/bookings'
import { errorMessage } from '../lib/errors'
import { formatMoney } from '../lib/money'

export function Bookings() {
  const { t } = useTranslation()
  const bookings = useBookings()

  return (
    <AppShell>
      <Link to="/" className="text-sm text-slate-600 underline">
        {t('common.back')}
      </Link>
      <Card title={t('booking.mine')}>
        {bookings.isLoading && <p className="text-sm text-slate-600">{t('dashboard.loading')}</p>}
        {bookings.isError && (
          <p role="alert" className="text-sm text-danger-600">
            {errorMessage(bookings.error, t('errors.generic'))}
          </p>
        )}
        {bookings.data?.length === 0 && <p className="text-sm text-slate-600">{t('booking.none')}</p>}
        <ul className="divide-y divide-slate-200">
          {bookings.data?.map((b) => <BookingRow key={b.id} booking={b} />)}
        </ul>
      </Card>
    </AppShell>
  )
}

function BookingRow({ booking }: { booking: Booking }) {
  const { t, i18n } = useTranslation()
  // Home visits owe the zone fee up front; prepaid services owe the service price.
  const due = booking.location === 'home' ? booking.home_visit_fee : booking.service_price

  return (
    <li className="space-y-1 py-3 text-sm">
      <div className="flex justify-between gap-3 font-medium">
        <span>
          {booking.service.name} · {booking.pet.name}
        </span>
        <span>{t(`booking.status.${booking.status}`)}</span>
      </div>
      <p className="text-slate-600">
        {formatDateTime(booking.starts_at, i18n.language)} · {t(`booking.location.${booking.location}`)}
        {booking.vet_name ? ` · ${booking.vet_name}` : ''}
      </p>
      {booking.status === 'pending_payment' && due && (
        <p className="text-slate-600">{t('booking.payAmount', { amount: formatMoney(due.rials, i18n.language) })}</p>
      )}
    </li>
  )
}
