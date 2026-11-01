import { useState, type FormEvent } from 'react'
import { useTranslation } from 'react-i18next'
import { Link, useNavigate } from 'react-router-dom'
import { AppShell, Card, Field, buttonClass, inputClass } from '../components/AppShell'
import { toLatinDigits } from '../lib/digits'
import { errorMessage, fieldErrors } from '../lib/errors'
import { useBreeds, useCreatePet, useSpecies, type NewPet } from '../lib/pets'

const OTHER_BREED = 'other'

export function AddPet() {
  const { t } = useTranslation()
  const navigate = useNavigate()
  const species = useSpecies()
  const createPet = useCreatePet()

  const [form, setForm] = useState<NewPet>({ name: '', species: '', sex: 'unknown', neutered: null })
  const [breedChoice, setBreedChoice] = useState('')
  const breeds = useBreeds(form.species || undefined)

  const errors = fieldErrors(createPet.error)
  const set = <K extends keyof NewPet>(key: K, value: NewPet[K]) => setForm((f) => ({ ...f, [key]: value }))

  const submit = async (e: FormEvent) => {
    e.preventDefault()
    const payload: NewPet = {
      ...form,
      breed_id: breedChoice && breedChoice !== OTHER_BREED ? Number(breedChoice) : null,
      breed_text: breedChoice === OTHER_BREED ? form.breed_text : undefined,
      birth_date_jalali: form.birth_date_jalali ? toLatinDigits(form.birth_date_jalali) : undefined,
      weight_kg: form.weight_kg ? toLatinDigits(form.weight_kg) : undefined,
    }
    const pet = await createPet.mutateAsync(payload).catch(() => null)
    if (pet) navigate(`/pets/${pet.id}`, { replace: true })
  }

  return (
    <AppShell>
      <Link to="/" className="text-sm text-slate-600 underline">
        {t('common.back')}
      </Link>
      <Card title={t('dashboard.addPet')}>
        <form onSubmit={submit} className="space-y-4">
          <Field label={t('pet.name')} error={errors.name}>
            <input className={inputClass} value={form.name} onChange={(e) => set('name', e.target.value)} required />
          </Field>

          <Field label={t('pet.species')} error={errors.species}>
            <select
              className={inputClass}
              value={form.species}
              onChange={(e) => {
                set('species', e.target.value)
                setBreedChoice('')
              }}
              required
            >
              <option value="" disabled />
              {species.data?.map((s) => (
                <option key={s.value} value={s.value}>
                  {s.label}
                </option>
              ))}
            </select>
          </Field>

          {form.species && (
            <Field label={t('pet.breed')} error={errors.breed_id ?? errors.breed_text}>
              <select className={inputClass} value={breedChoice} onChange={(e) => setBreedChoice(e.target.value)}>
                <option value="">{t('pet.breedPick')}</option>
                {breeds.data?.map((b) => (
                  <option key={b.id} value={b.id}>
                    {b.name}
                  </option>
                ))}
                <option value={OTHER_BREED}>{t('pet.breedOther')}</option>
              </select>
              {breedChoice === OTHER_BREED && (
                <input
                  className={`${inputClass} mt-2`}
                  value={form.breed_text ?? ''}
                  onChange={(e) => set('breed_text', e.target.value)}
                />
              )}
            </Field>
          )}

          <Field label={t('pet.sex')} error={errors.sex}>
            <div className="flex gap-4">
              {(['male', 'female', 'unknown'] as const).map((sex) => (
                <label key={sex} className="flex items-center gap-1.5">
                  <input type="radio" name="sex" checked={form.sex === sex} onChange={() => set('sex', sex)} />
                  {sex === 'unknown' ? t('common.unknown') : t(`pet.${sex}`)}
                </label>
              ))}
            </div>
          </Field>

          <Field label={`${t('pet.birthDate')} (${t('common.optional')})`} error={errors.birth_date}>
            <input
              className={inputClass}
              dir="ltr"
              inputMode="numeric"
              placeholder={t('pet.birthDateHint')}
              value={form.birth_date_jalali ?? ''}
              onChange={(e) => set('birth_date_jalali', e.target.value)}
            />
            <label className="mt-2 flex items-center gap-1.5 text-sm">
              <input
                type="checkbox"
                checked={!!form.birth_date_estimated}
                onChange={(e) => set('birth_date_estimated', e.target.checked)}
              />
              {t('pet.birthEstimated')}
            </label>
          </Field>

          <div className="grid grid-cols-2 gap-4">
            <Field label={`${t('pet.weightKg')} (${t('common.optional')})`} error={errors.weight_kg}>
              <input
                className={inputClass}
                dir="ltr"
                inputMode="decimal"
                value={form.weight_kg ?? ''}
                onChange={(e) => set('weight_kg', e.target.value)}
              />
            </Field>
            <Field label={t('pet.neutered')} error={errors.neutered}>
              <select
                className={inputClass}
                value={form.neutered === null || form.neutered === undefined ? '' : String(form.neutered)}
                onChange={(e) => set('neutered', e.target.value === '' ? null : e.target.value === 'true')}
              >
                <option value="">{t('common.unknown')}</option>
                <option value="true">{t('common.yes')}</option>
                <option value="false">{t('common.no')}</option>
              </select>
            </Field>
          </div>

          {createPet.isError && Object.keys(errors).length === 0 && (
            <p role="alert" className="text-sm text-danger-600">
              {errorMessage(createPet.error, t('errors.generic'))}
            </p>
          )}

          <button type="submit" disabled={createPet.isPending} className={`${buttonClass} w-full`}>
            {t('pet.create')}
          </button>
        </form>
      </Card>
    </AppShell>
  )
}
