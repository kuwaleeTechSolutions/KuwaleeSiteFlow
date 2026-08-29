import { useMemo, useState } from 'react'
import { useParams } from 'react-router-dom'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import * as Icons from 'lucide-react'
import { Download, Plus, Search, Trash2 } from 'lucide-react'
import { api, downloadFile } from '../lib/api'
import { demoRows } from '../lib/demo'
import { moduleMap, type RowAction } from '../features/modules'
import { EmptyState } from '../components/EmptyState'
import { Modal } from '../components/Modal'
import { EntityForm } from '../components/EntityForm'
import { StatusPill } from '../components/StatusPill'
import { Toast } from '../components/Toast'
import { ConfirmDialog } from '../components/ConfirmDialog'
import { RemarksDialog } from '../components/RemarksDialog'
import { money, shortDate, titleCase } from '../lib/format'
import type { ApiEnvelope, EntityRecord } from '../lib/types'

function pretty(v: unknown, format?: string) {
  if (v == null || v === '') return '—'
  if (format === 'money') return money(v)
  if (format === 'date') return shortDate(v)
  return titleCase(v)
}

export function ModulePage() {
  const { moduleKey = 'projects' } = useParams()
  const cfg = moduleMap[moduleKey]
  const demo = sessionStorage.getItem('siteflow_user')?.includes('demo-owner')
  const [search, setSearch] = useState('')
  const [show, setShow] = useState(false)
  const [viewing, setViewing] = useState<EntityRecord | null>(null)
  const [deleting, setDeleting] = useState<EntityRecord | null>(null)
  const [remarksAction, setRemarksAction] = useState<{ action: RowAction; row: EntityRecord } | null>(null)
  const [toast, setToast] = useState<{ message: string; tone?: 'info' | 'error' } | null>(null)
  const [formError, setFormError] = useState<string | null>(null)
  const qc = useQueryClient()

  const q = useQuery({
    queryKey: ['module', moduleKey],
    queryFn: async () => {
      if (demo) return demoRows[moduleKey] || []
      const res = await api.get<ApiEnvelope<EntityRecord[]> | { data: EntityRecord[] }>(cfg.endpoint)
      const payload = res.data as ApiEnvelope<EntityRecord[]>
      return Array.isArray(payload.data) ? payload.data : []
    },
    enabled: !!cfg,
  })

  const create = useMutation({
    mutationFn: async (v: Record<string, FormDataEntryValue>) => {
      if (demo) return v
      // An unselected <select> submits "" (empty string), which fails
      // backend enum validation for fields like `status`. Stripping empty
      // strings lets the backend's own column default apply instead.
      const cleaned = Object.fromEntries(Object.entries(v).filter(([, val]) => !(typeof val === 'string' && val.trim() === '')))
      const hasFile = Object.values(cleaned).some((x) => x instanceof File)
      let payload: Record<string, FormDataEntryValue> | FormData = cleaned
      if (hasFile) {
        const fd = new FormData()
        Object.entries(cleaned).forEach(([k, val]) => fd.append(k, val))
        payload = fd
      }
      return (await api.post(cfg.endpoint, payload)).data
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['module', moduleKey] })
      setShow(false)
      setFormError(null)
      setToast({ message: 'Record saved successfully.' })
    },
    onError: (err: unknown) => {
      const anyErr = err as { message?: string; response?: { data?: { message?: string; errors?: Record<string, string[]> } } }
      const serverMessage = anyErr?.response?.data?.message || anyErr?.message || 'Failed to save record.'
      const fieldErrors = anyErr?.response?.data?.errors
      const detail = fieldErrors ? Object.entries(fieldErrors).map(([field, msgs]) => `${field}: ${msgs.join(', ')}`).join(' | ') : ''
      setFormError(detail ? `${serverMessage} — ${detail}` : serverMessage)
    },
  })

  const remove = useMutation({
    mutationFn: async (row: EntityRecord) => {
      if (demo) return
      return (await api.delete(`${cfg.endpoint}/${row.id || row.uuid}`)).data
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['module', moduleKey] })
      setDeleting(null)
      setToast({ message: 'Record deleted.' })
    },
    onError: (err: unknown) => {
      const anyErr = err as { response?: { data?: { message?: string } } }
      setToast({ message: anyErr?.response?.data?.message || 'Failed to delete — you may not have permission.', tone: 'error' })
      setDeleting(null)
    },
  })

  const runAction = useMutation({
    mutationFn: async ({ action, row, remarks }: { action: RowAction; row: EntityRecord; remarks?: string }) => {
      if (demo) return
      if (action.kind === 'download') {
        await downloadFile(action.path(row), action.downloadName?.(row) || 'download')
        return
      }
      const body = remarks !== undefined ? { review_remarks: remarks } : {}
      // All non-download row actions are POST requests to a state-change
      // endpoint (submit/approve/reject/review). Kept as a single explicit
      // branch rather than dynamically indexing api[action.method], which
      // breaks TypeScript's strict overload resolution for axios methods.
      return (await api.post(action.path(row), body)).data
    },
    onSuccess: (_data, variables) => {
      if (variables.action.kind !== 'download') {
        qc.invalidateQueries({ queryKey: ['module', moduleKey] })
      }
      setToast({ message: variables.action.successMessage || 'Action completed.' })
      setRemarksAction(null)
    },
    onError: (err: unknown) => {
      const anyErr = err as { response?: { data?: { message?: string } } }
      setToast({ message: anyErr?.response?.data?.message || 'Action failed.', tone: 'error' })
      setRemarksAction(null)
    },
  })

  const rows = useMemo(
    () => (q.data || []).filter((r) => JSON.stringify(r).toLowerCase().includes(search.toLowerCase())),
    [q.data, search],
  )

  if (!cfg) return <EmptyState message="Module not found" />

  const allowDelete = cfg.allowDelete !== false

  return (
    <>
      <div className="page-head">
        <div>
          <span className="eyebrow">Operations</span>
          <h1>{cfg.title}</h1>
          <p>{cfg.description}</p>
        </div>
        <button
          className="button primary"
          onClick={() => {
            setFormError(null)
            setShow(true)
          }}
        >
          <Plus size={18} />
          {cfg.createLabel}
        </button>
      </div>

      <section className="panel">
        <div className="toolbar">
          <label className="search">
            <Search />
            <input placeholder={`Search ${cfg.title.toLowerCase()}…`} value={search} onChange={(e) => setSearch(e.target.value)} />
          </label>
          <div className="toolbar-actions">
            <button className="button secondary">
              <Download size={17} />
              Export
            </button>
          </div>
        </div>

        {q.isLoading ? (
          <div className="loading">
            <span />
            <p>Loading records…</p>
          </div>
        ) : rows.length === 0 ? (
          <EmptyState />
        ) : (
          <div className="table-wrap">
            <table className="data-table">
              <thead>
                <tr>
                  {cfg.columns.map((c) => (
                    <th key={c.key}>{c.label}</th>
                  ))}
                  <th className="actions-col">Actions</th>
                </tr>
              </thead>
              <tbody>
                {rows.map((row, i) => (
                  <tr key={String(row.id || row.uuid || i)}>
                    {cfg.columns.map((c) => (
                      <td key={c.key}>{c.format === 'status' ? <StatusPill value={String(row[c.key] || '')} /> : pretty(row[c.key], c.format)}</td>
                    ))}
                    <td className="row-actions">
                      {(cfg.rowActions || [])
                        .filter((a) => !a.visibleWhenStatus || a.visibleWhenStatus.includes(String(row.status || '')))
                        .map((action) => {
                          const Icon = (Icons as unknown as Record<string, typeof Icons.Box>)[action.icon] || Icons.Box
                          return (
                            <button
                              key={action.key}
                              className="icon-button"
                              title={action.label}
                              onClick={() => {
                                if (action.kind === 'remarks') setRemarksAction({ action, row })
                                else runAction.mutate({ action, row })
                              }}
                            >
                              <Icon size={17} />
                            </button>
                          )
                        })}
                      <button className="icon-button" title="View details" onClick={() => setViewing(row)}>
                        <Icons.Eye size={17} />
                      </button>
                      {allowDelete && (
                        <button className="icon-button" title="Delete" onClick={() => setDeleting(row)}>
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
          title={cfg.createLabel}
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
            fields={cfg.fields}
            onCancel={() => {
              setShow(false)
              setFormError(null)
            }}
            onSubmit={(v) => create.mutate(v)}
            busy={create.isPending}
          />
        </Modal>
      )}

      {viewing && (
        <Modal title={`${cfg.title} details`} onClose={() => setViewing(null)}>
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

      {deleting && (
        <ConfirmDialog
          title="Confirm deletion"
          message={`Are you sure you want to delete this ${cfg.title.toLowerCase().slice(0, -1)}? This action may not be reversible.`}
          confirmLabel="Delete"
          danger
          busy={remove.isPending}
          onConfirm={() => remove.mutate(deleting)}
          onCancel={() => setDeleting(null)}
        />
      )}

      {remarksAction && (
        <RemarksDialog
          title={remarksAction.action.label}
          label="Reason / remarks"
          busy={runAction.isPending}
          onSubmit={(remarks) => runAction.mutate({ action: remarksAction.action, row: remarksAction.row, remarks })}
          onCancel={() => setRemarksAction(null)}
        />
      )}

      {toast && <Toast message={toast.message} tone={toast.tone} onClose={() => setToast(null)} />}
    </>
  )
}
