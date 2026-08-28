import type { Dashboard, EntityRecord, User } from './types'

export const demoUser: User = {
  id: 'demo-owner',
  name: 'Demo Owner',
  email: 'owner@siteflow.demo',
  organization: { id: 'org-demo', name: 'Kuwalee Infrastructure' },
  permissions: ['*'],
  is_super_admin: false,
  roles: [{ id: 'r1', name: 'Organization Owner', slug: 'owner', is_system: true, org_wide_visibility: true }],
}

export const demoDashboard: Dashboard = {
  dashboard_type: 'organization',
  generated_at: new Date().toISOString(),
  summary: {
    projects_total: 6,
    projects_active: 4,
    pending_daily_report_reviews: 7,
    pending_measurement_approvals: 3,
    compliance_expiring_or_expired: 5,
  },
  projects: [
    {
      project: { id: 'p1', project_code: 'NH-17-A', project_name: 'NH-17 Widening Package A', status: 'active', contract_value: '485000000.00' },
      delivery: { sites: 4, daily_reports_total: 128, daily_reports_pending_review: 3, measurements_pending_approval: 1 },
      financial: { net_payable: '64850000.00', paid_amount: '52000000.00', outstanding_amount: '12850000.00', uncertified_bills: 2 },
      risk: { low_stock_items: 4, compliance_expiring_or_expired: 2 },
    },
    {
      project: { id: 'p2', project_code: 'BR-06', project_name: 'Brahmaputra Approach Road', status: 'active', contract_value: '296000000.00' },
      delivery: { sites: 3, daily_reports_total: 86, daily_reports_pending_review: 2, measurements_pending_approval: 2 },
      financial: { net_payable: '41300000.00', paid_amount: '35500000.00', outstanding_amount: '5800000.00', uncertified_bills: 1 },
      risk: { low_stock_items: 2, compliance_expiring_or_expired: 1 },
    },
  ],
}

export const demoRows: Record<string, EntityRecord[]> = {
  projects: [
    { id: 'p1', project_code: 'NH-17-A', project_name: 'NH-17 Widening Package A', client_name: 'Public Works Department', status: 'active', contract_value: '485000000.00' },
    { id: 'p2', project_code: 'BR-06', project_name: 'Brahmaputra Approach Road', client_name: 'Infrastructure Division', status: 'active', contract_value: '296000000.00' },
  ],
  workers: [
    { id: 'w1', worker_code: 'WK-101', name: 'Worker 101', trade: 'Mason', daily_wage: '850.00', status: 'active' },
    { id: 'w2', worker_code: 'WK-102', name: 'Worker 102', trade: 'Equipment Operator', daily_wage: '1100.00', status: 'active' },
  ],
  attendance: [
    { id: 'a1', attendance_date: '2026-08-25', worker_name: 'Worker 101', shift: 'day', status: 'present' },
    { id: 'a2', attendance_date: '2026-08-25', worker_name: 'Worker 102', shift: 'day', status: 'half_day' },
  ],
  materials: [
    { id: 'm1', material_code: 'MAT-001', material_name: 'OPC 53 Grade Cement', unit: 'bags', minimum_stock: '200.000', status: 'active' },
    { id: 'm2', material_code: 'MAT-002', material_name: 'TMT Steel 12 mm', unit: 'kg', minimum_stock: '1500.000', status: 'active' },
  ],
  'material-transactions': [
    { id: 'mt1', transaction_type: 'inward', material_name: 'OPC 53 Grade Cement', quantity: '500.000', site_name: 'Chainage 0+000 to 12+500', created_at: '2026-08-25' },
    { id: 'mt2', transaction_type: 'issue', material_name: 'TMT Steel 12 mm', quantity: '350.000', site_name: 'Bridge Approach East', created_at: '2026-08-25' },
  ],
  equipment: [
    { id: 'e1', equipment_code: 'EQ-001', equipment_name: 'Excavator 01', type: 'Excavator', status: 'in_use' },
    { id: 'e2', equipment_code: 'EQ-002', equipment_name: 'Diesel Generator 02', type: 'Generator', status: 'available' },
  ],
  'equipment-usage-logs': [
    { id: 'eu1', usage_date: '2026-08-25', equipment_name: 'Excavator 01', hours_used: '7.50', site_name: 'Chainage 0+000 to 12+500' },
  ],
  'fuel-transactions': [
    { id: 'f1', transaction_type: 'issue', equipment_name: 'Excavator 01', quantity: '65.00', opening_reading: '1200.00', closing_reading: '1268.00', status: 'draft' },
  ],
  measurements: [
    { id: 'me1', measurement_date: '2026-08-24', site_name: 'Chainage 0+000 to 12+500', status: 'submitted', items_count: 4 },
    { id: 'me2', measurement_date: '2026-08-20', site_name: 'Bridge Approach East', status: 'approved', items_count: 3 },
  ],
  documents: [
    { id: 'd1', title: 'Contract Agreement', category: 'contract', confidentiality_level: 'management_only', original_filename: 'contract-agreement.pdf', created_at: '2026-08-20' },
    { id: 'd2', title: 'Approved GAD Drawing', category: 'drawing', confidentiality_level: 'project', original_filename: 'gad-rev3.pdf', created_at: '2026-08-24' },
  ],
  'compliance-items': [
    { id: 'c1', title: 'Contractor All Risk Insurance', type: 'insurance', expiry_date: '2026-09-15', days_until_expiry: 20, status: 'expiring' },
    { id: 'c2', title: 'Crane Fitness Certificate', type: 'equipment_certificate', expiry_date: '2026-08-22', days_until_expiry: -3, status: 'expired' },
  ],
  users: [
    { id: 'u1', name: 'Demo Owner', email: 'owner@siteflow.demo', status: 'active' },
    { id: 'u2', name: 'Site Supervisor Demo', email: 'supervisor@siteflow.demo', status: 'active' },
  ],
  roles: [
    { id: 'r1', name: 'Organization Owner', slug: 'owner', is_system: true, org_wide_visibility: true },
    { id: 'r2', name: 'Site Engineer / Site Supervisor', slug: 'site_supervisor', is_system: true, org_wide_visibility: false },
  ],
}

export const demoSites: EntityRecord[] = [
  { id: 's1', site_name: 'Chainage 0+000 to 12+500', location: 'NH-17', status: 'active' },
  { id: 's2', site_name: 'Bridge Approach East', location: 'Brahmaputra crossing', status: 'active' },
]

export const demoBoq: EntityRecord[] = [
  { id: 'bq1', item_number: '1.01', description: 'Earthwork excavation in ordinary soil', unit: 'cum', contract_quantity: '12000.000', contract_rate: '245.00', completed_quantity: '5340.000', percentage_progress: '44.50' },
]

export const demoBills: EntityRecord[] = [
  { id: 'bl1', bill_number: 'RA-004', bill_type: 'running', bill_date: '2026-08-25', net_payable: '12450000.00', paid_amount: '10000000.00', outstanding_amount: '2450000.00', status: 'partially_paid' },
]

export const demoPayments: EntityRecord[] = [
  { id: 'pa1', payment_reference: 'UTR-894321', payment_date: '2026-08-22', amount: '10000000.00', payment_mode: 'Bank Transfer' },
]
