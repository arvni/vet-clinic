import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Link, useNavigate, useParams } from 'react-router-dom'
import { AppShell, Card, Field, buttonClass, inputClass } from '../components/AppShell'
import {
  dayOptions,
  formatTime,
  reverseGeocode,
  servicesFor,
  useAddAddress,
  useAddresses,
  useCreateBooking,
  useHoldSlot,
  useServices,
  useSlots,
  type Address,
  type BookingLocation,
  type Hold,
  type Slot,
} from '../lib/bookings'
import { errorMessage, fieldErrors } from '../lib/errors'
import { formatMoney } from '../lib/money'
import { usePet } from '../lib/pets'

const chip = (selected: boolean) =>
  `rounded-xl px-3 py-2 text-sm ring-1 ${selected ? 'bg-brand-600 text-white ring-brand-600' : 'bg-white ring-slate-200'}`

export function BookVisit() {
  const { id } = useParams()
  const petId = Number(id)
  const { t, i18n } = useTranslation()
  const navigate = useNavigate()
  const pet = usePet(petId)
  const services = useServices()

  const [serviceId, setServiceId] = useState<number | null>(null)
  const [location, setLocation] = useState<BookingLocation | null>(null)
  const [address, setAddress] = useState<Address | null>(null)
  const [date, setDate] = useState<string | null>(null)
  const [slot, setSlot] = useState<Slot | null>(null)
  const [hold, setHold] = useState<Hold | null>(null)
  const [notes, setNotes] = useState('')

  const slots = useSlots(serviceId, date)
  const holdSlot = useHoldSlot()
  const createBooking = useCreateBooking()

  const available = pet.data ? servicesFor(services.data ?? [], pet.data.species.value) : []
  const service = available.find((s) => s.id === serviceId) ?? null
  const days = dayOptions(i18n.language)
  const needsAddress = location === 'home'
  const placeReady = location !== null && (!needsAddress || !!address?.zone)

  const clearSlot = () => {
    setSlot(null)
    setHold(null)
    holdSlot.reset()
    createBooking.reset()
  }

  const pickSlot = async (next: Slot) => {
    clearSlot()
    const taken = await holdSlot
      .mutateAsync({ service_id: serviceId!, vet_id: next.vet_id, starts_at: next.starts_at })
      .catch(() => null)
    if (taken) {
      setSlot(next)
      setHold(taken)
    } else {
      await slots.refetch()
    }
  }

  const confirm = async () => {
    if (!hold || !location) return
    const booking = await createBooking
      .mutateAsync({
        hold_token: hold.token,
        pet_id: petId,
        location,
        address_id: needsAddress ? address?.id : undefined,
        notes: notes || undefined,
      })
      .catch(() => null)
    if (booking) navigate('/bookings', { replace: true })
  }

  if (pet.isLoading) return <AppShell>{t('dashboard.loading')}</AppShell>
  if (!pet.data) return <AppShell>{t('errors.generic')}</AppShell>

  return (
    <AppShell>
      <Link to={`/pets/${petId}`} className="text-sm text-slate-600 underline">
        {t('common.back')}
      </Link>

      <Card title={t('booking.titleFor', { name: pet.data.name })}>
        <div className="space-y-5">
          <Field label={t('booking.service')}>
            <select
              className={inputClass}
              value={serviceId ?? ''}
              onChange={(e) => {
                setServiceId(Number(e.target.value))
                setLocation(null)
                setAddress(null)
                setDate(null)
                clearSlot()
              }}
            >
              <option value="" disabled />
              {available.map((s) => (
                <option key={s.id} value={s.id}>
                  {s.name}
                </option>
              ))}
            </select>
          </Field>

          {service && (
            <Field label={t('booking.where')}>
              <div className="flex flex-wrap gap-2">
                {service.locations.map((l) => (
                  <button
                    key={l}
                    type="button"
                    className={chip(location === l)}
                    onClick={() => {
                      setLocation(l)
                      clearSlot()
                    }}
                  >
                    {t(`booking.location.${l}`)}
                  </button>
                ))}
              </div>
            </Field>
          )}

          {needsAddress && <AddressPicker selected={address} onSelect={setAddress} />}

          {placeReady && (
            <Field label={t('booking.day')}>
              <div className="flex gap-2 overflow-x-auto pb-1">
                {days.map((d) => (
                  <button
                    key={d.date}
                    type="button"
                    className={`${chip(date === d.date)} shrink-0`}
                    onClick={() => {
                      setDate(d.date)
                      clearSlot()
                    }}
                  >
                    {d.label}
                  </button>
                ))}
              </div>
            </Field>
          )}

          {placeReady && date && (
            <Field label={t('booking.time')}>
              {slots.isLoading && <p className="text-sm text-slate-600">{t('dashboard.loading')}</p>}
              {slots.data?.length === 0 && <p className="text-sm text-slate-600">{t('booking.noSlots')}</p>}
              <div className="flex flex-wrap gap-2">
                {slots.data?.map((s) => (
                  <button
                    key={`${s.vet_id}-${s.starts_at}`}
                    type="button"
                    disabled={holdSlot.isPending}
                    className={chip(slot?.vet_id === s.vet_id && slot.starts_at === s.starts_at)}
                    onClick={() => pickSlot(s)}
                  >
                    {formatTime(s.starts_at, i18n.language)}
                    {s.vet_name ? ` · ${s.vet_name}` : ''}
                  </button>
                ))}
              </div>
              {holdSlot.isError && (
                <p role="alert" className="mt-2 text-sm text-danger-600">
                  {errorMessage(holdSlot.error, t('errors.generic'))}
                </p>
              )}
            </Field>
          )}

          {hold && service && (
            <>
              <p className="text-sm text-slate-600">
                {t('booking.heldUntil', { time: formatTime(hold.expires_at, i18n.language) })}
              </p>

              <Field label={`${t('booking.notes')} (${t('common.optional')})`}>
                <textarea className={inputClass} rows={2} value={notes} onChange={(e) => setNotes(e.target.value)} />
              </Field>

              <dl className="space-y-1 text-sm">
                <div className="flex justify-between">
                  <dt>{service.name}</dt>
                  <dd>
                    {service.price ? formatMoney(service.price.rials, i18n.language) : t('booking.priceOnRequest')}
                  </dd>
                </div>
                {needsAddress && address?.zone && (
                  <div className="flex justify-between">
                    <dt>{t('booking.homeFee')}</dt>
                    <dd>{formatMoney(address.zone.home_visit_fee.rials, i18n.language)}</dd>
                  </div>
                )}
              </dl>

              {createBooking.isError && (
                <p role="alert" className="text-sm text-danger-600">
                  {errorMessage(createBooking.error, t('errors.generic'))}
                </p>
              )}

              <button
                type="button"
                disabled={createBooking.isPending}
                className={`${buttonClass} w-full`}
                onClick={confirm}
              >
                {t('booking.confirm')}
              </button>
            </>
          )}
        </div>
      </Card>
    </AppShell>
  )
}

function AddressPicker({ selected, onSelect }: { selected: Address | null; onSelect: (a: Address) => void }) {
  const { t } = useTranslation()
  const addresses = useAddresses()
  const addAddress = useAddAddress()
  const [adding, setAdding] = useState(false)
  const [label, setLabel] = useState('')
  const [line, setLine] = useState('')
  const [point, setPoint] = useState<{ lat: number; lng: number } | null>(null)
  const [locating, setLocating] = useState<'idle' | 'busy' | 'failed'>('idle')
  const errors = fieldErrors(addAddress.error)

  const locate = () => {
    setLocating('busy')
    navigator.geolocation.getCurrentPosition(
      async ({ coords }) => {
        setPoint({ lat: coords.latitude, lng: coords.longitude })
        setLocating('idle')
        const found = await reverseGeocode(coords.latitude, coords.longitude).catch(() => null)
        if (found) setLine((current) => current || found)
      },
      () => setLocating('failed'),
    )
  }

  const save = async () => {
    if (!point) return
    const created = await addAddress.mutateAsync({ label, line, ...point }).catch(() => null)
    if (created) {
      onSelect(created)
      setAdding(false)
    }
  }

  return (
    <Field label={t('booking.address')}>
      <div className="flex flex-wrap gap-2">
        {addresses.data?.map((a) => (
          <button key={a.id} type="button" className={chip(selected?.id === a.id)} onClick={() => onSelect(a)}>
            {a.label}
          </button>
        ))}
        <button type="button" className={chip(adding)} onClick={() => setAdding(!adding)}>
          {t('booking.addAddress')}
        </button>
      </div>

      {selected && !selected.zone && (
        <p role="alert" className="mt-2 text-sm text-danger-600">
          {t('booking.outsideZones')}
        </p>
      )}

      {adding && (
        <div className="mt-3 space-y-3">
          <Field label={t('booking.addressLabel')} error={errors.label}>
            <input className={inputClass} value={label} onChange={(e) => setLabel(e.target.value)} />
          </Field>
          <button type="button" className={chip(!!point)} disabled={locating === 'busy'} onClick={locate}>
            {point ? t('booking.located') : t('booking.useLocation')}
          </button>
          {locating === 'failed' && (
            <p role="alert" className="text-sm text-danger-600">
              {t('booking.locationFailed')}
            </p>
          )}
          <Field label={t('booking.addressLine')} error={errors.line ?? errors.lat}>
            <textarea className={inputClass} rows={2} value={line} onChange={(e) => setLine(e.target.value)} />
          </Field>
          <button
            type="button"
            className={buttonClass}
            disabled={!point || !label || !line || addAddress.isPending}
            onClick={save}
          >
            {t('booking.saveAddress')}
          </button>
        </div>
      )}
    </Field>
  )
}
