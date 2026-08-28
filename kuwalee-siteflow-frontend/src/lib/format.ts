export function money(v: unknown): string {
  return new Intl.NumberFormat('en-IN', { style: 'currency', currency: 'INR', maximumFractionDigits: 2 }).format(Number(v || 0))
}

export function shortDate(v: unknown): string {
  if (v == null) return '—'
  const d = new Date(String(v))
  return isNaN(d.valueOf()) ? String(v) : d.toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric' })
}

export function dateTime(v: unknown): string {
  if (v == null) return '—'
  const d = new Date(String(v))
  return isNaN(d.valueOf()) ? String(v) : d.toLocaleString('en-IN', { dateStyle: 'medium', timeStyle: 'short' })
}

export function titleCase(v: unknown): string {
  return String(v ?? '').replaceAll('_', ' ')
}
