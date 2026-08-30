import { useEffect, useState, type FormEvent } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { CreditCard, FileDown, Plus, Send, ShieldCheck, Trash2 } from 'lucide-react'
import { api, downloadFile } from '../lib/api'
import { demoBills, demoPayments, demoRows } from '../lib/demo'
import { Modal } from '../components/Modal'
import { EmptyState } from '../components/EmptyState'
import { StatusPill } from '../components/StatusPill'
import { Toast } from '../components/Toast'
import { money, shortDate } from '../lib/format'
import type { EntityRecord } from '../lib/types'

type BillItemRow = { measurement_item_id: string; quantity_billed: string }

export function BillsPage() {
  const demo = sessionStorage.getItem('siteflow_user')?.includes('demo-owner')
  const [projectId, setProjectId] = useState('')
  const [billDateFilter, setBillDateFilter] = useState('')
  const [billStatus, setBillStatus] = useState('')
  const [show, setShow] = useState(false)
  const [formError, setFormError] = useState<string | null>(null)
  const [toast, setToast] = useState<{ message: string; tone?: 'info' | 'error' } | null>(null)
  const [paymentsFor, setPaymentsFor] = useState<EntityRecord | null>(null)

  const [billNumber, setBillNumber] = useState('')
  const [billType, setBillType] = useState('running')
  const [billDate, setBillDate] = useState('')
  const [periodStart, setPeriodStart] = useState('')
  const [periodEnd, setPeriodEnd] = useState('')
  const [deductions, setDeductions] = useState('')
  const [taxes, setTaxes] = useState('')
  const [items, setItems] = useState<BillItemRow[]>([{ measurement_item_id: '', quantity_billed: '' }])

  const qc = useQueryClient()

  const projectsQuery = useQuery({
    queryKey: ['projects-for-bills'],
    queryFn: async () => {
      if (demo) return demoRows.projects as EntityRecord[]
      return (await api.get('/projects')).data.data as EntityRecord[]
    },
  })

  useEffect(() => {
    if (!projectId && projectsQuery.data && projectsQuery.data.length > 0) setProjectId(String(projectsQuery.data[0].id))
  }, [projectsQuery.data, projectId])

  const billsQuery = useQuery({
    queryKey: ['bills', projectId, billDateFilter, billStatus],
    queryFn: async () => {
      if (demo) return demoBills
      const params = Object.fromEntries(Object.entries({ status: billStatus, date: billDateFilter }).filter(([, value]) => value))
      return (await api.get(`/projects/${projectId}/bills`, { params })).data.data as EntityRecord[]
    },
    enabled: !!projectId,
  })

  const create = useMutation({
    mutationFn: async () => {
      if (demo) return
      return (
        await api.post(`/projects/${projectId}/bills`, {
          bill_number: billNumber,
          bill_type: billType,
          bill_date: billDate,
          billing_period_start: periodStart,
          billing_period_end: periodEnd,
          deductions: deductions || undefined,
          taxes: taxes || undefined,
          items: items.map((it) => ({ measurement_item_id: it.measurement_item_id, quantity_billed: Number(it.quantity_billed) })),
        })
      ).data
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['bills'] })
      setShow(false)
      setFormError(null)
      setToast({ message: 'Bill created successfully.' })
    },
    onError: (err: unknown) => {
      const anyErr = err as { response?: { data?: { message?: string; errors?: Record<string, string[]> } } }
      const msg = anyErr?.response?.data?.message || 'Failed to create bill.'
      const errs = anyErr?.response?.data?.errors
      setFormError(errs ? `${msg} — ${Object.entries(errs).map(([f, m]) => `${f}: ${m.join(', ')}`).join(' | ')}` : msg)
    },
  })

  const submitBill = useMutation({
    mutationFn: async (bill: EntityRecord) => (demo ? undefined : (await api.post(`/bills/${bill.id}/submit`)).data),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['bills'] })
      setToast({ message: 'Bill submitted for certification.' })
    },
    onError: (err: unknown) => {
      const anyErr = err as { response?: { data?: { message?: string } } }
      setToast({ message: anyErr?.response?.data?.message || 'Failed to submit bill.', tone: 'error' })
    },
  })

  const certifyBill = useMutation({
    mutationFn: async (bill: EntityRecord) => (demo ? undefined : (await api.post(`/bills/${bill.id}/certify`)).data),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['bills'] })
      setToast({ message: 'Bill certified.' })
    },
    onError: (err: unknown) => {
      const anyErr = err as { response?: { data?: { message?: string } } }
      setToast({ message: anyErr?.response?.data?.message || 'Failed to certify — you may be the bill creator (self-certification is blocked by default).', tone: 'error' })
    },
  })

  async function exportPdf(bill: EntityRecord) {
    if (demo) {
      setToast({ message: 'PDF export requires the live backend — not available in demo mode.' })
      return
    }
    try {
      await downloadFile(`/bills/${bill.id}/pdf`, `bill-${bill.bill_number}.pdf`)
    } catch {
      setToast({ message: 'Failed to export PDF.', tone: 'error' })
    }
  }

  function updateItem(index: number, patch: Partial<BillItemRow>) {
    setItems((prev) => prev.map((it, i) => (i === index ? { ...it, ...patch } : it)))
  }

  function submit(e: FormEvent) {
    e.preventDefault()
    create.mutate()
  }

  return (
    <>
      <div className="page-head">
        <div>
          <span className="eyebrow">Commercial</span>
          <h1>Bills</h1>
          <p>Create, submit and certify running and final bills against approved measurements.</p>
        </div>
        <button
          className="button primary"
          onClick={() => {
            setFormError(null)
            setShow(true)
          }}
        >
          <Plus size={18} />
          Create bill
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
          <input type="date" value={billDateFilter} onChange={(event) => setBillDateFilter(event.target.value)} aria-label="Filter bills by date" />
          <select value={billStatus} onChange={(event) => setBillStatus(event.target.value)} aria-label="Filter bills by status">
            <option value="">All statuses</option><option value="draft">Draft</option><option value="submitted">Submitted</option><option value="certified">Certified</option>
          </select>
        </div>

        {!projectId ? (
          <EmptyState message="Select a project above to view its bills" />
        ) : billsQuery.isLoading ? (
          <div className="loading">
            <span />
            <p>Loading bills…</p>
          </div>
        ) : (billsQuery.data || []).length === 0 ? (
          <EmptyState message="No bills yet for this project" />
        ) : (
          <div className="table-wrap">
            <table className="data-table">
              <thead>
                <tr>
                  <th>Bill number</th>
                  <th>Type</th>
                  <th>Date</th>
                  <th>Net payable</th>
                  <th>Outstanding</th>
                  <th>Status</th>
                  <th className="actions-col">Actions</th>
                </tr>
              </thead>
              <tbody>
                {(billsQuery.data || []).map((b) => (
                  <tr key={String(b.id)}>
                    <td>{String(b.bill_number)}</td>
                    <td>{String(b.bill_type)}</td>
                    <td>{shortDate(b.bill_date)}</td>
                    <td>{money(b.net_payable)}</td>
                    <td>{money(b.outstanding_amount)}</td>
                    <td><StatusPill value={String(b.status || '')} /></td>
                    <td className="row-actions">
                      {b.status === 'draft' && (
                        <button className="icon-button" title="Submit for certification" onClick={() => submitBill.mutate(b)}>
                          <Send size={17} />
                        </button>
                      )}
                      {b.status === 'submitted' && (
                        <button className="icon-button" title="Certify" onClick={() => certifyBill.mutate(b)}>
                          <ShieldCheck size={17} />
                        </button>
                      )}
                      <button className="icon-button" title="Payments" onClick={() => setPaymentsFor(b)}>
                        <CreditCard size={17} />
                      </button>
                      <button className="icon-button" title="Export PDF" onClick={() => exportPdf(b)}>
                        <FileDown size={17} />
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
          title="Create bill"
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
              <span>Bill number *</span>
              <input value={billNumber} onChange={(e) => setBillNumber(e.target.value)} required />
            </label>
            <label>
              <span>Bill type *</span>
              <select value={billType} onChange={(e) => setBillType(e.target.value)} required>
                <option value="running">Running</option>
                <option value="interim">Interim</option>
                <option value="final">Final</option>
              </select>
            </label>
            <label>
              <span>Bill date *</span>
              <input type="date" value={billDate} onChange={(e) => setBillDate(e.target.value)} required />
            </label>
            <label>
              <span>Billing period start *</span>
              <input type="date" value={periodStart} onChange={(e) => setPeriodStart(e.target.value)} required />
            </label>
            <label>
              <span>Billing period end *</span>
              <input type="date" value={periodEnd} onChange={(e) => setPeriodEnd(e.target.value)} required />
            </label>
            <label>
              <span>Deductions</span>
              <input type="number" step="0.01" value={deductions} onChange={(e) => setDeductions(e.target.value)} />
            </label>
            <label>
              <span>Taxes / TDS</span>
              <input type="number" step="0.01" value={taxes} onChange={(e) => setTaxes(e.target.value)} />
            </label>

            <div className="span-2">
              <h3 style={{ fontSize: 13, color: '#173b57', margin: '6px 0 4px' }}>Bill items</h3>
              <p style={{ fontSize: 12, color: '#667680', marginBottom: 10 }}>
                Enter the ID of an approved Measurement item (find it via View details on the Measurements page) and the quantity to bill.
              </p>
              {items.map((it, i) => (
                <div key={i} className="boq-item-row" style={{ gridTemplateColumns: '1fr 1fr auto' }}>
                  <input placeholder="Measurement item ID" value={it.measurement_item_id} onChange={(e) => updateItem(i, { measurement_item_id: e.target.value })} required />
                  <input placeholder="Quantity billed" type="number" step="0.001" value={it.quantity_billed} onChange={(e) => updateItem(i, { quantity_billed: e.target.value })} required />
                  <button type="button" className="icon-button" onClick={() => setItems((prev) => prev.filter((_, idx) => idx !== i))} disabled={items.length === 1}>
                    <Trash2 size={16} />
                  </button>
                </div>
              ))}
              <button type="button" className="button secondary" style={{ marginTop: 8 }} onClick={() => setItems((prev) => [...prev, { measurement_item_id: '', quantity_billed: '' }])}>
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
              <button className="button primary" disabled={create.isPending}>
                {create.isPending ? 'Saving…' : 'Create bill'}
              </button>
            </div>
          </form>
        </Modal>
      )}

      {paymentsFor && <PaymentsModal bill={paymentsFor} demo={!!demo} onClose={() => setPaymentsFor(null)} onToast={(m, t) => setToast({ message: m, tone: t })} />}

      {toast && <Toast message={toast.message} tone={toast.tone} onClose={() => setToast(null)} />}
    </>
  )
}

function PaymentsModal({
  bill,
  demo,
  onClose,
  onToast,
}: {
  bill: EntityRecord
  demo: boolean
  onClose: () => void
  onToast: (message: string, tone?: 'info' | 'error') => void
}) {
  const qc = useQueryClient()
  const [amount, setAmount] = useState('')
  const [paymentDate, setPaymentDate] = useState('')
  const [reference, setReference] = useState('')
  const [mode, setMode] = useState('')

  const paymentsQuery = useQuery({
    queryKey: ['payments', bill.id],
    queryFn: async () => {
      if (demo) return demoPayments
      return (await api.get(`/bills/${bill.id}/payments`)).data.data as EntityRecord[]
    },
  })

  const record = useMutation({
    mutationFn: async () => {
      if (demo) return
      return (
        await api.post(`/bills/${bill.id}/payments`, {
          amount: Number(amount),
          payment_date: paymentDate,
          payment_reference: reference || undefined,
          payment_mode: mode || undefined,
        })
      ).data
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['payments', bill.id] })
      qc.invalidateQueries({ queryKey: ['bills'] })
      setAmount('')
      setPaymentDate('')
      setReference('')
      setMode('')
      onToast('Payment recorded successfully.')
    },
    onError: (err: unknown) => {
      const anyErr = err as { response?: { data?: { message?: string } } }
      onToast(anyErr?.response?.data?.message || 'Failed to record payment.', 'error')
    },
  })

  return (
    <Modal title={`Payments — ${bill.bill_number}`} wide onClose={onClose}>
      <div style={{ padding: '0 22px 22px' }}>
        <div style={{ display: 'flex', gap: 24, padding: '18px 0', borderBottom: '1px solid #e5ecef', marginBottom: 16 }}>
          <div>
            <small style={{ color: '#667680' }}>Net payable</small>
            <div style={{ fontWeight: 700 }}>{money(bill.net_payable)}</div>
          </div>
          <div>
            <small style={{ color: '#667680' }}>Outstanding</small>
            <div style={{ fontWeight: 700 }}>{money(bill.outstanding_amount)}</div>
          </div>
          <div>
            <small style={{ color: '#667680' }}>Status</small>
            <div><StatusPill value={String(bill.status || '')} /></div>
          </div>
        </div>

        {paymentsQuery.isLoading ? (
          <div className="loading">
            <span />
            <p>Loading payments…</p>
          </div>
        ) : (paymentsQuery.data || []).length === 0 ? (
          <EmptyState message="No payments recorded yet" />
        ) : (
          <table className="data-table" style={{ marginBottom: 18 }}>
            <thead>
              <tr>
                <th>Date</th>
                <th>Amount</th>
                <th>Reference</th>
                <th>Mode</th>
              </tr>
            </thead>
            <tbody>
              {(paymentsQuery.data || []).map((p) => (
                <tr key={String(p.id)}>
                  <td>{shortDate(p.payment_date)}</td>
                  <td>{money(p.amount)}</td>
                  <td>{String(p.payment_reference || '—')}</td>
                  <td>{String(p.payment_mode || '—')}</td>
                </tr>
              ))}
            </tbody>
          </table>
        )}

        {bill.status !== 'draft' && bill.status !== 'submitted' && (
          <>
            <h3 style={{ fontSize: 13, color: '#173b57', margin: '4px 0 10px' }}>Record a payment</h3>
            <div className="entity-form" style={{ padding: 0 }}>
              <label>
                <span>Amount *</span>
                <input type="number" step="0.01" value={amount} onChange={(e) => setAmount(e.target.value)} />
              </label>
              <label>
                <span>Payment date *</span>
                <input type="date" value={paymentDate} onChange={(e) => setPaymentDate(e.target.value)} />
              </label>
              <label>
                <span>Reference</span>
                <input value={reference} onChange={(e) => setReference(e.target.value)} />
              </label>
              <label>
                <span>Mode</span>
                <input value={mode} onChange={(e) => setMode(e.target.value)} placeholder="Bank Transfer" />
              </label>
              <div className="form-actions">
                <button className="button primary" disabled={record.isPending || !amount || !paymentDate} onClick={() => record.mutate()}>
                  {record.isPending ? 'Recording…' : 'Record payment'}
                </button>
              </div>
            </div>
          </>
        )}
      </div>
    </Modal>
  )
}
