import { useTranslation } from 'react-i18next'
import type { Booking } from '../lib/bookings'

const STYLES: Record<Booking['status'], string> = {
  confirmed: 'bg-emerald-100 text-emerald-800',
  pending_payment: 'bg-amber-100 text-amber-800',
  cancelled: 'bg-slate-200 text-slate-700',
}

export function BookingStatus({ status }: { status: Booking['status'] }) {
  const { t } = useTranslation()

  return (
    <span className={`shrink-0 rounded-full px-2.5 py-1 text-xs font-semibold ${STYLES[status]}`}>
      {t(`booking.status.${status}`)}
    </span>
  )
}
