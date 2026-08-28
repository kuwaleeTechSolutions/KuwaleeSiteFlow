import { useState, type FormEvent } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Building2, Plus } from 'lucide-react'
import { api } from '../lib/api'
import { Modal } from '../components/Modal'
import { EmptyState } from '../components/EmptyState'
import { StatusPill } from '../components/StatusPill'
import { Toast } from '../components/Toast'
import { shortDate } from '../lib/format'

type Organization = { id: string; name: string; legal_name?: string; email: string; status: string; created_at: string }

export function SystemOrganizationsPage() {
  const [show, setShow] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [toast, setToast] = useState('')
  const qc = useQueryClient()

  const q = useQuery({
    queryKey: ['system-organizations'],
    queryFn: async () => {
      const res = await api.get('/system/organizations')
      return (res.data.data || []) as Organization[]
    },
  })

  const create = useMutation({
    mutationFn: async (payload: Record<string, string>) => (await api.post('/system/organizations', payload)).data,
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['system-organizations'] })
      setShow(false)
      setError(null)
      setToast('Organization created successfully.')
    },
    onError: (err: unknown) => {
      const anyErr = err as { response?: { data?: { message?: string; errors?: Record<string, string[]> } } }
      const msg = anyErr?.response?.data?.message || 'Failed to create organization.'
      const errs = anyErr?.response?.data?.errors
      setError(errs ? `${msg} — ${Object.entries(errs).map(([f, m]) => `${f}: ${m.join(', ')}`).join(' | ')}` : msg)
    },
  })

  function submit(e: FormEvent<HTMLFormElement>) {
    e.preventDefault()
    const d = Object.fromEntries(new FormData(e.currentTarget)) as Record<string, string>
    create.mutate(d)
  }

  return (
    <>
      <div className="page-head">
        <div>
          <span className="eyebrow">System administration</span>
          <h1>Organizations</h1>
          <p>Provision new contractor organisations. Each organisation gets its own Owner account and default roles.</p>
        </div>
        <button className="button primary" onClick={() => setShow(true)}>
          <Plus size={18} />
          Create organization
        </button>
      </div>

      <section className="panel">
        {q.isLoading ? (
          <div className="loading">
            <span />
            <p>Loading organisations…</p>
          </div>
        ) : (q.data || []).length === 0 ? (
          <EmptyState message="No organizations yet" />
        ) : (
          <div className="table-wrap">
            <table className="data-table">
              <thead>
                <tr>
                  <th>Name</th>
                  <th>Email</th>
                  <th>Status</th>
                  <th>Created</th>
                </tr>
              </thead>
              <tbody>
                {(q.data || []).map((org) => (
                  <tr key={org.id}>
                    <td>
                      <Building2 size={15} style={{ marginRight: 8, verticalAlign: 'middle', color: '#2e8b83' }} />
                      {org.name}
                    </td>
                    <td>{org.email}</td>
                    <td>
                      <StatusPill value={org.status} />
                    </td>
                    <td>{shortDate(org.created_at)}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </section>

      {show && (
        <Modal
          title="Create organization"
          onClose={() => {
            setShow(false)
            setError(null)
          }}
        >
          {error && (
            <div className="alert error" style={{ margin: '0 22px 14px' }}>
              {error}
            </div>
          )}
          <form className="entity-form" onSubmit={submit}>
            <label>
              <span>Organisation name *</span>
              <input name="name" required />
            </label>
            <label>
              <span>Legal name</span>
              <input name="legal_name" />
            </label>
            <label>
              <span>Organisation email *</span>
              <input name="email" type="email" required />
            </label>
            <label>
              <span>Phone</span>
              <input name="phone" />
            </label>
            <label>
              <span>GST number</span>
              <input name="gst_number" />
            </label>
            <label>
              <span>Owner full name *</span>
              <input name="owner_name" required />
            </label>
            <label>
              <span>Owner email *</span>
              <input name="owner_email" type="email" required />
            </label>
            <label>
              <span>Owner password *</span>
              <input name="owner_password" type="password" required minLength={8} />
            </label>
            <label>
              <span>Confirm owner password *</span>
              <input name="owner_password_confirmation" type="password" required minLength={8} />
            </label>
            <div className="form-actions">
              <button
                type="button"
                className="button secondary"
                onClick={() => {
                  setShow(false)
                  setError(null)
                }}
              >
                Cancel
              </button>
              <button className="button primary" disabled={create.isPending}>
                {create.isPending ? 'Creating…' : 'Create organization'}
              </button>
            </div>
          </form>
        </Modal>
      )}

      {toast && <Toast message={toast} onClose={() => setToast('')} />}
    </>
  )
}
