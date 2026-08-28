import { X } from 'lucide-react'
import type { ReactNode } from 'react'

export function Modal({ title, children, onClose, wide }: { title: string; children: ReactNode; onClose: () => void; wide?: boolean }) {
  return (
    <div className="modal-backdrop" onMouseDown={onClose}>
      <section className={wide ? 'modal modal-wide' : 'modal'} onMouseDown={(e) => e.stopPropagation()}>
        <header>
          <h2>{title}</h2>
          <button className="icon-button" onClick={onClose} aria-label="Close">
            <X />
          </button>
        </header>
        {children}
      </section>
    </div>
  )
}
