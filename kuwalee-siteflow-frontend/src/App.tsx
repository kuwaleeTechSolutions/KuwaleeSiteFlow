import { Navigate, Route, Routes } from 'react-router-dom'
import type { ReactNode } from 'react'
import { useAuth } from './lib/auth'
import { AppShell } from './components/AppShell'
import { LoginPage } from './pages/LoginPage'
import { DashboardPage } from './pages/DashboardPage'
import { ModulePage } from './pages/ModulePage'
import { ProjectsPage } from './pages/ProjectsPage'
import { SitesPage } from './pages/SitesPage'
import { BoqPage } from './pages/BoqPage'
import { BillsPage } from './pages/BillsPage'
import { DailyReportsPage } from './pages/DailyReportsPage'
import { UsersPage } from './pages/UsersPage'
import { RolesPage } from './pages/RolesPage'
import { SystemOrganizationsPage } from './pages/SystemOrganizationsPage'

function Protected() {
  const { user } = useAuth()
  return user ? <AppShell /> : <Navigate to="/login" replace />
}

function RootRedirect() {
  const { user } = useAuth()
  if (user?.is_super_admin) return <Navigate to="/system/organizations" replace />
  return <DashboardPage />
}

function TenantOnly({ children }: { children: ReactNode }) {
  const { user } = useAuth()
  return user?.is_super_admin ? <Navigate to="/system/organizations" replace /> : <>{children}</>
}

function SuperAdminOnly({ children }: { children: ReactNode }) {
  const { user } = useAuth()
  return user?.is_super_admin ? <>{children}</> : <Navigate to="/" replace />
}

export default function App() {
  return (
    <Routes>
      <Route path="/login" element={<LoginPage />} />
      <Route element={<Protected />}>
        <Route index element={<RootRedirect />} />

        {/* Super Admin only */}
        <Route path="system/organizations" element={<SuperAdminOnly><SystemOrganizationsPage /></SuperAdminOnly>} />

        {/* Dedicated pages — these have nested-resource or workflow needs
            the generic ModulePage cannot handle */}
        <Route path="projects" element={<TenantOnly><ProjectsPage /></TenantOnly>} />
        <Route path="sites" element={<TenantOnly><SitesPage /></TenantOnly>} />
        <Route path="boq" element={<TenantOnly><BoqPage /></TenantOnly>} />
        <Route path="bills" element={<TenantOnly><BillsPage /></TenantOnly>} />
        <Route path="daily-reports" element={<TenantOnly><DailyReportsPage /></TenantOnly>} />
        <Route path="users" element={<TenantOnly><UsersPage /></TenantOnly>} />
        <Route path="roles" element={<TenantOnly><RolesPage /></TenantOnly>} />

        {/* Generic CRUD pages driven by features/modules.ts */}
        <Route path=":moduleKey" element={<TenantOnly><ModulePage /></TenantOnly>} />
      </Route>
      <Route path="*" element={<Navigate to="/" replace />} />
    </Routes>
  )
}
