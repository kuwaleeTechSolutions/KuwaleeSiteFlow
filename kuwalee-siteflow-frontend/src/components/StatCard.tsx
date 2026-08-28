import type { LucideIcon } from 'lucide-react'

export function StatCard({
  label,
  value,
  icon: Icon,
  tone = 'blue',
  hint,
}: {
  label: string
  value: string | number
  icon: LucideIcon
  tone?: string
  hint?: string
}) {
  return (
    <article className="stat-card">
      <div className={`stat-icon ${tone}`}>
        <Icon size={21} />
      </div>
      <div>
        <p>{label}</p>
        <strong>{value}</strong>
        {hint && <small>{hint}</small>}
      </div>
    </article>
  )
}
