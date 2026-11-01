import { createContext, useCallback, useContext, useEffect, useMemo, useState, type ReactNode } from 'react'
import { api, getToken, setToken, type User } from '../lib/api'
import { setLanguage } from '../i18n'

interface AuthState {
  user: User | null
  loading: boolean
  requestOtp: (mobile: string) => Promise<{ resend_after: number }>
  verifyOtp: (mobile: string, code: string) => Promise<void>
  logout: () => Promise<void>
}

const AuthContext = createContext<AuthState | null>(null)

export function AuthProvider({ children }: { children: ReactNode }) {
  const [user, setUser] = useState<User | null>(null)
  const [loading, setLoading] = useState<boolean>(() => getToken() !== null)

  useEffect(() => {
    if (!getToken()) return
    api
      .get<{ data: User }>('/me')
      .then((res) => {
        setUser(res.data.data)
        void setLanguage(res.data.data.locale)
      })
      .catch(() => setToken(null))
      .finally(() => setLoading(false))
  }, [])

  const requestOtp = useCallback(async (mobile: string) => {
    const res = await api.post<{ resend_after: number }>('/auth/otp/request', { mobile })
    return res.data
  }, [])

  const verifyOtp = useCallback(async (mobile: string, code: string) => {
    const res = await api.post<{ token: string; user: User }>('/auth/otp/verify', { mobile, code })
    setToken(res.data.token)
    setUser(res.data.user)
  }, [])

  const logout = useCallback(async () => {
    try {
      await api.post('/auth/logout')
    } finally {
      setToken(null)
      setUser(null)
    }
  }, [])

  const value = useMemo(
    () => ({ user, loading, requestOtp, verifyOtp, logout }),
    [user, loading, requestOtp, verifyOtp, logout],
  )

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>
}

export function useAuth(): AuthState {
  const ctx = useContext(AuthContext)
  if (!ctx) throw new Error('useAuth must be used inside AuthProvider')
  return ctx
}
