import { Navigate, Route, Routes } from 'react-router-dom'
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

export default function App() {
  return (
    <Routes>
      <Route path="/login" element={<LoginPage />} />
      <Route element={<Protected />}>
        <Route index element={<RootRedirect />} />

        {/* Super Admin only */}
        <Route path="system/organizations" element={<SystemOrganizationsPage />} />

        {/* Dedicated pages — these have nested-resource or workflow needs
            the generic ModulePage cannot handle */}
        <Route path="projects" element={<ProjectsPage />} />
        <Route path="sites" element={<SitesPage />} />
        <Route path="boq" element={<BoqPage />} />
        <Route path="bills" element={<BillsPage />} />
        <Route path="daily-reports" element={<DailyReportsPage />} />
        <Route path="users" element={<UsersPage />} />
        <Route path="roles" element={<RolesPage />} />

        {/* Generic CRUD pages driven by features/modules.ts */}
        <Route path=":moduleKey" element={<ModulePage />} />
      </Route>
      <Route path="*" element={<Navigate to="/" replace />} />
    </Routes>
  )
}
