import { useState, type FormEvent } from 'react'
import { useTranslation } from 'react-i18next'
import { Link, useParams } from 'react-router-dom'
import { AppShell, Card, Field, buttonClass, inputClass } from '../components/AppShell'
import { PetAge } from '../components/PetAge'
import { localizeDigits, toLatinDigits } from '../lib/digits'
import { fieldErrors } from '../lib/errors'
import {
  useAddAllergy,
  useAddWeight,
  usePet,
  usePetWeights,
  useRemoveAllergy,
  useUploadDocument,
  useUploadPhoto,
  type Pet,
} from '../lib/pets'

export function PetProfile() {
  const { id } = useParams()
  const petId = Number(id)
  const { t } = useTranslation()
  const pet = usePet(petId)

  if (pet.isLoading) return <AppShell>{t('dashboard.loading')}</AppShell>
  if (!pet.data) return <AppShell>{t('errors.generic')}</AppShell>

  return (
    <AppShell>
      <Link to="/" className="text-sm text-slate-600 underline">
        {t('common.back')}
      </Link>
      <Summary pet={pet.data} />
      <Link to={`/pets/${petId}/book`} className={`${buttonClass} w-full text-lg`}>
        {t('booking.book')}
      </Link>
      <Weights petId={petId} />
      <Allergies pet={pet.data} />
      <Documents pet={pet.data} />
    </AppShell>
  )
}

function Summary({ pet }: { pet: Pet }) {
  const { t, i18n } = useTranslation()
  const uploadPhoto = useUploadPhoto(pet.id)

  return (
    <section className="flex items-center gap-4 rounded-2xl bg-white p-4 ring-1 ring-slate-200">
      <label className="relative cursor-pointer" title={t('pet.changePhoto')}>
        {pet.photo_url ? (
          <img src={pet.photo_url} alt="" className="h-20 w-20 rounded-full object-cover" />
        ) : (
          <div className="flex h-20 w-20 items-center justify-center rounded-full bg-brand-50 text-2xl text-brand-700">
            {pet.name.charAt(0)}
          </div>
        )}
        <input
          type="file"
          accept="image/jpeg,image/png,image/webp"
          className="sr-only"
          onChange={(e) => e.target.files?.[0] && uploadPhoto.mutate(e.target.files[0])}
        />
      </label>
      <div className="min-w-0 space-y-0.5">
        <h1 className="text-xl font-semibold">{pet.name}</h1>
        <p className="text-sm text-slate-600">
          {pet.species.label}
          {pet.breed.name ? ` · ${pet.breed.name}` : ''} · {pet.sex.label}
        </p>
        <p className="text-sm text-slate-600">
          {t('pet.age')}: <PetAge age={pet.age} />
          {pet.birth_date_jalali ? ` (${pet.birth_date_jalali})` : ''}
        </p>
        {pet.microchip_no && (
          <p className="text-sm text-slate-600">
            {t('pet.microchip')}: <span dir="ltr">{localizeDigits(pet.microchip_no, i18n.language)}</span>
          </p>
        )}
      </div>
    </section>
  )
}

function Weights({ petId }: { petId: number }) {
  const { t, i18n } = useTranslation()
  const weights = usePetWeights(petId)
  const addWeight = useAddWeight(petId)
  const [kg, setKg] = useState('')

  const submit = (e: FormEvent) => {
    e.preventDefault()
    addWeight.mutate(toLatinDigits(kg), { onSuccess: () => setKg('') })
  }

  return (
    <Card title={t('pet.weights')}>
      <form onSubmit={submit} className="mb-3 flex gap-2">
        <input
          className={inputClass}
          dir="ltr"
          inputMode="decimal"
          placeholder={t('pet.weightKg')}
          value={kg}
          onChange={(e) => setKg(e.target.value)}
          required
        />
        <button type="submit" className={`${buttonClass} shrink-0`} disabled={addWeight.isPending}>
          {t('pet.addWeight')}
        </button>
      </form>
      {fieldErrors(addWeight.error).weight_kg && (
        <p className="mb-2 text-sm text-danger-600">{fieldErrors(addWeight.error).weight_kg}</p>
      )}
      {weights.data?.length === 0 && <p className="text-sm text-slate-500">{t('pet.noWeights')}</p>}
      <ul className="divide-y divide-slate-100 text-sm">
        {weights.data?.map((w) => (
          <li key={w.id} className="flex justify-between py-2">
            <span>
              <span dir="ltr">{localizeDigits(w.kg.replace(/\.?0+$/, ''), i18n.language)} kg</span>
              <span className="text-slate-400"> · {t(`pet.bySource_${w.source}`)}</span>
            </span>
            <span className="text-slate-500">{w.measured_at_jalali}</span>
          </li>
        ))}
      </ul>
    </Card>
  )
}

function Allergies({ pet }: { pet: Pet }) {
  const { t } = useTranslation()
  const addAllergy = useAddAllergy(pet.id)
  const removeAllergy = useRemoveAllergy(pet.id)
  const [type, setType] = useState('drug')
  const [substance, setSubstance] = useState('')

  const submit = (e: FormEvent) => {
    e.preventDefault()
    addAllergy.mutate({ type, substance }, { onSuccess: () => setSubstance('') })
  }

  return (
    <Card title={t('pet.allergies')}>
      {pet.allergies?.length === 0 && <p className="mb-3 text-sm text-slate-500">{t('pet.noAllergies')}</p>}
      <ul className="mb-3 flex flex-wrap gap-2">
        {pet.allergies?.map((a) => (
          <li key={a.id} className="flex items-center gap-1 rounded-full bg-amber-50 px-3 py-1 text-sm text-amber-800">
            {a.substance} <span className="text-amber-600">({a.type.label})</span>
            <button
              type="button"
              aria-label={t('common.remove')}
              className="ms-1 text-amber-700"
              onClick={() => removeAllergy.mutate(a.id)}
            >
              ×
            </button>
          </li>
        ))}
      </ul>
      <form onSubmit={submit} className="flex gap-2">
        <select className={`${inputClass} w-32 shrink-0`} value={type} onChange={(e) => setType(e.target.value)}>
          {(['drug', 'food', 'other'] as const).map((v) => (
            <option key={v} value={v}>
              {t(`pet.allergy_${v}`)}
            </option>
          ))}
        </select>
        <input
          className={inputClass}
          placeholder={t('pet.substance')}
          value={substance}
          onChange={(e) => setSubstance(e.target.value)}
          required
        />
        <button type="submit" className={`${buttonClass} shrink-0`} disabled={addAllergy.isPending}>
          {t('common.add')}
        </button>
      </form>
    </Card>
  )
}

function Documents({ pet }: { pet: Pet }) {
  const { t, i18n } = useTranslation()
  const upload = useUploadDocument(pet.id)
  const [kind, setKind] = useState('national')
  const [number, setNumber] = useState('')
  const [file, setFile] = useState<File | undefined>()
  const errors = fieldErrors(upload.error)

  const submit = (e: FormEvent) => {
    e.preventDefault()
    upload.mutate(
      { kind, number: toLatinDigits(number) || undefined, file },
      {
        onSuccess: () => {
          setNumber('')
          setFile(undefined)
          ;(e.target as HTMLFormElement).reset()
        },
      },
    )
  }

  return (
    <Card title={t('pet.documents')}>
      {pet.documents?.length === 0 && <p className="mb-3 text-sm text-slate-500">{t('pet.noDocuments')}</p>}
      <ul className="mb-4 divide-y divide-slate-100 text-sm">
        {pet.documents?.map((d) => (
          <li key={d.id} className="flex items-center justify-between gap-2 py-2">
            <span>
              {d.kind.label}
              {d.number && (
                <span dir="ltr" className="text-slate-500">
                  {' '}
                  · {localizeDigits(d.number, i18n.language)}
                </span>
              )}
            </span>
            <span className="flex items-center gap-2">
              <span
                className={`rounded-full px-2 py-0.5 text-xs ${
                  d.verified ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-600'
                }`}
              >
                {d.verified ? t('pet.verified') : t('pet.unverified')}
              </span>
              {d.file_url && (
                <a href={d.file_url} target="_blank" rel="noreferrer" className="text-brand-600 underline">
                  {t('pet.view')}
                </a>
              )}
            </span>
          </li>
        ))}
      </ul>

      <form onSubmit={submit} className="space-y-3">
        <div className="grid grid-cols-2 gap-3">
          <Field label={t('pet.docKind')}>
            <select className={inputClass} value={kind} onChange={(e) => setKind(e.target.value)}>
              {(['national', 'certificate', 'other'] as const).map((k) => (
                <option key={k} value={k}>
                  {t(`pet.doc_${k}`)}
                </option>
              ))}
            </select>
          </Field>
          <Field label={t('pet.docNumber')} error={errors.number}>
            <input
              className={inputClass}
              dir="ltr"
              value={number}
              onChange={(e) => setNumber(e.target.value)}
              required={kind === 'national'}
            />
          </Field>
        </div>
        <Field label={t('pet.docFile')} error={errors.file}>
          <input
            type="file"
            accept="image/jpeg,image/png,image/webp,application/pdf"
            className="block w-full text-sm"
            onChange={(e) => setFile(e.target.files?.[0])}
            required={kind === 'national'}
          />
        </Field>
        <button type="submit" className={buttonClass} disabled={upload.isPending}>
          {t('pet.upload')}
        </button>
      </form>
    </Card>
  )
}
