import { Modal } from './Modal'

export function ConfirmDialog({
  title,
  message,
  confirmLabel = 'Confirm',
  danger,
  busy,
  onConfirm,
  onCancel,
}: {
  title: string
  message: string
  confirmLabel?: string
  danger?: boolean
  busy?: boolean
  onConfirm: () => void
  onCancel: () => void
}) {
  return (
    <Modal title={title} onClose={onCancel}>
      <div style={{ padding: 22 }}>
        <p style={{ marginBottom: 20, color: '#455761' }}>{message}</p>
        <div className="form-actions" style={{ padding: 0 }}>
          <button className="button secondary" onClick={onCancel}>
            Cancel
          </button>
          <button className="button primary" style={danger ? { background: '#a8423d' } : undefined} disabled={busy} onClick={onConfirm}>
            {busy ? 'Please wait…' : confirmLabel}
          </button>
        </div>
      </div>
    </Modal>
  )
}
