import { useTranslation } from 'react-i18next'
import { localizeDigits } from '../lib/digits'
import type { Pet } from '../lib/pets'

export function PetAge({ age }: { age: Pet['age'] }) {
  const { t, i18n } = useTranslation()
  if (!age) return <>{t('common.unknown')}</>

  const value = t('pet.ageValue', {
    years: localizeDigits(age.years, i18n.language),
    months: localizeDigits(age.months, i18n.language),
  })
  return <>{age.estimated ? t('pet.ageApprox', { value }) : value}</>
}
