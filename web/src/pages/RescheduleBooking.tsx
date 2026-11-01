import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Link, useNavigate, useParams } from 'react-router-dom'
import { AppShell, Card, Field, buttonClass } from '../components/AppShell'
import {
  dayOptions,
  formatTime,
  useBookings,
  useHoldSlot,
  useRescheduleBooking,
  useSlots,
  type Hold,
  type Slot,
} from '../lib/bookings'
import { errorMessage } from '../lib/errors'

const chip = (selected: boolean) =>
  `rounded-xl px-3 py-2 text-sm ring-1 ${selected ? 'bg-brand-600 text-white ring-brand-600' : 'bg-white ring-slate-200'}`

export function RescheduleBooking() {
  const { id } = useParams()
  const { t, i18n } = useTranslation()
  const navigate = useNavigate()
  const bookings = useBookings()
  const booking = bookings.data?.find((b) => b.id === Number(id))

  const [date, setDate] = useState<string | null>(null)
  const [slot, setSlot] = useState<Slot | null>(null)
  const [hold, setHold] = useState<Hold | null>(null)
  const slots = useSlots(booking?.service.id ?? null, date)
  const holdSlot = useHoldSlot()
  const reschedule = useRescheduleBooking()

  if (bookings.isLoading) return <AppShell>{t('dashboard.loading')}</AppShell>
  if (!booking) return <AppShell>{t('errors.generic')}</AppShell>

  const pick = async (next: Slot) => {
    setSlot(null)
    setHold(null)
    const taken = await holdSlot
      .mutateAsync({ service_id: booking.service.id, vet_id: next.vet_id, starts_at: next.starts_at })
      .catch(() => null)
    if (taken) {
      setSlot(next)
      setHold(taken)
    } else {
      await slots.refetch()
    }
  }

  const save = async () => {
    if (!hold) return
    const moved = await reschedule.mutateAsync({ id: booking.id, hold_token: hold.token }).catch(() => null)
    if (moved) navigate('/bookings', { replace: true })
  }

  return (
    <AppShell>
      <Link to="/bookings" className="text-sm text-slate-600 underline">
        {t('common.back')}
      </Link>
      <Card title={t('booking.rescheduleTitle', { name: booking.service.name })}>
        <div className="space-y-5">
          <Field label={t('booking.day')}>
            <div className="flex gap-2 overflow-x-auto pb-1">
              {dayOptions(i18n.language).map((d) => (
                <button
                  key={d.date}
                  type="button"
                  className={`${chip(date === d.date)} shrink-0`}
                  onClick={() => {
                    setDate(d.date)
                    setSlot(null)
                    setHold(null)
                  }}
                >
                  {d.label}
                </button>
              ))}
            </div>
          </Field>

          {date && (
            <Field label={t('booking.time')}>
              {slots.data?.length === 0 && <p className="text-sm text-slate-600">{t('booking.noSlots')}</p>}
              <div className="flex flex-wrap gap-2">
                {slots.data?.map((s) => (
                  <button
                    key={`${s.vet_id}-${s.starts_at}`}
                    type="button"
                    disabled={holdSlot.isPending}
                    className={chip(slot?.vet_id === s.vet_id && slot.starts_at === s.starts_at)}
                    onClick={() => pick(s)}
                  >
                    {formatTime(s.starts_at, i18n.language)}
                    {s.vet_name ? ` · ${s.vet_name}` : ''}
                  </button>
                ))}
              </div>
            </Field>
          )}

          {(holdSlot.isError || reschedule.isError) && (
            <p role="alert" className="text-sm text-danger-600">
              {errorMessage(holdSlot.error ?? reschedule.error, t('errors.generic'))}
            </p>
          )}

          {hold && (
            <button type="button" disabled={reschedule.isPending} className={`${buttonClass} w-full`} onClick={save}>
              {t('booking.confirm')}
            </button>
          )}
        </div>
      </Card>
    </AppShell>
  )
}
