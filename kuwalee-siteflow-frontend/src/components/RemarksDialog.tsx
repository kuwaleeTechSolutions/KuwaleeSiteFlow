import { useState } from 'react'
import { Modal } from './Modal'

/**
 * Used for workflow actions that require a reason from the caller before the
 * backend will accept them — e.g. returning a Daily Report for correction,
 * or rejecting a Measurement. The backend Form Requests for these actions
 * (ReturnDailyReportRequest, RejectMeasurementRequest) require
 * `review_remarks` as a non-empty string, so this dialog enforces that on
 * the client too before the request is even sent.
 */
export function RemarksDialog({
  title,
  label,
  busy,
  onSubmit,
  onCancel,
}: {
  title: string
  label: string
  busy?: boolean
  onSubmit: (remarks: string) => void
  onCancel: () => void
}) {
  const [remarks, setRemarks] = useState('')

  return (
    <Modal title={title} onClose={onCancel}>
      <div style={{ padding: 22 }}>
        <label style={{ display: 'flex', flexDirection: 'column', gap: 7, fontSize: 12, fontWeight: 600, color: '#455761' }}>
          <span>{label} *</span>
          <textarea
            value={remarks}
            onChange={(e) => setRemarks(e.target.value)}
            required
            style={{ minHeight: 100, border: '1px solid #cfdbe0', borderRadius: 8, padding: '10px 11px' }}
          />
        </label>
        <div className="form-actions" style={{ padding: '18px 0 0' }}>
          <button className="button secondary" onClick={onCancel}>
            Cancel
          </button>
          <button className="button primary" disabled={busy || remarks.trim() === ''} onClick={() => onSubmit(remarks.trim())}>
            {busy ? 'Submitting…' : 'Submit'}
          </button>
        </div>
      </div>
    </Modal>
  )
}
