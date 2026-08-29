import { NavLink, Outlet, useLocation } from 'react-router-dom'
import { useState } from 'react'
import * as Icons from 'lucide-react'
import { useAuth } from '../lib/auth'

type NavItem = { path: string; title: string; icon: string; permission?: string }
type NavGroup = { name: string; items: NavItem[] }

const tenantGroups: NavGroup[] = [
  { name: 'Overview', items: [{ path: '/', title: 'Dashboard', icon: 'LayoutDashboard' }] },
  {
    name: 'Field operations',
    items: [
      { path: '/projects', title: 'Projects', icon: 'Building2', permission: 'projects.view' },
      { path: '/sites', title: 'Sites', icon: 'MapPinned', permission: 'sites.view' },
      { path: '/daily-reports', title: 'Daily Reports', icon: 'ClipboardList', permission: 'daily_reports.view' },
      { path: '/workers', title: 'Workers', icon: 'Users', permission: 'labour.view' },
      { path: '/attendance', title: 'Attendance', icon: 'CalendarCheck', permission: 'labour.view' },
    ],
  },
  {
    name: 'Resources',
    items: [
      { path: '/materials', title: 'Materials', icon: 'Boxes', permission: 'materials.view' },
      { path: '/material-transactions', title: 'Material Transactions', icon: 'ArrowLeftRight', permission: 'materials.view' },
      { path: '/equipment', title: 'Equipment', icon: 'Truck', permission: 'equipment.view' },
      { path: '/equipment-usage-logs', title: 'Equipment Usage', icon: 'Gauge', permission: 'equipment.view' },
      { path: '/fuel-transactions', title: 'Fuel', icon: 'Fuel', permission: 'fuel.view' },
    ],
  },
  {
    name: 'Commercial',
    items: [
      { path: '/boq', title: 'BOQ', icon: 'ListChecks', permission: 'billing.view' },
      { path: '/measurements', title: 'Measurements', icon: 'Ruler', permission: 'measurements.view' },
      { path: '/bills', title: 'Bills', icon: 'ReceiptIndianRupee', permission: 'billing.view' },
    ],
  },
  {
    name: 'Governance',
    items: [
      { path: '/documents', title: 'Documents', icon: 'FolderLock', permission: 'documents.view' },
      { path: '/compliance-items', title: 'Compliance', icon: 'ShieldCheck', permission: 'compliance.view' },
    ],
  },
  {
    name: 'Administration',
    items: [
      { path: '/users', title: 'Users', icon: 'UserCog', permission: 'users.view' },
      { path: '/roles', title: 'Roles', icon: 'KeySquare', permission: 'roles.view' },
    ],
  },
]

const superAdminGroups: NavGroup[] = [
  { name: 'System administration', items: [{ path: '/system/organizations', title: 'Organizations', icon: 'Building2' }] },
]

export function AppShell() {
  const [open, setOpen] = useState(false)
  const { user, logout } = useAuth()
  const location = useLocation()
  // Super Admin accounts are architecturally blocked from every tenant
  // route by the backend's EnsureOrganizationContext middleware — show
  // them ONLY the system administration menu.
  const groups = user?.is_super_admin ? superAdminGroups : tenantGroups
  const can = (permission?: string) => !permission || user?.permissions?.includes(permission)

  return (
    <div className="app-shell">
      <aside className={open ? 'sidebar open' : 'sidebar'}>
        <div className="brand">
          <div className="brand-mark">K</div>
          <div>
            <strong>Kuwalee</strong>
            <span>SiteFlow</span>
          </div>
          <button className="mobile-close" onClick={() => setOpen(false)} aria-label="Close">
            <Icons.X />
          </button>
        </div>
        <nav>
          {groups.map((g) => {
            const visibleItems = g.items.filter((item) => can(item.permission))
            if (visibleItems.length === 0) return null
            return (
            <section key={g.name}>
              <p>{g.name}</p>
              {visibleItems.map((item) => {
                const Icon = (Icons as unknown as Record<string, typeof Icons.Box>)[item.icon] || Icons.Box
                return (
                  <NavLink key={item.path} to={item.path} end={item.path === '/'} onClick={() => setOpen(false)}>
                    <Icon size={18} />
                    <span>{item.title}</span>
                  </NavLink>
                )
              })}
            </section>
          )})}
        </nav>
        <div className="sidebar-footer">
          <Icons.ShieldCheck size={16} />
          <span>{user?.is_super_admin ? 'System administrator workspace' : 'Secure organisation workspace'}</span>
        </div>
      </aside>
      {open && <div className="scrim" onClick={() => setOpen(false)} />}
      <main className="main-area">
        <header className="topbar">
          <button className="menu-button" onClick={() => setOpen(true)}>
            <Icons.Menu />
          </button>
          <div className="crumb">
            <span>SiteFlow</span>
            <b>/</b>
            <strong>{location.pathname === '/' ? 'Dashboard' : location.pathname.split('/')[1].replaceAll('-', ' ')}</strong>
          </div>
          <div className="top-actions">
            <button className="icon-button" title="Notifications">
              <Icons.Bell />
            </button>
            <div className="user-menu">
              <div className="avatar">{user?.name?.slice(0, 1)}</div>
              <div>
                <strong>{user?.name}</strong>
                <span>{user?.is_super_admin ? 'Super Admin' : user?.roles?.[0]?.name || 'User'}</span>
              </div>
              <button className="icon-button" onClick={logout} title="Sign out">
                <Icons.LogOut />
              </button>
            </div>
          </div>
        </header>
        <div className="page-container">
          <Outlet />
        </div>
      </main>
    </div>
  )
}
