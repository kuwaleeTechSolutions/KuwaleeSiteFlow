import { useState, type FormEvent } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Plus, ShieldCheck, Trash2 } from 'lucide-react'
import { api } from '../lib/api'
import { demoRows } from '../lib/demo'
import { permissionCatalogue } from '../lib/permissionCatalogue'
import { Modal } from '../components/Modal'
import { EmptyState } from '../components/EmptyState'
import { Toast } from '../components/Toast'
import { ConfirmDialog } from '../components/ConfirmDialog'
import type { EntityRecord, Role } from '../lib/types'

export function RolesPage() {
  const demo = sessionStorage.getItem('siteflow_user')?.includes('demo-owner')
  const [show, setShow] = useState(false)
  const [deleting, setDeleting] = useState<EntityRecord | null>(null)
  const [formError, setFormError] = useState<string | null>(null)
  const [toast, setToast] = useState<{ message: string; tone?: 'info' | 'error' } | null>(null)

  const [name, setName] = useState('')
  const [slug, setSlug] = useState('')
  const [description, setDescription] = useState('')
  const [orgWide, setOrgWide] = useState(false)
  const [selectedPermissions, setSelectedPermissions] = useState<Set<string>>(new Set())

  const qc = useQueryClient()

  const q = useQuery({
    queryKey: ['module', 'roles'],
    queryFn: async () => {
      if (demo) return demoRows.roles as unknown as Role[]
      return (await api.get('/roles')).data.data as Role[]
    },
  })

  const create = useMutation({
    mutationFn: async () => {
      if (demo) return
      return (
        await api.post('/roles', {
          name,
          slug,
          description: description || undefined,
          org_wide_visibility: orgWide,
          permissions: Array.from(selectedPermissions),
        })
      ).data
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['module', 'roles'] })
      setShow(false)
      setFormError(null)
      setName('')
      setSlug('')
      setDescription('')
      setOrgWide(false)
      setSelectedPermissions(new Set())
      setToast({ message: 'Role created successfully.' })
    },
    onError: (err: unknown) => {
      const anyErr = err as { response?: { data?: { message?: string; errors?: Record<string, string[]> } } }
      const msg = anyErr?.response?.data?.message || 'Failed to create role.'
      const errs = anyErr?.response?.data?.errors
      setFormError(errs ? `${msg} — ${Object.entries(errs).map(([f, m]) => `${f}: ${m.join(', ')}`).join(' | ')}` : msg)
    },
  })

  const remove = useMutation({
    mutationFn: async (role: EntityRecord) => (demo ? undefined : (await api.delete(`/roles/${role.id}`)).data),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['module', 'roles'] })
      setDeleting(null)
      setToast({ message: 'Role deleted.' })
    },
    onError: (err: unknown) => {
      const anyErr = err as { response?: { data?: { message?: string } } }
      setToast({ message: anyErr?.response?.data?.message || 'Cannot delete this role — system role templates cannot be removed.', tone: 'error' })
      setDeleting(null)
    },
  })

  function togglePermission(p: string) {
    setSelectedPermissions((prev) => {
      const next = new Set(prev)
      if (next.has(p)) next.delete(p)
      else next.add(p)
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
          <h1>Roles</h1>
          <p>Roles are bundles of permissions. The 8 system role templates cannot be edited or deleted — create a custom role instead.</p>
        </div>
        <button
          className="button primary"
          onClick={() => {
            setFormError(null)
            setShow(true)
          }}
        >
          <Plus size={18} />
          Create custom role
        </button>
      </div>

      <section className="panel">
        {q.isLoading ? (
          <div className="loading">
            <span />
            <p>Loading roles…</p>
          </div>
        ) : (q.data || []).length === 0 ? (
          <EmptyState />
        ) : (
          <div className="table-wrap">
            <table className="data-table">
              <thead>
                <tr>
                  <th>Role</th>
                  <th>Type</th>
                  <th>Org-wide visibility</th>
                  <th>Permissions</th>
                  <th className="actions-col">Actions</th>
                </tr>
              </thead>
              <tbody>
                {(q.data || []).map((role) => (
                  <tr key={role.id}>
                    <td>
                      <ShieldCheck size={14} style={{ marginRight: 8, verticalAlign: 'middle', color: '#2e8b83' }} />
                      {role.name}
                    </td>
                    <td>{role.is_system ? 'System' : 'Custom'}</td>
                    <td>{role.org_wide_visibility ? 'Yes' : 'No'}</td>
                    <td>{role.permissions?.length ?? '—'}</td>
                    <td className="row-actions">
                      {!role.is_system && (
                        <button className="icon-button" title="Delete" onClick={() => setDeleting(role as unknown as EntityRecord)}>
                          <Trash2 size={17} />
                        </button>
                      )}
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
          title="Create custom role"
          wide
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
            <label>
              <span>Role name *</span>
              <input value={name} onChange={(e) => setName(e.target.value)} required />
            </label>
            <label>
              <span>Slug *</span>
              <input value={slug} onChange={(e) => setSlug(e.target.value)} placeholder="site-photographer" required />
            </label>
            <label className="span-2">
              <span>Description</span>
              <textarea value={description} onChange={(e) => setDescription(e.target.value)} />
            </label>
            <label style={{ flexDirection: 'row', alignItems: 'center', gap: 10 }}>
              <input type="checkbox" checked={orgWide} onChange={(e) => setOrgWide(e.target.checked)} style={{ width: 'auto' }} />
              <span style={{ marginBottom: 0 }}>Org-wide visibility (bypasses project/site assignment checks)</span>
            </label>

            <div className="span-2">
              <h3 style={{ fontSize: 13, color: '#173b57', margin: '10px 0' }}>Permissions</h3>
              <div className="permission-grid">
                {Object.entries(permissionCatalogue).map(([group, perms]) => (
                  <div key={group} className="permission-group">
                    <strong>{group.replaceAll('_', ' ')}</strong>
                    {perms.map((p) => (
                      <label key={p} className="permission-check">
                        <input type="checkbox" checked={selectedPermissions.has(p)} onChange={() => togglePermission(p)} />
                        <span>{p}</span>
                      </label>
                    ))}
                  </div>
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
              <button className="button primary" disabled={create.isPending || selectedPermissions.size === 0}>
                {create.isPending ? 'Saving…' : 'Create role'}
              </button>
            </div>
          </form>
        </Modal>
      )}

      {deleting && (
        <ConfirmDialog
          title="Confirm deletion"
          message={`Delete role "${deleting.name}"? Users currently holding this role will lose the permissions it grants.`}
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
