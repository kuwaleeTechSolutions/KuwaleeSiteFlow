import { useState, type FormEvent } from 'react'
import { Navigate } from 'react-router-dom'
import { Building2, LockKeyhole, ShieldCheck } from 'lucide-react'
import { useAuth } from '../lib/auth'

export function LoginPage() {
  const { user, login, loading } = useAuth()
  const [error, setError] = useState('')

  if (user) return <Navigate to="/" replace />

  async function submit(e: FormEvent<HTMLFormElement>) {
    e.preventDefault()
    setError('')
    const d = new FormData(e.currentTarget)
    try {
      await login(String(d.get('email')), String(d.get('password')))
    } catch (er) {
      setError(er instanceof Error ? er.message : 'Login failed')
    }
  }

  return (
    <main className="login-page">
      <section className="login-hero">
        <div className="hero-content">
          <div className="brand light">
            <div className="brand-mark">K</div>
            <div>
              <strong>Kuwalee</strong>
              <span>SiteFlow</span>
            </div>
          </div>
          <h1>One operating system for every construction site.</h1>
          <p>Field progress, labour, materials, equipment, measurements, billing, documents and compliance in one secure workspace.</p>
          <div className="hero-points">
            <span>
              <Building2 />
              Multi-project oversight
            </span>
            <span>
              <ShieldCheck />
              Role-based security
            </span>
            <span>
              <LockKeyhole />
              Private document vault
            </span>
          </div>
        </div>
      </section>

      <section className="login-panel">
        <form onSubmit={submit}>
          <div>
            <span className="eyebrow">Welcome back</span>
            <h2>Sign in to SiteFlow</h2>
            <p>Use your organisation account to continue.</p>
          </div>
          {error && <div className="alert error">{error}</div>}
          <label>
            Email address
            <input name="email" type="email" defaultValue="owner@siteflow.demo" required autoComplete="email" />
          </label>
          <label>
            Password
            <input name="password" type="password" defaultValue="demo1234" required autoComplete="current-password" />
          </label>
          <button className="button primary wide" disabled={loading}>
            {loading ? 'Signing in…' : 'Sign in'}
          </button>
          <button className="button demo wide" type="button" onClick={() => login('owner@siteflow.demo', 'demo1234', true)}>
            Open demo workspace
          </button>
          <small>Demo mode uses sample records and does not require the API.</small>
        </form>
      </section>
    </main>
  )
}
