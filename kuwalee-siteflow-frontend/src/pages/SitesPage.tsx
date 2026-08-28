import { useEffect, useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Plus } from 'lucide-react'
import { api } from '../lib/api'
import { demoRows, demoSites } from '../lib/demo'
import { Modal } from '../components/Modal'
import { EntityForm } from '../components/EntityForm'
import { EmptyState } from '../components/EmptyState'
import { StatusPill } from '../components/StatusPill'
import { Toast } from '../components/Toast'
import type { EntityRecord } from '../lib/types'

export function SitesPage() {
  const demo = sessionStorage.getItem('siteflow_user')?.includes('demo-owner')
  const [projectId, setProjectId] = useState('')
  const [show, setShow] = useState(false)
  const [formError, setFormError] = useState<string | null>(null)
  const [toast, setToast] = useState('')
  const qc = useQueryClient()

  const projectsQuery = useQuery({
    queryKey: ['projects-for-sites'],
    queryFn: async () => {
      if (demo) return demoRows.projects as EntityRecord[]
      return (await api.get('/projects')).data.data as EntityRecord[]
    },
  })

  useEffect(() => {
    if (!projectId && projectsQuery.data && projectsQuery.data.length > 0) setProjectId(String(projectsQuery.data[0].id))
  }, [projectsQuery.data, projectId])

  const sitesQuery = useQuery({
    queryKey: ['sites', projectId],
    queryFn: async () => {
      if (demo) return demoSites
      return (await api.get(`/projects/${projectId}/sites`)).data.data as EntityRecord[]
    },
    enabled: !!projectId,
  })

  const create = useMutation({
    mutationFn: async (v: Record<string, FormDataEntryValue>) => {
      if (demo) return v
      const targetProjectId = String(v.project_id)
      const body: Record<string, FormDataEntryValue> = {}
      Object.entries(v).forEach(([k, val]) => {
        if (k !== 'project_id' && !(typeof val === 'string' && val.trim() === '')) body[k] = val
      })
      return (await api.post(`/projects/${targetProjectId}/sites`, body)).data
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['sites'] })
      setShow(false)
      setFormError(null)
      setToast('Site created successfully.')
    },
    onError: (err: unknown) => {
      const anyErr = err as { response?: { data?: { message?: string; errors?: Record<string, string[]> } } }
      const msg = anyErr?.response?.data?.message || 'Failed to create site.'
      const errs = anyErr?.response?.data?.errors
      setFormError(errs ? `${msg} — ${Object.entries(errs).map(([f, m]) => `${f}: ${m.join(', ')}`).join(' | ')}` : msg)
    },
  })

  return (
    <>
      <div className="page-head">
        <div>
          <span className="eyebrow">Field operations</span>
          <h1>Sites</h1>
          <p>Sites belong to a project. Select a project to view its sites.</p>
        </div>
        <button
          className="button primary"
          onClick={() => {
            setFormError(null)
            setShow(true)
          }}
        >
          <Plus size={18} />
          Create site
        </button>
      </div>

      <section className="panel">
        <div className="toolbar">
          <select value={projectId} onChange={(e) => setProjectId(e.target.value)} className="inline-select">
            <option value="">Select a project…</option>
            {(projectsQuery.data || []).map((p) => (
              <option key={String(p.id)} value={String(p.id)}>
                {String(p.project_code)} — {String(p.project_name)}
              </option>
            ))}
          </select>
        </div>

        {!projectId ? (
          <EmptyState message="Select a project above to view its sites" />
        ) : sitesQuery.isLoading ? (
          <div className="loading">
            <span />
            <p>Loading sites…</p>
          </div>
        ) : (sitesQuery.data || []).length === 0 ? (
          <EmptyState message="No sites yet for this project" />
        ) : (
          <div className="table-wrap">
            <table className="data-table">
              <thead>
                <tr>
                  <th>Site</th>
                  <th>Location</th>
                  <th>Status</th>
                </tr>
              </thead>
              <tbody>
                {(sitesQuery.data || []).map((s) => (
                  <tr key={String(s.id)}>
                    <td>{String(s.site_name)}</td>
                    <td>{String(s.location || '—')}</td>
                    <td>
                      <StatusPill value={String(s.status || '')} />
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
          title="Create site"
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
              { name: 'project_id', label: 'Project', type: 'reference', referenceEndpoint: '/projects', required: true },
              { name: 'site_name', label: 'Site name', required: true },
              { name: 'location', label: 'Location' },
              { name: 'status', label: 'Status', type: 'select', options: ['active', 'inactive', 'completed'] },
            ]}
            onCancel={() => {
              setShow(false)
              setFormError(null)
            }}
            onSubmit={(v) => create.mutate(v)}
          />
        </Modal>
      )}

      {toast && <Toast message={toast} onClose={() => setToast('')} />}
    </>
  )
}
