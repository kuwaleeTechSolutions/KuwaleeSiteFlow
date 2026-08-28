import { useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { BarChart3, Eye, Plus, Trash2 } from 'lucide-react'
import { api } from '../lib/api'
import { demoRows } from '../lib/demo'
import { Modal } from '../components/Modal'
import { EntityForm } from '../components/EntityForm'
import { EmptyState } from '../components/EmptyState'
import { StatusPill } from '../components/StatusPill'
import { Toast } from '../components/Toast'
import { ConfirmDialog } from '../components/ConfirmDialog'
import { money } from '../lib/format'
import type { EntityRecord, ProjectDashboard } from '../lib/types'

export function ProjectsPage() {
  const demo = sessionStorage.getItem('siteflow_user')?.includes('demo-owner')
  const [show, setShow] = useState(false)
  const [viewing, setViewing] = useState<EntityRecord | null>(null)
  const [deleting, setDeleting] = useState<EntityRecord | null>(null)
  const [dashboard, setDashboard] = useState<{ project: EntityRecord; data: ProjectDashboard } | null>(null)
  const [formError, setFormError] = useState<string | null>(null)
  const [toast, setToast] = useState<{ message: string; tone?: 'info' | 'error' } | null>(null)
  const qc = useQueryClient()

  const q = useQuery({
    queryKey: ['module', 'projects'],
    queryFn: async () => {
      if (demo) return demoRows.projects
      const res = await api.get('/projects')
      return Array.isArray(res.data?.data) ? (res.data.data as EntityRecord[]) : []
    },
  })

  const create = useMutation({
    mutationFn: async (v: Record<string, FormDataEntryValue>) => {
      if (demo) return v
      const cleaned = Object.fromEntries(Object.entries(v).filter(([, val]) => !(typeof val === 'string' && val.trim() === '')))
      return (await api.post('/projects', cleaned)).data
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['module', 'projects'] })
      setShow(false)
      setFormError(null)
      setToast({ message: 'Project created successfully.' })
    },
    onError: (err: unknown) => {
      const anyErr = err as { response?: { data?: { message?: string; errors?: Record<string, string[]> } } }
      const msg = anyErr?.response?.data?.message || 'Failed to create project.'
      const errs = anyErr?.response?.data?.errors
      setFormError(errs ? `${msg} — ${Object.entries(errs).map(([f, m]) => `${f}: ${m.join(', ')}`).join(' | ')}` : msg)
    },
  })

  const remove = useMutation({
    mutationFn: async (row: EntityRecord) => {
      if (demo) return
      return (await api.delete(`/projects/${row.id}`)).data
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['module', 'projects'] })
      setDeleting(null)
      setToast({ message: 'Project deleted.' })
    },
    onError: (err: unknown) => {
      const anyErr = err as { response?: { data?: { message?: string } } }
      setToast({ message: anyErr?.response?.data?.message || 'Delete failed — only Owner/Admin roles can delete projects.', tone: 'error' })
      setDeleting(null)
    },
  })

  async function openDashboard(row: EntityRecord) {
    if (demo) {
      setToast({ message: 'Project dashboard drill-down is illustrated on the main Dashboard page in demo mode.' })
      return
    }
    try {
      const res = await api.get(`/projects/${row.id}/dashboard`)
      setDashboard({ project: row, data: res.data.data })
    } catch (err) {
      const anyErr = err as { response?: { data?: { message?: string } } }
      setToast({ message: anyErr?.response?.data?.message || 'Could not load project dashboard.', tone: 'error' })
    }
  }

  return (
    <>
      <div className="page-head">
        <div>
          <span className="eyebrow">Field operations</span>
          <h1>Projects</h1>
          <p>Manage contracts, delivery scope and project assignments.</p>
        </div>
        <button
          className="button primary"
          onClick={() => {
            setFormError(null)
            setShow(true)
          }}
        >
          <Plus size={18} />
          Create project
        </button>
      </div>

      <section className="panel">
        {q.isLoading ? (
          <div className="loading">
            <span />
            <p>Loading projects…</p>
          </div>
        ) : (q.data || []).length === 0 ? (
          <EmptyState />
        ) : (
          <div className="table-wrap">
            <table className="data-table">
              <thead>
                <tr>
                  <th>Code</th>
                  <th>Project</th>
                  <th>Client</th>
                  <th>Contract value</th>
                  <th>Status</th>
                  <th className="actions-col">Actions</th>
                </tr>
              </thead>
              <tbody>
                {(q.data || []).map((row) => (
                  <tr key={String(row.id)}>
                    <td>{String(row.project_code)}</td>
                    <td>{String(row.project_name)}</td>
                    <td>{String(row.client_name || '—')}</td>
                    <td>{money(row.contract_value)}</td>
                    <td><StatusPill value={String(row.status || '')} /></td>
                    <td className="row-actions">
                      <button className="icon-button" title="Project dashboard" onClick={() => openDashboard(row)}>
                        <BarChart3 size={17} />
                      </button>
                      <button className="icon-button" title="View details" onClick={() => setViewing(row)}>
                        <Eye size={17} />
                      </button>
                      <button className="icon-button" title="Delete" onClick={() => setDeleting(row)}>
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
          title="Create project"
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
          <EntityForm
            fields={[
              { name: 'project_code', label: 'Project code', required: true },
              { name: 'project_name', label: 'Project name', required: true },
              { name: 'client_name', label: 'Client name' },
              { name: 'contract_number', label: 'Contract number' },
              { name: 'contract_value', label: 'Contract value', type: 'number', step: '0.01', required: true },
              { name: 'start_date', label: 'Start date', type: 'date' },
              { name: 'expected_end_date', label: 'Expected end date', type: 'date' },
              { name: 'status', label: 'Status', type: 'select', options: ['planning', 'active', 'on_hold', 'completed', 'cancelled'] },
              { name: 'description', label: 'Description', type: 'textarea' },
            ]}
            onCancel={() => {
              setShow(false)
              setFormError(null)
            }}
            onSubmit={(v) => create.mutate(v)}
          />
        </Modal>
      )}

      {viewing && (
        <Modal title="Project details" onClose={() => setViewing(null)}>
          <div style={{ padding: 22 }}>
            <table className="data-table" style={{ minWidth: 'auto' }}>
              <tbody>
                {Object.entries(viewing).map(([key, value]) => (
                  <tr key={key}>
                    <td style={{ fontWeight: 600, width: 180 }}>{key.replaceAll('_', ' ')}</td>
                    <td>{typeof value === 'object' ? JSON.stringify(value) : String(value ?? '—')}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </Modal>
      )}

      {dashboard && (
        <Modal title={`${dashboard.project.project_name} — Dashboard`} onClose={() => setDashboard(null)}>
          <div style={{ padding: 22 }}>
            <table className="data-table" style={{ minWidth: 'auto' }}>
              <tbody>
                <tr><td style={{ fontWeight: 600, width: 200 }}>Contract value</td><td>{money(dashboard.data.project.contract_value)}</td></tr>
                {Object.entries(dashboard.data.delivery).map(([k, v]) => (
                  <tr key={k}><td style={{ fontWeight: 600 }}>{k.replaceAll('_', ' ')}</td><td>{v}</td></tr>
                ))}
                {Object.entries(dashboard.data.financial).map(([k, v]) => (
                  <tr key={k}><td style={{ fontWeight: 600 }}>{k.replaceAll('_', ' ')}</td><td>{k.includes('amount') || k.includes('payable') ? money(v) : v}</td></tr>
                ))}
                {Object.entries(dashboard.data.risk).map(([k, v]) => (
                  <tr key={k}><td style={{ fontWeight: 600 }}>{k.replaceAll('_', ' ')}</td><td>{v}</td></tr>
                ))}
              </tbody>
            </table>
          </div>
        </Modal>
      )}

      {deleting && (
        <ConfirmDialog
          title="Confirm deletion"
          message={`Delete project "${deleting.project_name}"? This cascades to its sites and related records — only Owner/Admin roles can do this.`}
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
