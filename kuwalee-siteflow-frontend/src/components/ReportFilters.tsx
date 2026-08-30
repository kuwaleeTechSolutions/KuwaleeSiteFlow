import { useQuery } from '@tanstack/react-query'
import { FilterX } from 'lucide-react'
import { api, fetchReferenceList } from '../lib/api'

export type ReportFilterValue = { projectId: string; siteId: string; date: string; status: string }

export function ReportFilters({ value, onChange, statuses = [] }: {
  value: ReportFilterValue
  onChange: (value: ReportFilterValue) => void
  statuses?: string[]
}) {
  const projects = useQuery({ queryKey: ['filter-projects'], queryFn: () => fetchReferenceList('/projects') })
  const sites = useQuery({
    queryKey: ['filter-sites', value.projectId],
    queryFn: async () => {
      if (!value.projectId) return fetchReferenceList('/sites')
      const result = await api.get(`/projects/${value.projectId}/sites`)
      return (result.data?.data || []).map((site: Record<string, unknown>) => ({ id: String(site.id), label: String(site.site_name) })) as { id: string; label: string }[]
    },
  })
  const set = (key: keyof ReportFilterValue, next: string) => onChange({ ...value, [key]: next, ...(key === 'projectId' ? { siteId: '' } : {}) })

  return <div className="toolbar report-filters">
    <select value={value.projectId} onChange={(event) => set('projectId', event.target.value)} aria-label="Filter by project">
      <option value="">All accessible projects</option>
      {(projects.data || []).map((project) => <option key={project.id} value={project.id}>{project.label}</option>)}
    </select>
    <select value={value.siteId} onChange={(event) => set('siteId', event.target.value)} aria-label="Filter by site">
      <option value="">All accessible sites</option>
      {(sites.data || []).map((site: { id: string; label: string }) => <option key={site.id} value={site.id}>{site.label}</option>)}
    </select>
    <input type="date" value={value.date} onChange={(event) => set('date', event.target.value)} aria-label="Filter by date" />
    {statuses.length > 0 && <select value={value.status} onChange={(event) => set('status', event.target.value)} aria-label="Filter by status">
      <option value="">All statuses</option>
      {statuses.map((status) => <option key={status} value={status}>{status.replaceAll('_', ' ')}</option>)}
    </select>}
    <button type="button" className="button secondary" onClick={() => onChange({ projectId: '', siteId: '', date: '', status: '' })}>
      <FilterX size={16} /> Clear
    </button>
  </div>
}
