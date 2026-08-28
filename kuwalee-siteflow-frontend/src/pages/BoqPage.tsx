import { useEffect, useState, type FormEvent } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Plus, Trash2 } from 'lucide-react'
import { api } from '../lib/api'
import { demoBoq, demoRows } from '../lib/demo'
import { Modal } from '../components/Modal'
import { EmptyState } from '../components/EmptyState'
import { Toast } from '../components/Toast'
import { money } from '../lib/format'
import type { EntityRecord } from '../lib/types'

type BoqItemRow = { item_number: string; description: string; unit: string; contract_quantity: string; contract_rate: string }

export function BoqPage() {
  const demo = sessionStorage.getItem('siteflow_user')?.includes('demo-owner')
  const [projectId, setProjectId] = useState('')
  const [show, setShow] = useState(false)
  const [reason, setReason] = useState('')
  const [effectiveDate, setEffectiveDate] = useState('')
  const [items, setItems] = useState<BoqItemRow[]>([{ item_number: '', description: '', unit: '', contract_quantity: '', contract_rate: '' }])
  const [formError, setFormError] = useState<string | null>(null)
  const [toast, setToast] = useState('')
  const qc = useQueryClient()

  const projectsQuery = useQuery({
    queryKey: ['projects-for-boq'],
    queryFn: async () => {
      if (demo) return demoRows.projects as EntityRecord[]
      return (await api.get('/projects')).data.data as EntityRecord[]
    },
  })

  useEffect(() => {
    if (!projectId && projectsQuery.data && projectsQuery.data.length > 0) setProjectId(String(projectsQuery.data[0].id))
  }, [projectsQuery.data, projectId])

  const boqQuery = useQuery({
    queryKey: ['boq', projectId],
    queryFn: async () => {
      if (demo) return demoBoq
      return (await api.get(`/projects/${projectId}/boq-items`)).data.data as EntityRecord[]
    },
    enabled: !!projectId,
  })

  const createRevision = useMutation({
    mutationFn: async () => {
      if (demo) return
      return (
        await api.post(`/projects/${projectId}/boq-items/revisions`, {
          reason,
          effective_date: effectiveDate,
          items: items.map((it) => ({
            item_number: it.item_number,
            description: it.description,
            unit: it.unit,
            contract_quantity: Number(it.contract_quantity),
            contract_rate: Number(it.contract_rate),
          })),
        })
      ).data
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['boq'] })
      setShow(false)
      setFormError(null)
      setReason('')
      setEffectiveDate('')
      setItems([{ item_number: '', description: '', unit: '', contract_quantity: '', contract_rate: '' }])
      setToast('BOQ revision created successfully.')
    },
    onError: (err: unknown) => {
      const anyErr = err as { response?: { data?: { message?: string; errors?: Record<string, string[]> } } }
      const msg = anyErr?.response?.data?.message || 'Failed to create revision.'
      const errs = anyErr?.response?.data?.errors
      setFormError(errs ? `${msg} — ${Object.entries(errs).map(([f, m]) => `${f}: ${m.join(', ')}`).join(' | ')}` : msg)
    },
  })

  function updateItem(index: number, patch: Partial<BoqItemRow>) {
    setItems((prev) => prev.map((it, i) => (i === index ? { ...it, ...patch } : it)))
  }

  function submit(e: FormEvent) {
    e.preventDefault()
    createRevision.mutate()
  }

  return (
    <>
      <div className="page-head">
        <div>
          <span className="eyebrow">Commercial</span>
          <h1>Bill of Quantities</h1>
          <p>Current effective BOQ per project. Revisions never overwrite history — each revision adds new rows.</p>
        </div>
        <button
          className="button primary"
          onClick={() => {
            setFormError(null)
            setShow(true)
          }}
        >
          <Plus size={18} />
          Create revision
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
          <EmptyState message="Select a project above to view its BOQ" />
        ) : boqQuery.isLoading ? (
          <div className="loading">
            <span />
            <p>Loading BOQ…</p>
          </div>
        ) : (boqQuery.data || []).length === 0 ? (
          <EmptyState message="No BOQ items yet for this project" />
        ) : (
          <div className="table-wrap">
            <table className="data-table">
              <thead>
                <tr>
                  <th>Item</th>
                  <th>Description</th>
                  <th>Unit</th>
                  <th>Contract qty</th>
                  <th>Rate</th>
                  <th>Progress %</th>
                </tr>
              </thead>
              <tbody>
                {(boqQuery.data || []).map((it) => (
                  <tr key={String(it.id)}>
                    <td>{String(it.item_number)}</td>
                    <td>{String(it.description)}</td>
                    <td>{String(it.unit)}</td>
                    <td>{String(it.contract_quantity)}</td>
                    <td>{money(it.contract_rate)}</td>
                    <td>{String(it.percentage_progress ?? '0')}%</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </section>

      {show && (
        <Modal
          title="Create BOQ revision"
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
              <span>Revision reason *</span>
              <input value={reason} onChange={(e) => setReason(e.target.value)} required />
            </label>
            <label>
              <span>Effective date *</span>
              <input type="date" value={effectiveDate} onChange={(e) => setEffectiveDate(e.target.value)} required />
            </label>

            <div className="span-2">
              <h3 style={{ fontSize: 13, color: '#173b57', margin: '6px 0 10px' }}>Items in this revision</h3>
              {items.map((it, i) => (
                <div key={i} className="boq-item-row">
                  <input placeholder="Item no." value={it.item_number} onChange={(e) => updateItem(i, { item_number: e.target.value })} required />
                  <input placeholder="Description" value={it.description} onChange={(e) => updateItem(i, { description: e.target.value })} required />
                  <input placeholder="Unit" value={it.unit} onChange={(e) => updateItem(i, { unit: e.target.value })} required />
                  <input placeholder="Quantity" type="number" step="0.001" value={it.contract_quantity} onChange={(e) => updateItem(i, { contract_quantity: e.target.value })} required />
                  <input placeholder="Rate" type="number" step="0.01" value={it.contract_rate} onChange={(e) => updateItem(i, { contract_rate: e.target.value })} required />
                  <button type="button" className="icon-button" onClick={() => setItems((prev) => prev.filter((_, idx) => idx !== i))} disabled={items.length === 1}>
                    <Trash2 size={16} />
                  </button>
                </div>
              ))}
              <button
                type="button"
                className="button secondary"
                style={{ marginTop: 8 }}
                onClick={() => setItems((prev) => [...prev, { item_number: '', description: '', unit: '', contract_quantity: '', contract_rate: '' }])}
              >
                <Plus size={16} />
                Add another item
              </button>
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
              <button className="button primary" disabled={createRevision.isPending}>
                {createRevision.isPending ? 'Saving…' : 'Create revision'}
              </button>
            </div>
          </form>
        </Modal>
      )}

      {toast && <Toast message={toast} onClose={() => setToast('')} />}
    </>
  )
}
