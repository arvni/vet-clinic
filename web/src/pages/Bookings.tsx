import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Link } from 'react-router-dom'
import { AppShell, Card } from '../components/AppShell'
import { formatDateTime, useBookings, useCancelBooking, type Booking } from '../lib/bookings'
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
  const cancel = useCancelBooking()
  const [confirming, setConfirming] = useState(false)
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
      {booking.status === 'cancelled' && booking.refund_due.rials !== '0' && (
        <p className="text-slate-600">
          {t('booking.refundDue', { amount: formatMoney(booking.refund_due.rials, i18n.language) })}
        </p>
      )}
      <div className="flex flex-wrap items-center gap-3">
        {booking.can_reschedule && (
          <Link to={`/bookings/${booking.id}/reschedule`} className="text-brand-700 underline">
            {t('booking.reschedule')}
          </Link>
        )}
        {booking.can_cancel && !confirming && (
          <button type="button" className="text-danger-600 underline" onClick={() => setConfirming(true)}>
            {t('booking.cancel')}
          </button>
        )}
        {confirming && (
          <>
            <span>{t('booking.cancelConfirm')}</span>
            <button
              type="button"
              disabled={cancel.isPending}
              className="text-danger-600 underline"
              onClick={() => cancel.mutate(booking.id, { onSettled: () => setConfirming(false) })}
            >
              {t('common.yes')}
            </button>
            <button type="button" className="underline" onClick={() => setConfirming(false)}>
              {t('common.no')}
            </button>
          </>
        )}
      </div>
      {cancel.isError && (
        <p role="alert" className="text-danger-600">
          {errorMessage(cancel.error, t('errors.generic'))}
        </p>
      )}
    </li>
  )
}
