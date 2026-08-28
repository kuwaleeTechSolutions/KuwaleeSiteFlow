import { useEffect } from 'react'

export function Toast({ message, tone = 'info', onClose }: { message: string; tone?: 'info' | 'error'; onClose: () => void }) {
  useEffect(() => {
    const timer = setTimeout(onClose, 4500)
    return () => clearTimeout(timer)
  }, [onClose])

  return (
    <div className={tone === 'error' ? 'toast toast-error' : 'toast'} role="status">
      <span>{message}</span>
      <button onClick={onClose} aria-label="Dismiss">
        ×
      </button>
    </div>
  )
}
