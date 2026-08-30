import { useEffect, useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Camera, CheckCircle2, Plus, RotateCcw, Send } from 'lucide-react'
import { api, downloadFile } from '../lib/api'
import { demoRows } from '../lib/demo'
import { Modal } from '../components/Modal'
import { EntityForm } from '../components/EntityForm'
import { EmptyState } from '../components/EmptyState'
import { StatusPill } from '../components/StatusPill'
import { Toast } from '../components/Toast'
import { RemarksDialog } from '../components/RemarksDialog'
import { ReportFilters, type ReportFilterValue } from '../components/ReportFilters'
import { shortDate } from '../lib/format'
import type { EntityRecord } from '../lib/types'

export function DailyReportsPage() {
  const demo = sessionStorage.getItem('siteflow_user')?.includes('demo-owner')
  const [show, setShow] = useState(false)
  const [photosFor, setPhotosFor] = useState<EntityRecord | null>(null)
  const [returning, setReturning] = useState<EntityRecord | null>(null)
  const [formError, setFormError] = useState<string | null>(null)
  const [toast, setToast] = useState<{ message: string; tone?: 'info' | 'error' } | null>(null)
  const [filters, setFilters] = useState<ReportFilterValue>({ projectId: '', siteId: '', date: '', status: '' })
  const qc = useQueryClient()

  const q = useQuery({
    queryKey: ['module', 'daily-reports', filters],
    queryFn: async () => {
      if (demo) return demoRows['daily-reports']
      const params = Object.fromEntries(Object.entries({ project_id: filters.projectId, site_id: filters.siteId, date: filters.date, status: filters.status }).filter(([, value]) => value))
      return (await api.get('/daily-reports', { params })).data.data as EntityRecord[]
    },
  })

  const create = useMutation({
    mutationFn: async (v: Record<string, FormDataEntryValue>) => {
      if (demo) return v
      const cleaned = Object.fromEntries(Object.entries(v).filter(([, val]) => !(typeof val === 'string' && val.trim() === '')))
      return (await api.post('/daily-reports', cleaned)).data
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['module', 'daily-reports'] })
      setShow(false)
      setFormError(null)
      setToast({ message: 'Daily report saved as draft.' })
    },
    onError: (err: unknown) => {
      const anyErr = err as { response?: { data?: { message?: string; errors?: Record<string, string[]> } } }
      const msg = anyErr?.response?.data?.message || 'Failed to save report.'
      const errs = anyErr?.response?.data?.errors
      setFormError(errs ? `${msg} — ${Object.entries(errs).map(([f, m]) => `${f}: ${m.join(', ')}`).join(' | ')}` : msg)
    },
  })

  const submitReport = useMutation({
    mutationFn: async (row: EntityRecord) => (demo ? undefined : (await api.post(`/daily-reports/${row.id}/submit`)).data),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['module', 'daily-reports'] })
      setToast({ message: 'Report submitted for approval.' })
    },
    onError: (err: unknown) => {
      const anyErr = err as { response?: { data?: { message?: string } } }
      setToast({ message: anyErr?.response?.data?.message || 'Failed to submit report.', tone: 'error' })
    },
  })

  const approveReport = useMutation({
    mutationFn: async (row: EntityRecord) => (demo ? undefined : (await api.post(`/daily-reports/${row.id}/approve`)).data),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['module', 'daily-reports'] })
      setToast({ message: 'Report approved.' })
    },
    onError: (err: unknown) => {
      const anyErr = err as { response?: { data?: { message?: string } } }
      setToast({ message: anyErr?.response?.data?.message || 'Failed to approve — self-approval is blocked by default.', tone: 'error' })
    },
  })

  const returnReport = useMutation({
    mutationFn: async ({ row, remarks }: { row: EntityRecord; remarks: string }) =>
      demo ? undefined : (await api.post(`/daily-reports/${row.id}/return`, { review_remarks: remarks })).data,
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['module', 'daily-reports'] })
      setReturning(null)
      setToast({ message: 'Report returned for correction.' })
    },
    onError: (err: unknown) => {
      const anyErr = err as { response?: { data?: { message?: string } } }
      setToast({ message: anyErr?.response?.data?.message || 'Failed to return report.', tone: 'error' })
      setReturning(null)
    },
  })

  return (
    <>
      <div className="page-head">
        <div>
          <span className="eyebrow">Field operations</span>
          <h1>Daily Reports</h1>
          <p>Record daily field progress, resources and evidence. Submit for review once complete.</p>
        </div>
        <button
          className="button primary"
          onClick={() => {
            setFormError(null)
            setShow(true)
          }}
        >
          <Plus size={18} />
          Create report
        </button>
      </div>

      <section className="panel">
        <ReportFilters value={filters} onChange={setFilters} statuses={['draft', 'submitted', 'returned', 'approved']} />
        {q.isLoading ? (
          <div className="loading">
            <span />
            <p>Loading reports…</p>
          </div>
        ) : (q.data || []).length === 0 ? (
          <EmptyState />
        ) : (
          <div className="table-wrap">
            <table className="data-table">
              <thead>
                <tr>
                  <th>Date</th>
                  <th>Site</th>
                  <th>Weather</th>
                  <th>Status</th>
                  <th className="actions-col">Actions</th>
                </tr>
              </thead>
              <tbody>
                {(q.data || []).map((row) => (
                  <tr key={String(row.id)}>
                    <td>{shortDate(row.report_date)}</td>
                    <td>{String(row.site_name || (row.site as { site_name?: string } | undefined)?.site_name || '—')}</td>
                    <td>{String(row.weather || '—')}</td>
                    <td><StatusPill value={String(row.status || '')} /></td>
                    <td className="row-actions">
                      {(row.status === 'draft' || row.status === 'returned') && (
                        <button className="icon-button" title="Submit for approval" onClick={() => submitReport.mutate(row)}>
                          <Send size={17} />
                        </button>
                      )}
                      {row.status === 'submitted' && (
                        <>
                          <button className="icon-button" title="Approve" onClick={() => approveReport.mutate(row)}>
                            <CheckCircle2 size={17} />
                          </button>
                          <button className="icon-button" title="Return for correction" onClick={() => setReturning(row)}>
                            <RotateCcw size={17} />
                          </button>
                        </>
                      )}
                      <button className="icon-button" title="Photos" onClick={() => setPhotosFor(row)}>
                        <Camera size={17} />
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
          title="Create daily report"
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
          <EntityForm
            fields={[
              { name: 'site_id', label: 'Site', type: 'reference', referenceEndpoint: '/sites', required: true },
              { name: 'report_date', label: 'Report date', type: 'date', required: true },
              { name: 'weather', label: 'Weather' },
              { name: 'manpower_deployed', label: 'Manpower deployed', type: 'number' },
              { name: 'work_activities', label: 'Work activities', type: 'textarea' },
              { name: 'work_completed', label: 'Work completed', type: 'textarea' },
              { name: 'quantity_completed', label: 'Quantity completed', type: 'number', step: '0.01' },
              { name: 'unit', label: 'Unit' },
              { name: 'equipment_used', label: 'Equipment used', type: 'textarea' },
              { name: 'material_used', label: 'Material used', type: 'textarea' },
              { name: 'problems_delays', label: 'Problems / delays', type: 'textarea' },
              { name: 'reason_for_delay', label: 'Reason for delay', type: 'textarea' },
              { name: 'safety_incidents', label: 'Safety incidents', type: 'textarea' },
              { name: 'tomorrow_plan', label: "Tomorrow's plan", type: 'textarea' },
              { name: 'remarks', label: 'Remarks', type: 'textarea' },
            ]}
            submitLabel="Save as draft"
            onCancel={() => {
              setShow(false)
              setFormError(null)
            }}
            onSubmit={(v) => create.mutate(v)}
            busy={create.isPending}
          />
        </Modal>
      )}

      {photosFor && <PhotosModal report={photosFor} demo={!!demo} onClose={() => setPhotosFor(null)} onToast={(m, t) => setToast({ message: m, tone: t })} />}

      {returning && (
        <RemarksDialog
          title="Return for correction"
          label="Reason for returning this report"
          busy={returnReport.isPending}
          onSubmit={(remarks) => returnReport.mutate({ row: returning, remarks })}
          onCancel={() => setReturning(null)}
        />
      )}

      {toast && <Toast message={toast.message} tone={toast.tone} onClose={() => setToast(null)} />}
    </>
  )
}

function PhotosModal({ report, demo, onClose, onToast }: { report: EntityRecord; demo: boolean; onClose: () => void; onToast: (m: string, t?: 'info' | 'error') => void }) {
  const qc = useQueryClient()
  const [file, setFile] = useState<File | null>(null)
  const [caption, setCaption] = useState('')

  const photosQuery = useQuery({
    queryKey: ['report-photos', report.id],
    queryFn: async () => {
      if (demo) return [] as EntityRecord[]
      const res = await api.get(`/daily-reports/${report.id}`)
      return (res.data.data?.photos || []) as EntityRecord[]
    },
  })

  const upload = useMutation({
    mutationFn: async () => {
      if (demo || !file) return
      const fd = new FormData()
      fd.append('photo', file)
      if (caption) fd.append('caption', caption)
      return (await api.post(`/daily-reports/${report.id}/photos`, fd)).data
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['report-photos', report.id] })
      setFile(null)
      setCaption('')
      onToast('Photo uploaded successfully.')
    },
    onError: (err: unknown) => {
      const anyErr = err as { response?: { data?: { message?: string } } }
      onToast(anyErr?.response?.data?.message || 'Upload failed — check file type and size.', 'error')
    },
  })

  async function download(photo: EntityRecord) {
    if (demo) return
    try {
      await downloadFile(`/daily-report-photos/${photo.id}/download`, String(photo.original_filename || 'photo'))
    } catch {
      onToast('Failed to download photo.', 'error')
    }
  }

  return (
    <Modal title="Report photos" onClose={onClose}>
      <div style={{ padding: 22 }}>
        {photosQuery.isLoading ? (
          <div className="loading">
            <span />
          </div>
        ) : (photosQuery.data || []).length === 0 ? (
          <EmptyState message="No photos uploaded yet" />
        ) : (
          <div className="photo-list">
            {(photosQuery.data || []).map((p) => (
              <PhotoPreview key={String(p.id)} photo={p} onDownload={() => download(p)} />
            ))}
          </div>
        )}

        <div style={{ marginTop: 18, paddingTop: 18, borderTop: '1px solid #e5ecef' }}>
          <label style={{ display: 'flex', flexDirection: 'column', gap: 7, fontSize: 12, fontWeight: 600, color: '#455761', marginBottom: 12 }}>
            <span>Photo file</span>
            <input type="file" accept="image/jpeg,image/png,image/heic,image/webp" onChange={(e) => setFile(e.target.files?.[0] || null)} />
          </label>
          <label style={{ display: 'flex', flexDirection: 'column', gap: 7, fontSize: 12, fontWeight: 600, color: '#455761' }}>
            <span>Caption</span>
            <input value={caption} onChange={(e) => setCaption(e.target.value)} />
          </label>
          <div className="form-actions" style={{ padding: '18px 0 0' }}>
            <button className="button primary" disabled={!file || upload.isPending} onClick={() => upload.mutate()}>
              {upload.isPending ? 'Uploading…' : 'Upload photo'}
            </button>
          </div>
        </div>
      </div>
    </Modal>
  )
}

function PhotoPreview({ photo, onDownload }: { photo: EntityRecord; onDownload: () => void }) {
  const image = useQuery({
    queryKey: ['photo-preview', photo.id],
    queryFn: async () => {
      const response = await api.get(`/daily-report-photos/${photo.id}/download`, { responseType: 'blob' })
      return URL.createObjectURL(response.data as Blob)
    },
    staleTime: 60_000,
  })
  useEffect(() => () => { if (image.data) URL.revokeObjectURL(image.data) }, [image.data])
  return <button className="photo-preview" onClick={onDownload} title="Download full-size photo">
    {image.data ? <img src={image.data} alt={String(photo.caption || photo.original_filename || 'Daily report photo')} /> : <span>Loading preview…</span>}
    <b>{String(photo.caption || photo.original_filename)}</b>
  </button>
}
