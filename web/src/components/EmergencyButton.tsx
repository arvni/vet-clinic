import { useQuery } from '@tanstack/react-query'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { fetchClinicPhone, quickPosition, raiseEmergency } from '../lib/emergency'

type State = 'idle' | 'sending' | 'sent' | 'failed'

export function EmergencyButton() {
  const { t } = useTranslation()
  const [state, setState] = useState<State>('idle')
  const phone = useQuery({ queryKey: ['clinic-phone'], queryFn: fetchClinicPhone, staleTime: Infinity })

  const raise = async () => {
    setState('sending')
    try {
      await raiseEmergency(await quickPosition())
      setState('sent')
    } catch {
      setState('failed')
    }
  }

  return (
    <div className="space-y-2">
      <button
        type="button"
        disabled={state === 'sending' || state === 'sent'}
        onClick={raise}
        className="w-full rounded-xl bg-danger-600 py-3 text-lg font-semibold text-white shadow-sm"
      >
        {t('dashboard.emergency')}
      </button>
      {state === 'sent' && <p role="status">{t('emergency.sent')}</p>}
      {state === 'failed' && (
        <p role="alert" className="text-danger-600">
          {t('emergency.failed')}
        </p>
      )}
      {state !== 'idle' && phone.data && (
        <a href={`tel:${phone.data}`} dir="ltr" className="block rounded-xl bg-white py-3 text-center font-semibold ring-1 ring-slate-200">
          {t('emergency.call', { phone: phone.data })}
        </a>
      )}
    </div>
  )
}
