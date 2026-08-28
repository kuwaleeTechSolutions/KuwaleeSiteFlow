import { Inbox } from 'lucide-react'

export function EmptyState({ message = 'No records found' }: { message?: string }) {
  return (
    <div className="empty">
      <Inbox size={34} />
      <h3>{message}</h3>
      <p>Try adjusting your search or filters, or create a new record.</p>
    </div>
  )
}
