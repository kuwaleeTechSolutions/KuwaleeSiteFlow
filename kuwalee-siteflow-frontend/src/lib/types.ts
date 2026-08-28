export type Permission = string

export type Role = {
  id: string
  name: string
  slug: string
  description?: string | null
  is_system: boolean
  org_wide_visibility: boolean
  permissions?: { name: string; group: string; description?: string | null }[]
}

export type User = {
  id: string
  name: string
  email: string
  phone?: string | null
  status?: string
  roles?: Role[]
  permissions?: string[]
  organization?: { id: string; name: string } | null
  is_super_admin?: boolean
  last_login_at?: string | null
}

export type ApiEnvelope<T> = {
  success: boolean
  data: T
  message?: string
  meta?: { total?: number; current_page?: number; last_page?: number }
}

export type ProjectFinancial = {
  net_payable: string
  paid_amount: string
  outstanding_amount: string
  uncertified_bills: number
}

export type ProjectDashboard = {
  project: { id: string; project_code: string; project_name: string; status: string; contract_value: string }
  delivery: Record<string, number>
  financial: ProjectFinancial
  risk: Record<string, number>
}

export type Dashboard = {
  dashboard_type: 'organization' | 'assigned_projects' | 'assigned_sites'
  generated_at: string
  summary?: Record<string, number | string>
  projects?: ProjectDashboard[]
  sites?: { id: string; site_name: string; project: { id: string; project_name: string }; daily_reports_pending: number; measurements_pending: number }[]
}

export type EntityRecord = Record<string, unknown> & {
  id?: string
  uuid?: string
  status?: string
  created_at?: string
}
