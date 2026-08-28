import axios, { AxiosError } from 'axios'

// IMPORTANT: this must point at the Laravel APP ROOT, not the /api prefix,
// because /sanctum/csrf-cookie lives OUTSIDE the /api route group.
export const API_ROOT = import.meta.env.VITE_API_ROOT_URL || 'http://127.0.0.1:8000'

export const api = axios.create({
  baseURL: import.meta.env.VITE_API_BASE_URL || `${API_ROOT}/api`,
  withCredentials: true,
  // Frontend (e.g. http://localhost:5173) and backend (http://127.0.0.1:8000)
  // are different origins to the browser. Axios only auto-attaches the
  // XSRF-TOKEN cookie as the X-XSRF-TOKEN header for SAME-origin requests
  // unless this flag is explicitly set. Without it, Laravel rejects
  // state-changing requests with "CSRF token mismatch" (419).
  withXSRFToken: true,
  headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
})

/**
 * Must be called once before the first login attempt (and again after any
 * full page reload / session expiry). Hits the Sanctum CSRF endpoint at the
 * APP ROOT — NOT through the `api` instance above, since that instance is
 * prefixed with /api and /sanctum/csrf-cookie is not under /api.
 */
export async function ensureCsrfCookie(): Promise<void> {
  await axios.get(`${API_ROOT}/sanctum/csrf-cookie`, { withCredentials: true })
}

api.interceptors.response.use(
  (response) => response,
  (error: AxiosError<{ message?: string; errors?: Record<string, string[]> }>) => {
    if (error.response?.status === 401) {
      sessionStorage.removeItem('siteflow_user')
    }
    const message = error.response?.data?.message || error.message || 'Request failed'
    return Promise.reject(new Error(message))
  },
)

/**
 * Fetches a lightweight list of records for populating dropdown selectors
 * (Projects, Workers, Materials, Equipment, etc.) rather than making the
 * user type a raw database ID by hand.
 *
 * Sites are a special case: there is no flat GET /api/sites endpoint on the
 * backend (sites are nested under their parent project), so this function
 * aggregates sites across every accessible project when asked for '/sites'.
 */
export async function fetchReferenceList(endpoint: string): Promise<{ id: string; label: string }[]> {
  if (endpoint === '/sites') {
    const projectsRes = await api.get('/projects')
    const projects = Array.isArray(projectsRes.data?.data) ? projectsRes.data.data : []
    const all: { id: string; label: string }[] = []
    for (const project of projects) {
      try {
        const sitesRes = await api.get(`/projects/${project.id}/sites`)
        const sites = Array.isArray(sitesRes.data?.data) ? sitesRes.data.data : []
        sites.forEach((s: Record<string, unknown>) =>
          all.push({ id: String(s.id), label: `${s.site_name} (${project.project_name})` }),
        )
      } catch {
        /* skip any project the user cannot access */
      }
    }
    return all
  }

  const res = await api.get(endpoint)
  const rows = Array.isArray(res.data?.data) ? res.data.data : []
  return rows.map((row: Record<string, unknown>) => ({
    id: String(row.id ?? row.uuid ?? ''),
    label: String(
      row.project_name ?? row.site_name ?? row.name ?? row.material_name ?? row.equipment_name ?? row.title ?? row.id ?? 'Unnamed',
    ),
  }))
}

/**
 * Downloads a binary response (PDF export, document download) and triggers
 * a browser save-as, reading the filename from Content-Disposition when the
 * backend provides one.
 */
export async function downloadFile(path: string, fallbackName: string): Promise<void> {
  const response = await api.get(path, { responseType: 'blob' })
  const disposition = response.headers['content-disposition'] as string | undefined
  const match = disposition?.match(/filename="?([^"]+)"?/)
  const filename = match?.[1] || fallbackName

  const blobUrl = window.URL.createObjectURL(response.data as Blob)
  const link = document.createElement('a')
  link.href = blobUrl
  link.download = filename
  document.body.appendChild(link)
  link.click()
  link.remove()
  window.URL.revokeObjectURL(blobUrl)
}
