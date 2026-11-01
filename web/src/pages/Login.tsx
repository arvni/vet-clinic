import { useEffect, useState, type FormEvent } from 'react'
import { useTranslation } from 'react-i18next'
import { useNavigate } from 'react-router-dom'
import { isAxiosError } from 'axios'
import { useAuth } from '../auth/AuthContext'
import { LanguageSwitch } from '../components/LanguageSwitch'
import { localizeDigits, normalizeIranMobile, toLatinDigits } from '../lib/digits'

type Step = 'mobile' | 'code'

export function Login() {
  const { t, i18n } = useTranslation()
  const { requestOtp, verifyOtp } = useAuth()
  const navigate = useNavigate()

  const [step, setStep] = useState<Step>('mobile')
  const [mobileInput, setMobileInput] = useState('')
  const [mobile, setMobile] = useState('')
  const [code, setCode] = useState('')
  const [error, setError] = useState<string | null>(null)
  const [busy, setBusy] = useState(false)
  const [resendIn, setResendIn] = useState(0)

  useEffect(() => {
    if (resendIn <= 0) return
    const id = setTimeout(() => setResendIn((s) => s - 1), 1000)
    return () => clearTimeout(id)
  }, [resendIn])

  const errorMessage = (err: unknown): string => {
    if (isAxiosError(err)) {
      if (err.response?.status === 429) return t('errors.tooManyRequests')
      const msg = err.response?.data?.message
      if (typeof msg === 'string') return msg
    }
    return t('errors.generic')
  }

  const sendCode = async (e?: FormEvent) => {
    e?.preventDefault()
    const normalized = normalizeIranMobile(mobileInput)
    if (!normalized) {
      setError(t('auth.invalidMobile'))
      return
    }
    setBusy(true)
    setError(null)
    try {
      const res = await requestOtp(normalized)
      setMobile(normalized)
      setStep('code')
      setResendIn(res.resend_after)
    } catch (err) {
      setError(errorMessage(err))
    } finally {
      setBusy(false)
    }
  }

  const verify = async (e: FormEvent) => {
    e.preventDefault()
    setBusy(true)
    setError(null)
    try {
      await verifyOtp(mobile, toLatinDigits(code))
      navigate('/', { replace: true })
    } catch (err) {
      setError(errorMessage(err))
    } finally {
      setBusy(false)
    }
  }

  const displayMobile = localizeDigits(mobile.replace('+98', '0'), i18n.language)

  return (
    <main className="flex min-h-screen items-center justify-center p-4">
      <div className="w-full max-w-sm rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
        <div className="mb-6 flex items-center justify-between">
          <h1 className="text-lg font-semibold text-brand-700">{t('app.name')}</h1>
          <LanguageSwitch />
        </div>
        <h2 className="mb-4 text-base font-medium">{t('auth.title')}</h2>

        {step === 'mobile' ? (
          <form onSubmit={sendCode} className="space-y-4">
            <label className="block">
              <span className="mb-1 block text-sm text-slate-600">{t('auth.mobile')}</span>
              <input
                type="tel"
                inputMode="numeric"
                autoComplete="tel"
                dir="ltr"
                value={mobileInput}
                onChange={(e) => setMobileInput(e.target.value)}
                placeholder={t('auth.mobilePlaceholder')}
                className="w-full rounded-lg border border-slate-300 px-3 py-2 text-start focus:border-brand-500 focus:outline-none"
                required
              />
            </label>
            <button
              type="submit"
              disabled={busy}
              className="w-full rounded-lg bg-brand-600 py-2.5 font-medium text-white hover:bg-brand-700 disabled:opacity-60"
            >
              {t('auth.sendCode')}
            </button>
          </form>
        ) : (
          <form onSubmit={verify} className="space-y-4">
            <p className="text-sm text-slate-600">{t('auth.codeSentTo', { mobile: displayMobile })}</p>
            <label className="block">
              <span className="mb-1 block text-sm text-slate-600">{t('auth.code')}</span>
              <input
                type="text"
                inputMode="numeric"
                autoComplete="one-time-code"
                dir="ltr"
                maxLength={5}
                value={code}
                onChange={(e) => setCode(e.target.value)}
                className="w-full rounded-lg border border-slate-300 px-3 py-2 text-center text-lg tracking-[0.5em] focus:border-brand-500 focus:outline-none"
                required
              />
            </label>
            <button
              type="submit"
              disabled={busy}
              className="w-full rounded-lg bg-brand-600 py-2.5 font-medium text-white hover:bg-brand-700 disabled:opacity-60"
            >
              {t('auth.verify')}
            </button>
            <div className="flex justify-between text-sm">
              <button type="button" className="text-slate-600 underline" onClick={() => setStep('mobile')}>
                {t('auth.changeMobile')}
              </button>
              {resendIn > 0 ? (
                <span className="text-slate-500">
                  {t('auth.resendIn', { seconds: localizeDigits(resendIn, i18n.language) })}
                </span>
              ) : (
                <button type="button" className="text-brand-600 underline" onClick={() => sendCode()}>
                  {t('auth.resend')}
                </button>
              )}
            </div>
          </form>
        )}

        {error && (
          <p role="alert" className="mt-4 rounded-lg bg-red-50 px-3 py-2 text-sm text-danger-600">
            {error}
          </p>
        )}
      </div>
    </main>
  )
}
