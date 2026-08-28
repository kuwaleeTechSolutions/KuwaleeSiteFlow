export function StatusPill({ value }: { value?: string }) {
  const v = (value || 'unknown').toLowerCase().replaceAll(' ', '_')
  return <span className={`status status-${v}`}>{value?.replaceAll('_', ' ') || 'Unknown'}</span>
}
