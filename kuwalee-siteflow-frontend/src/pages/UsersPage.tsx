import { useState, type FormEvent } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { KeyRound, Plus, Trash2, UserCircle2 } from 'lucide-react'
import { api } from '../lib/api'
import { demoRows } from '../lib/demo'
import { Modal } from '../components/Modal'
import { EmptyState } from '../components/EmptyState'
import { StatusPill } from '../components/StatusPill'
import { Toast } from '../components/Toast'
import { ConfirmDialog } from '../components/ConfirmDialog'
import type { EntityRecord, Role, User } from '../lib/types'

export function UsersPage() {
  const demo = sessionStorage.getItem('siteflow_user')?.includes('demo-owner')
  const [show, setShow] = useState(false)
  const [deleting, setDeleting] = useState<EntityRecord | null>(null)
  const [reassigning, setReassigning] = useState<User | null>(null)
  const [formError, setFormError] = useState<string | null>(null)
  const [toast, setToast] = useState<{ message: string; tone?: 'info' | 'error' } | null>(null)

  const [name, setName] = useState('')
  const [email, setEmail] = useState('')
  const [password, setPassword] = useState('')
  const [passwordConfirmation, setPasswordConfirmation] = useState('')
  const [selectedRoleIds, setSelectedRoleIds] = useState<Set<string>>(new Set())

  const qc = useQueryClient()

  const usersQuery = useQuery({
    queryKey: ['module', 'users'],
    queryFn: async () => {
      if (demo) return demoRows.users as unknown as User[]
      return (await api.get('/users')).data.data as User[]
    },
  })

  const rolesQuery = useQuery({
    queryKey: ['roles-for-users'],
    queryFn: async () => {
      if (demo) return demoRows.roles as unknown as Role[]
      return (await api.get('/roles')).data.data as Role[]
    },
  })

  const create = useMutation({
    mutationFn: async () => {
      if (demo) return
      return (
        await api.post('/users', {
          name,
          email,
          password,
          password_confirmation: passwordConfirmation,
          role_ids: Array.from(selectedRoleIds),
        })
      ).data
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['module', 'users'] })
      setShow(false)
      setFormError(null)
      setName('')
      setEmail('')
      setPassword('')
      setPasswordConfirmation('')
      setSelectedRoleIds(new Set())
      setToast({ message: 'User created successfully.' })
    },
    onError: (err: unknown) => {
      const anyErr = err as { response?: { data?: { message?: string; errors?: Record<string, string[]> } } }
      const msg = anyErr?.response?.data?.message || 'Failed to create user.'
      const errs = anyErr?.response?.data?.errors
      setFormError(errs ? `${msg} — ${Object.entries(errs).map(([f, m]) => `${f}: ${m.join(', ')}`).join(' | ')}` : msg)
    },
  })

  const remove = useMutation({
    mutationFn: async (user: EntityRecord) => (demo ? undefined : (await api.delete(`/users/${user.id}`)).data),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['module', 'users'] })
      setDeleting(null)
      setToast({ message: 'User deleted.' })
    },
    onError: (err: unknown) => {
      const anyErr = err as { response?: { data?: { message?: string } } }
      setToast({ message: anyErr?.response?.data?.message || 'Failed to delete user.', tone: 'error' })
      setDeleting(null)
    },
  })

  function toggleRole(id: string) {
    setSelectedRoleIds((prev) => {
      const next = new Set(prev)
      if (next.has(id)) next.delete(id)
      else next.add(id)
      return next
    })
  }

  function submit(e: FormEvent) {
    e.preventDefault()
    create.mutate()
  }

  return (
    <>
      <div className="page-head">
        <div>
          <span className="eyebrow">Administration</span>
          <h1>Users</h1>
          <p>Invite organisation members and assign their roles.</p>
        </div>
        <button
          className="button primary"
          onClick={() => {
            setFormError(null)
            setShow(true)
          }}
        >
          <Plus size={18} />
          Add user
        </button>
      </div>

      <section className="panel">
        {usersQuery.isLoading ? (
          <div className="loading">
            <span />
            <p>Loading users…</p>
          </div>
        ) : (usersQuery.data || []).length === 0 ? (
          <EmptyState />
        ) : (
          <div className="table-wrap">
            <table className="data-table">
              <thead>
                <tr>
                  <th>Name</th>
                  <th>Email</th>
                  <th>Roles</th>
                  <th>Status</th>
                  <th className="actions-col">Actions</th>
                </tr>
              </thead>
              <tbody>
                {(usersQuery.data || []).map((u) => (
                  <tr key={u.id}>
                    <td>
                      <UserCircle2 size={15} style={{ marginRight: 8, verticalAlign: 'middle', color: '#2e8b83' }} />
                      {u.name}
                    </td>
                    <td>{u.email}</td>
                    <td>{u.roles?.map((r) => r.name).join(', ') || '—'}</td>
                    <td><StatusPill value={u.status || 'active'} /></td>
                    <td className="row-actions">
                      <button className="icon-button" title="Change roles" onClick={() => setReassigning(u)}>
                        <KeyRound size={17} />
                      </button>
                      <button className="icon-button" title="Delete" onClick={() => setDeleting(u as unknown as EntityRecord)}>
                        <Trash2 size={17} />
                      </button>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </section>

      {show && (
        <Modal
          title="Add user"
          onClose={() => {
            setShow(false)
            setFormError(null)
          }}
        >
          {formError && (
            <div className="alert error" style={{ margin: '0 22px 14px' }}>
              {formError}
            </div>
          )}
          <form className="entity-form" onSubmit={submit}>
            <label className="span-2">
              <span>Full name *</span>
              <input value={name} onChange={(e) => setName(e.target.value)} required />
            </label>
            <label className="span-2">
              <span>Email address *</span>
              <input type="email" value={email} onChange={(e) => setEmail(e.target.value)} required />
            </label>
            <label>
              <span>Password *</span>
              <input type="password" value={password} onChange={(e) => setPassword(e.target.value)} minLength={8} required />
            </label>
            <label>
              <span>Confirm password *</span>
              <input type="password" value={passwordConfirmation} onChange={(e) => setPasswordConfirmation(e.target.value)} minLength={8} required />
            </label>

            <div className="span-2">
              <h3 style={{ fontSize: 13, color: '#173b57', margin: '6px 0 10px' }}>Roles *</h3>
              <div className="role-check-list">
                {(rolesQuery.data || []).map((r) => (
                  <label key={r.id} className="permission-check">
                    <input type="checkbox" checked={selectedRoleIds.has(r.id)} onChange={() => toggleRole(r.id)} />
                    <span>{r.name}</span>
                  </label>
                ))}
              </div>
            </div>

            <div className="form-actions">
              <button
                type="button"
                className="button secondary"
                onClick={() => {
                  setShow(false)
                  setFormError(null)
                }}
              >
                Cancel
              </button>
              <button className="button primary" disabled={create.isPending || selectedRoleIds.size === 0}>
                {create.isPending ? 'Saving…' : 'Create user'}
              </button>
            </div>
          </form>
        </Modal>
      )}

      {reassigning && (
        <ReassignRolesModal
          user={reassigning}
          roles={rolesQuery.data || []}
          demo={!!demo}
          onClose={() => setReassigning(null)}
          onDone={() => {
            qc.invalidateQueries({ queryKey: ['module', 'users'] })
            setReassigning(null)
            setToast({ message: 'Roles updated successfully.' })
          }}
          onError={(m) => setToast({ message: m, tone: 'error' })}
        />
      )}

      {deleting && (
        <ConfirmDialog
          title="Confirm deletion"
          message={`Delete user "${deleting.name}"? This cannot be undone.`}
          confirmLabel="Delete"
          danger
          busy={remove.isPending}
          onConfirm={() => remove.mutate(deleting)}
          onCancel={() => setDeleting(null)}
        />
      )}

      {toast && <Toast message={toast.message} tone={toast.tone} onClose={() => setToast(null)} />}
    </>
  )
}

function ReassignRolesModal({
  user,
  roles,
  demo,
  onClose,
  onDone,
  onError,
}: {
  user: User
  roles: Role[]
  demo: boolean
  onClose: () => void
  onDone: () => void
  onError: (message: string) => void
}) {
  const [selected, setSelected] = useState<Set<string>>(new Set(user.roles?.map((r) => r.id) || []))
  const [busy, setBusy] = useState(false)

  async function save() {
    setBusy(true)
    try {
      if (!demo) {
        await api.post(`/users/${user.id}/roles`, { role_ids: Array.from(selected) })
      }
      onDone()
    } catch (err) {
      const anyErr = err as { response?: { data?: { message?: string } } }
      onError(anyErr?.response?.data?.message || 'Failed to update roles.')
    } finally {
      setBusy(false)
    }
  }

  function toggle(id: string) {
    setSelected((prev) => {
      const next = new Set(prev)
      if (next.has(id)) next.delete(id)
      else next.add(id)
      return next
    })
  }

  return (
    <Modal title={`Change roles — ${user.name}`} onClose={onClose}>
      <div style={{ padding: 22 }}>
        <div className="role-check-list">
          {roles.map((r) => (
            <label key={r.id} className="permission-check">
              <input type="checkbox" checked={selected.has(r.id)} onChange={() => toggle(r.id)} />
              <span>{r.name}</span>
            </label>
          ))}
        </div>
        <div className="form-actions" style={{ padding: '18px 0 0' }}>
          <button className="button secondary" onClick={onClose}>
            Cancel
          </button>
          <button className="button primary" disabled={busy || selected.size === 0} onClick={save}>
            {busy ? 'Saving…' : 'Save roles'}
          </button>
        </div>
      </div>
    </Modal>
  )
}
