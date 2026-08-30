import { useMemo } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { CheckCircle2, ClipboardCheck, FileText, Ruler, XCircle } from 'lucide-react'
import { api } from '../lib/api'
import { EmptyState } from '../components/EmptyState'
import { StatusPill } from '../components/StatusPill'
import { Toast } from '../components/Toast'
import { useState } from 'react'
import { shortDate } from '../lib/format'
import type { EntityRecord } from '../lib/types'

type Approval = EntityRecord & { kind: 'daily-report' | 'measurement' | 'bill'; label: string; detail: string; date: unknown }
const siteName = (row: EntityRecord) => String(row.site_name || (row.site as { site_name?: string } | undefined)?.site_name || 'Site')

export function ApprovalsPage() {
  const [toast, setToast] = useState<{ message: string; tone?: 'info' | 'error' } | null>(null)
  const queryClient = useQueryClient()
  const pending = useQuery({
    queryKey: ['approval-inbox'],
    queryFn: async (): Promise<Approval[]> => {
      const [reports, measurements, projects] = await Promise.allSettled([
        api.get('/daily-reports', { params: { status: 'submitted', per_page: 100 } }),
        api.get('/measurements', { params: { status: 'submitted', per_page: 100 } }),
        api.get('/projects'),
      ])
      const daily = reports.status === 'fulfilled' ? (reports.value.data?.data || []).map((row: EntityRecord) => ({ ...row, kind: 'daily-report' as const, label: 'Daily report', detail: siteName(row), date: row.report_date })) : []
      const measured = measurements.status === 'fulfilled' ? (measurements.value.data?.data || []).map((row: EntityRecord) => ({ ...row, kind: 'measurement' as const, label: 'Measurement', detail: siteName(row), date: row.measurement_date })) : []
      const projectRows = projects.status === 'fulfilled' ? projects.value.data?.data || [] : []
      const billResponses = await Promise.allSettled(projectRows.map((project: EntityRecord) => api.get(`/projects/${project.id}/bills`, { params: { status: 'submitted', per_page: 100 } })))
      const bills = billResponses.flatMap((response, index) => response.status === 'fulfilled' ? (response.value.data?.data || []).map((row: EntityRecord) => ({ ...row, kind: 'bill' as const, label: 'Bill certification', detail: `${projectRows[index].project_code} — ${row.bill_number}`, date: row.bill_date })) : [])
      return [...daily, ...measured, ...bills]
    },
  })
  const action = useMutation({
    mutationFn: async ({ item, action }: { item: Approval; action: 'approve' | 'reject' | 'certify' }) => {
      const path = item.kind === 'daily-report' ? `/daily-reports/${item.id}/${action === 'approve' ? 'approve' : 'return'}`
        : item.kind === 'measurement' ? `/measurements/${item.id}/${action === 'approve' ? 'approve' : 'reject'}`
          : `/bills/${item.id}/certify`
      return api.post(path, action === 'reject' ? { review_remarks: 'Returned from approval inbox.' } : {})
    },
    onSuccess: () => { queryClient.invalidateQueries({ queryKey: ['approval-inbox'] }); setToast({ message: 'Approval action completed.' }) },
    onError: (error: { response?: { data?: { message?: string } } }) => setToast({ message: error.response?.data?.message || 'This action is not allowed for your role or assignment.', tone: 'error' }),
  })
  const rows = useMemo(() => pending.data || [], [pending.data])
  return <>
    <div className="page-head"><div><span className="eyebrow">Review centre</span><h1>Approvals</h1><p>Only records you are authorized to approve are actionable. The server rechecks role, assignment and self-approval rules.</p></div></div>
    <section className="panel">
      {pending.isLoading ? <div className="loading"><span /><p>Loading approvals…</p></div> : rows.length === 0 ? <EmptyState message="Nothing is awaiting your approval" /> : <div className="table-wrap"><table className="data-table"><thead><tr><th>Type</th><th>Record</th><th>Date</th><th>Status</th><th className="actions-col">Decision</th></tr></thead><tbody>
        {rows.map((item) => <tr key={`${item.kind}-${item.id}`}><td>{item.kind === 'bill' ? <FileText size={17} /> : item.kind === 'measurement' ? <Ruler size={17} /> : <ClipboardCheck size={17} />}</td><td><strong>{item.label}</strong><br /><small>{item.detail}</small></td><td>{shortDate(item.date)}</td><td><StatusPill value="submitted" /></td><td className="row-actions">
          {item.kind === 'bill' ? <button className="button primary" disabled={action.isPending} onClick={() => action.mutate({ item, action: 'certify' })}><CheckCircle2 size={16} /> Certify</button> : <>
            <button className="button primary" disabled={action.isPending} onClick={() => action.mutate({ item, action: 'approve' })}><CheckCircle2 size={16} /> Approve</button>
            <button className="button secondary" disabled={action.isPending} onClick={() => action.mutate({ item, action: 'reject' })}><XCircle size={16} /> Return</button>
          </>}
        </td></tr>)}
      </tbody></table></div>}
    </section>
    {toast && <Toast message={toast.message} tone={toast.tone} onClose={() => setToast(null)} />}
  </>
}
