/**
 * Mirrors backend/app/Support/PermissionCatalogue.php exactly. The backend
 * validates any submitted permission name against this exact list (via
 * Rule::in(PermissionCatalogue::flat())), so this must stay in sync with
 * the backend source of truth if new permissions are ever added there.
 */
export const permissionCatalogue: Record<string, string[]> = {
  projects: ['projects.view', 'projects.create', 'projects.update', 'projects.delete'],
  sites: ['sites.view', 'sites.create', 'sites.update', 'sites.delete'],
  daily_reports: ['daily_reports.view', 'daily_reports.create', 'daily_reports.update', 'daily_reports.approve', 'daily_reports.delete'],
  materials: ['materials.view', 'materials.create', 'materials.update', 'materials.issue', 'materials.transfer', 'materials.delete', 'materials.negative_stock_override'],
  fuel: ['fuel.view', 'fuel.create', 'fuel.update', 'fuel.approve'],
  equipment: ['equipment.view', 'equipment.create', 'equipment.update', 'equipment.delete', 'equipment.log_usage'],
  labour: ['labour.view', 'labour.create', 'labour.update', 'labour.attendance', 'labour.wages'],
  measurements: ['measurements.view', 'measurements.create', 'measurements.update', 'measurements.approve'],
  billing: ['billing.view', 'billing.create', 'billing.update', 'billing.approve'],
  payments: ['payments.view', 'payments.create', 'payments.update'],
  documents: ['documents.view', 'documents.upload', 'documents.download', 'documents.delete', 'documents.share'],
  compliance: ['compliance.view', 'compliance.create', 'compliance.update', 'compliance.delete'],
  users: ['users.view', 'users.create', 'users.update', 'users.delete'],
  roles: ['roles.view', 'roles.create', 'roles.update', 'roles.delete'],
  audit_logs: ['audit_logs.view'],
  organization: ['organization.manage'],
}
