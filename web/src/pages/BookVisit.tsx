import { useState, type ReactNode } from 'react'
import { useTranslation } from 'react-i18next'
import { useNavigate, useParams } from 'react-router-dom'
import { AppShell, ChoiceCard, EmptyState, Field, buttonClass, inputClass } from '../components/AppShell'
import {
  CalendarIcon,
  CheckIcon,
  ChevronIcon,
  ClinicIcon,
  HomeIcon,
  PawIcon,
  VideoIcon,
} from '../components/Icons'
import {
  dayOptions,
  formatDateTime,
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
  type Service,
  type Slot,
} from '../lib/bookings'
import { localizeDigits } from '../lib/digits'
import { errorMessage, fieldErrors } from '../lib/errors'
import { formatMoney } from '../lib/money'
import { usePet } from '../lib/pets'

const chip = (selected: boolean) =>
  `min-h-11 rounded-xl px-3 py-2 text-sm font-medium ring-1 ${selected ? 'bg-brand-600 text-white ring-brand-600' : 'bg-white ring-slate-200'}`

const STEPS = ['service', 'place', 'time', 'confirm'] as const
type Step = (typeof STEPS)[number]

const PLACE_ICONS: Record<BookingLocation, ReactNode> = {
  clinic: <ClinicIcon />,
  home: <HomeIcon />,
  online: <VideoIcon />,
}

export function BookVisit() {
  const { id } = useParams()
  const petId = Number(id)
  const { t, i18n } = useTranslation()
  const navigate = useNavigate()
  const pet = usePet(petId)
  const services = useServices()

  const [step, setStep] = useState<Step>('service')
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
  const money = (rials: string) => formatMoney(rials, i18n.language)
  const fa = (n: number) => localizeDigits(n, i18n.language)

  const clearSlot = () => {
    setSlot(null)
    setHold(null)
    holdSlot.reset()
    createBooking.reset()
  }

  const chooseService = (next: Service) => {
    setServiceId(next.id)
    setAddress(null)
    setDate(null)
    clearSlot()
    // One place on offer means there is no question to ask.
    if (next.locations.length === 1 && next.locations[0] !== 'home') {
      setLocation(next.locations[0])
      setStep('time')
    } else {
      setLocation(next.locations.length === 1 ? next.locations[0] : null)
      setStep('place')
    }
  }

  const choosePlace = (next: BookingLocation) => {
    setLocation(next)
    clearSlot()
    if (next !== 'home') setStep('time')
  }

  const pickSlot = async (next: Slot) => {
    clearSlot()
    const taken = await holdSlot
      .mutateAsync({ service_id: serviceId!, vet_id: next.vet_id, starts_at: next.starts_at })
      .catch(() => null)
    if (taken) {
      setSlot(next)
      setHold(taken)
      setStep('confirm')
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

  const back = () => {
    const skipPlace = service?.locations.length === 1 && service.locations[0] !== 'home'
    if (step === 'service') navigate(`/pets/${petId}`)
    else if (step === 'place') setStep('service')
    else if (step === 'time') setStep(skipPlace ? 'service' : 'place')
    else setStep('time')
  }

  if (pet.isLoading) return <AppShell>{t('dashboard.loading')}</AppShell>
  if (!pet.data) return <AppShell>{t('errors.generic')}</AppShell>

  return (
    <AppShell>
      <button type="button" onClick={back} className="inline-flex items-center gap-1 py-1 text-sm font-medium text-slate-600">
        <ChevronIcon width={18} height={18} className="rotate-180" />
        {t('common.back')}
      </button>

      <h1 className="text-2xl font-bold">{t('booking.titleFor', { name: pet.data.name })}</h1>

      <ol className="flex gap-1.5" aria-label={t('booking.progress')}>
        {STEPS.map((s, i) => (
          <li
            key={s}
            aria-current={s === step ? 'step' : undefined}
            className={`h-1.5 flex-1 rounded-full ${i <= STEPS.indexOf(step) ? 'bg-brand-600' : 'bg-slate-200'}`}
          />
        ))}
      </ol>
      <p className="text-sm text-slate-600">
        {t('booking.stepOf', { step: fa(STEPS.indexOf(step) + 1), total: fa(STEPS.length) })}
      </p>

      {step === 'service' && (
        <section className="space-y-2">
          <h2 className="text-lg font-semibold">{t('booking.whatDoesNeed', { name: pet.data.name })}</h2>
          {services.isLoading && <p className="text-sm text-slate-600">{t('dashboard.loading')}</p>}
          {available.map((s) => (
            <ChoiceCard
              key={s.id}
              icon={<PawIcon />}
              title={s.name}
              detail={t('booking.minutes', { count: s.duration_minutes, n: fa(s.duration_minutes) })}
              selected={s.id === serviceId}
              onClick={() => chooseService(s)}
              trailing={
                <span className="text-sm font-semibold text-brand-700">
                  {s.price ? money(s.price.rials) : t('booking.priceOnRequest')}
                </span>
              }
            />
          ))}
        </section>
      )}

      {step === 'place' && service && (
        <section className="space-y-2">
          <h2 className="text-lg font-semibold">{t('booking.where')}</h2>
          {service.locations.map((l) => (
            <ChoiceCard
              key={l}
              icon={PLACE_ICONS[l]}
              title={t(`booking.location.${l}`)}
              detail={t(`booking.locationHint.${l}`)}
              selected={location === l}
              onClick={() => choosePlace(l)}
            />
          ))}

          {needsAddress && (
            <div className="rounded-2xl bg-white p-4 ring-1 ring-slate-200">
              <AddressPicker selected={address} onSelect={setAddress} />
              {address?.zone && (
                <div className="mt-3 space-y-2">
                  <p className="text-sm text-slate-600">
                    {t('booking.homeFeeNote', { amount: money(address.zone.home_visit_fee.rials) })}
                  </p>
                  <button type="button" className={`${buttonClass} w-full`} onClick={() => setStep('time')}>
                    {t('booking.next')}
                  </button>
                </div>
              )}
            </div>
          )}
        </section>
      )}

      {step === 'time' && (
        <section className="space-y-4">
          <div>
            <h2 className="mb-2 text-lg font-semibold">{t('booking.day')}</h2>
            <div className="-mx-4 flex gap-2 overflow-x-auto px-4 pb-1">
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
          </div>

          {date ? (
            <div>
              <h2 className="mb-2 text-lg font-semibold">{t('booking.time')}</h2>
              {slots.isLoading && <p className="text-sm text-slate-600">{t('dashboard.loading')}</p>}
              {slots.data?.length === 0 && (
                <EmptyState
                  icon={<CalendarIcon width={32} height={32} />}
                  title={t('booking.noSlots')}
                  hint={t('booking.noSlotsHint')}
                />
              )}
              <div className="grid grid-cols-3 gap-2 sm:grid-cols-4">
                {slots.data?.map((s) => (
                  <button
                    key={`${s.vet_id}-${s.starts_at}`}
                    type="button"
                    disabled={holdSlot.isPending}
                    className={chip(slot?.vet_id === s.vet_id && slot.starts_at === s.starts_at)}
                    title={s.vet_name ?? undefined}
                    onClick={() => pickSlot(s)}
                  >
                    {formatTime(s.starts_at, i18n.language)}
                  </button>
                ))}
              </div>
              {holdSlot.isError && (
                <p role="alert" className="mt-2 text-sm text-danger-600">
                  {errorMessage(holdSlot.error, t('errors.generic'))}
                </p>
              )}
            </div>
          ) : (
            <p className="text-sm text-slate-600">{t('booking.pickDayFirst')}</p>
          )}
        </section>
      )}

      {step === 'confirm' && hold && service && slot && location && (
        <section className="space-y-4">
          <div className="rounded-2xl bg-white p-4 ring-1 ring-slate-200">
            <h2 className="mb-3 text-lg font-semibold">{t('booking.review')}</h2>
            <dl className="space-y-2 text-sm">
              <Row label={t('booking.service')} value={service.name} />
              <Row label={t('booking.when')} value={formatDateTime(slot.starts_at, i18n.language)} />
              <Row label={t('booking.where')} value={address && needsAddress ? address.label : t(`booking.location.${location}`)} />
              {slot.vet_name && <Row label={t('booking.vet')} value={slot.vet_name} />}
              <hr className="border-slate-200" />
              <Row
                label={service.name}
                value={service.price ? money(service.price.rials) : t('booking.priceOnRequest')}
              />
              {needsAddress && address?.zone && (
                <Row label={t('booking.homeFee')} value={money(address.zone.home_visit_fee.rials)} />
              )}
            </dl>
          </div>

          <p className="rounded-xl bg-brand-50 px-3 py-2 text-sm text-brand-700">
            {t('booking.heldUntil', { time: formatTime(hold.expires_at, i18n.language) })}
          </p>

          <Field label={`${t('booking.notes')} (${t('common.optional')})`}>
            <textarea className={inputClass} rows={2} value={notes} onChange={(e) => setNotes(e.target.value)} />
          </Field>

          {createBooking.isError && (
            <p role="alert" className="text-sm text-danger-600">
              {errorMessage(createBooking.error, t('errors.generic'))}
            </p>
          )}

          <button
            type="button"
            disabled={createBooking.isPending}
            className={`${buttonClass} w-full text-lg`}
            onClick={confirm}
          >
            <CheckIcon />
            {t('booking.confirm')}
          </button>
        </section>
      )}
    </AppShell>
  )
}

function Row({ label, value }: { label: string; value: string }) {
  return (
    <div className="flex justify-between gap-4">
      <dt className="text-slate-600">{label}</dt>
      <dd className="text-end font-medium">{value}</dd>
    </div>
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
