import { createContext, useContext, useMemo, useState, type ReactNode } from 'react'
import { api, ensureCsrfCookie } from './api'
import { demoUser } from './demo'
import type { User } from './types'

type AuthContextType = {
  user: User | null
  loading: boolean
  login: (email: string, password: string, demo?: boolean) => Promise<void>
  logout: () => Promise<void>
  refreshProfile: () => Promise<void>
}

const AuthContext = createContext<AuthContextType | undefined>(undefined)

export function AuthProvider({ children }: { children: ReactNode }) {
  const stored = sessionStorage.getItem('siteflow_user')
  const [user, setUser] = useState<User | null>(stored ? JSON.parse(stored) : null)
  const [loading, setLoading] = useState(false)

  async function refreshProfile() {
    const me = await api.get('/me')
    const profile = me.data.data as User
    sessionStorage.setItem('siteflow_user', JSON.stringify(profile))
    setUser(profile)
  }

  async function login(email: string, password: string, demo = false) {
    setLoading(true)
    try {
      if (demo || (import.meta.env.VITE_ENABLE_DEMO === 'true' && email.endsWith('@siteflow.demo'))) {
        sessionStorage.setItem('siteflow_user', JSON.stringify(demoUser))
        setUser(demoUser)
        return
      }

      // Must happen BEFORE the POST /login below — primes the XSRF-TOKEN
      // cookie the browser needs to send back on the next state-changing
      // request. Without this, Laravel returns 419 CSRF token mismatch.
      await ensureCsrfCookie()
      await api.post('/login', { email, password })

      // The /login response does NOT include permissions, organization, or
      // is_super_admin — fetch the full profile from /me immediately after.
      await refreshProfile()
    } finally {
      setLoading(false)
    }
  }

  async function logout() {
    try {
      await api.post('/logout')
    } catch {
      /* local sign-out still applies even if the server call fails */
    }
    sessionStorage.clear()
    setUser(null)
  }

  const value = useMemo(() => ({ user, loading, login, logout, refreshProfile }), [user, loading])
  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>
}

export const useAuth = () => {
  const c = useContext(AuthContext)
  if (!c) throw new Error('useAuth must be inside AuthProvider')
  return c
}
