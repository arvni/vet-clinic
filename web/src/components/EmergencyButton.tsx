import { useQuery } from '@tanstack/react-query'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { fetchClinicPhone, quickPosition, raiseEmergency } from '../lib/emergency'
import { AlertIcon, PhoneIcon } from './Icons'

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
        className="flex min-h-14 w-full items-center gap-3 rounded-2xl bg-danger-600 px-4 py-2.5 text-start text-white shadow-sm disabled:opacity-80"
      >
        <AlertIcon />
        <span>
          <span className="block font-bold">{t('dashboard.emergency')}</span>
          <span className="block text-sm opacity-90">{t('emergency.hint')}</span>
        </span>
      </button>
      {state === 'sent' && (
        <p role="status" className="rounded-xl bg-brand-50 px-3 py-2 text-sm text-brand-700">
          {t('emergency.sent')}
        </p>
      )}
      {state === 'failed' && (
        <p role="alert" className="rounded-xl bg-red-50 px-3 py-2 text-sm text-danger-600">
          {t('emergency.failed')}
        </p>
      )}
      {state !== 'idle' && phone.data && (
        <a
          href={`tel:${phone.data}`}
          className="flex min-h-12 items-center justify-center gap-2 rounded-2xl bg-white font-semibold ring-1 ring-slate-200"
        >
          <PhoneIcon width={20} height={20} />
          <span dir="ltr">{phone.data}</span>
        </a>
      )}
    </div>
  )
}
