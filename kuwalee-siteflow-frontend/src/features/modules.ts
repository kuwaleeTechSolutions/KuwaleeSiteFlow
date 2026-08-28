import type { Field } from '../components/EntityForm'

export type RowAction = {
  key: string
  label: string
  icon: string
  // 'simple' posts an empty body. 'remarks' opens a dialog collecting a
  // required text reason first (review_remarks) before posting it.
  // 'download' streams a binary file (PDF/document) via blob download.
  kind: 'simple' | 'remarks' | 'download'
  method: 'post' | 'get'
  path: (row: Record<string, unknown>) => string
  successMessage?: string
  downloadName?: (row: Record<string, unknown>) => string
  // Only show this action when the row's status matches one of these
  // values — avoids offering "Approve" on an already-approved record, etc.
  visibleWhenStatus?: string[]
}

export type ModuleConfig = {
  key: string
  title: string
  description: string
  endpoint: string
  columns: { key: string; label: string; format?: 'status' | 'money' | 'date' }[]
  fields: Field[]
  createLabel: string
  icon: string
  rowActions?: RowAction[]
  // Set false to hide the generic Delete action entirely — several backend
  // resources (Measurement, Bill, BoqItem) deliberately have no delete
  // route at all, by design, since they are financial/approval records.
  allowDelete?: boolean
}

const commonStatus = ['active', 'inactive']

export const modules: ModuleConfig[] = [
  {
    key: 'workers',
    title: 'Workers',
    description: 'Maintain workforce master records and wage rates.',
    endpoint: '/workers',
    createLabel: 'Add worker',
    icon: 'Users',
    allowDelete: false,
    columns: [
      { key: 'worker_code', label: 'Code' },
      { key: 'name', label: 'Worker' },
      { key: 'trade', label: 'Trade' },
      { key: 'daily_wage', label: 'Daily wage', format: 'money' },
      { key: 'status', label: 'Status', format: 'status' },
    ],
    fields: [
      { name: 'worker_code', label: 'Worker code', required: true },
      { name: 'name', label: 'Name', required: true },
      { name: 'phone', label: 'Phone' },
      { name: 'trade', label: 'Trade' },
      { name: 'skill_category', label: 'Skill category' },
      { name: 'daily_wage', label: 'Daily wage', type: 'number', step: '0.01', required: true },
      { name: 'joining_date', label: 'Joining date', type: 'date' },
      { name: 'status', label: 'Status', type: 'select', options: commonStatus },
    ],
  },
  {
    key: 'attendance',
    title: 'Attendance',
    description: 'Capture worker attendance, shifts and overtime.',
    endpoint: '/attendance',
    createLabel: 'Mark attendance',
    icon: 'CalendarCheck',
    allowDelete: false,
    columns: [
      { key: 'attendance_date', label: 'Date', format: 'date' },
      { key: 'worker_name', label: 'Worker' },
      { key: 'shift', label: 'Shift' },
      { key: 'status', label: 'Status', format: 'status' },
    ],
    fields: [
      { name: 'worker_id', label: 'Worker', type: 'reference', referenceEndpoint: '/workers', required: true },
      { name: 'site_id', label: 'Site', type: 'reference', referenceEndpoint: '/sites', required: true },
      { name: 'attendance_date', label: 'Date', type: 'date', required: true },
      { name: 'shift', label: 'Shift', type: 'select', options: ['day', 'night'], required: true },
      { name: 'status', label: 'Status', type: 'select', options: ['present', 'absent', 'half_day'], required: true },
      { name: 'check_in', label: 'Check-in (HH:MM)', placeholder: '08:00' },
      { name: 'check_out', label: 'Check-out (HH:MM)', placeholder: '17:00' },
      { name: 'overtime_hours', label: 'Overtime hours', type: 'number', step: '0.01' },
    ],
  },
  {
    key: 'materials',
    title: 'Materials',
    description: 'Manage the material catalogue and minimum stock levels.',
    endpoint: '/materials',
    createLabel: 'Create material',
    icon: 'Boxes',
    allowDelete: false,
    columns: [
      { key: 'material_code', label: 'Code' },
      { key: 'material_name', label: 'Material' },
      { key: 'unit', label: 'Unit' },
      { key: 'minimum_stock', label: 'Minimum stock' },
      { key: 'status', label: 'Status', format: 'status' },
    ],
    fields: [
      { name: 'material_code', label: 'Material code', required: true },
      { name: 'material_name', label: 'Material name', required: true },
      { name: 'category', label: 'Category' },
      { name: 'unit', label: 'Unit', required: true },
      { name: 'minimum_stock', label: 'Minimum stock', type: 'number', step: '0.001' },
    ],
  },
  {
    key: 'material-transactions',
    title: 'Material Transactions',
    description: 'Record inward, issue, return, transfer and adjustment movements.',
    endpoint: '/material-transactions',
    createLabel: 'Record transaction',
    icon: 'ArrowLeftRight',
    allowDelete: false,
    columns: [
      { key: 'created_at', label: 'Date', format: 'date' },
      { key: 'transaction_type', label: 'Type', format: 'status' },
      { key: 'material_name', label: 'Material' },
      { key: 'quantity', label: 'Quantity' },
      { key: 'site_name', label: 'Site' },
    ],
    fields: [
      { name: 'material_id', label: 'Material', type: 'reference', referenceEndpoint: '/materials', required: true },
      { name: 'site_id', label: 'Site', type: 'reference', referenceEndpoint: '/sites', required: true },
      { name: 'transaction_type', label: 'Type', type: 'select', options: ['inward', 'issue', 'return', 'transfer', 'adjustment'], required: true },
      { name: 'quantity', label: 'Quantity', type: 'number', step: '0.001', required: true },
      { name: 'to_site_id', label: 'Destination site (transfer only)', type: 'reference', referenceEndpoint: '/sites' },
      { name: 'direction', label: 'Direction (adjustment only)', type: 'select', options: ['increase', 'decrease'] },
      { name: 'reference_number', label: 'Reference number' },
      { name: 'remarks', label: 'Remarks', type: 'textarea' },
    ],
  },
  {
    key: 'equipment',
    title: 'Equipment',
    description: 'Maintain fleet details, assignments and operational status.',
    endpoint: '/equipment',
    createLabel: 'Add equipment',
    icon: 'Truck',
    columns: [
      { key: 'equipment_code', label: 'Code' },
      { key: 'equipment_name', label: 'Equipment' },
      { key: 'type', label: 'Type' },
      { key: 'status', label: 'Status', format: 'status' },
    ],
    fields: [
      { name: 'equipment_code', label: 'Equipment code', required: true },
      { name: 'equipment_name', label: 'Name', required: true },
      { name: 'type', label: 'Type' },
      { name: 'registration_number', label: 'Registration number' },
      { name: 'status', label: 'Status', type: 'select', options: ['available', 'in_use', 'maintenance', 'breakdown', 'inactive'] },
    ],
  },
  {
    key: 'equipment-usage-logs',
    title: 'Equipment Usage',
    description: 'Log daily equipment hours, locations and operators.',
    endpoint: '/equipment-usage-logs',
    createLabel: 'Log usage',
    icon: 'Gauge',
    allowDelete: false,
    columns: [
      { key: 'usage_date', label: 'Date', format: 'date' },
      { key: 'equipment_name', label: 'Equipment' },
      { key: 'hours_used', label: 'Hours' },
      { key: 'site_name', label: 'Site' },
    ],
    fields: [
      { name: 'equipment_id', label: 'Equipment', type: 'reference', referenceEndpoint: '/equipment', required: true },
      { name: 'site_id', label: 'Site', type: 'reference', referenceEndpoint: '/sites', required: true },
      { name: 'usage_date', label: 'Usage date', type: 'date', required: true },
      { name: 'hours_used', label: 'Hours used', type: 'number', step: '0.01', required: true },
      { name: 'remarks', label: 'Remarks', type: 'textarea' },
    ],
  },
  {
    key: 'fuel-transactions',
    title: 'Fuel',
    description: 'Track bulk purchases, equipment issues, readings and cost.',
    endpoint: '/fuel-transactions',
    createLabel: 'Record fuel',
    icon: 'Fuel',
    allowDelete: false,
    columns: [
      { key: 'transaction_type', label: 'Type', format: 'status' },
      { key: 'equipment_name', label: 'Equipment' },
      { key: 'quantity', label: 'Quantity' },
      { key: 'opening_reading', label: 'Opening' },
      { key: 'closing_reading', label: 'Closing' },
      { key: 'status', label: 'Status', format: 'status' },
    ],
    fields: [
      { name: 'transaction_type', label: 'Type', type: 'select', options: ['purchase', 'issue'], required: true },
      { name: 'site_id', label: 'Site', type: 'reference', referenceEndpoint: '/sites', required: true },
      { name: 'equipment_id', label: 'Equipment (required for issue)', type: 'reference', referenceEndpoint: '/equipment' },
      { name: 'quantity', label: 'Quantity', type: 'number', step: '0.01', required: true },
      { name: 'opening_reading', label: 'Opening reading', type: 'number', step: '0.01' },
      { name: 'closing_reading', label: 'Closing reading', type: 'number', step: '0.01' },
      { name: 'unit_cost', label: 'Unit cost', type: 'number', step: '0.01' },
      { name: 'remarks', label: 'Remarks', type: 'textarea' },
    ],
    rowActions: [
      {
        key: 'review',
        label: 'Mark reviewed',
        icon: 'CheckCircle2',
        kind: 'simple',
        method: 'post',
        path: (row) => `/fuel-transactions/${row.id}/review`,
        successMessage: 'Fuel transaction reviewed.',
      },
    ],
  },
  {
    key: 'measurements',
    title: 'Measurements',
    description: 'Prepare, submit and approve Measurement Book entries.',
    endpoint: '/measurements',
    createLabel: 'Create measurement',
    icon: 'Ruler',
    allowDelete: false,
    columns: [
      { key: 'measurement_date', label: 'Date', format: 'date' },
      { key: 'site_name', label: 'Site' },
      { key: 'status', label: 'Status', format: 'status' },
    ],
    fields: [
      { name: 'site_id', label: 'Site', type: 'reference', referenceEndpoint: '/sites', required: true },
      { name: 'measurement_date', label: 'Date', type: 'date', required: true },
      { name: 'boq_item_id', label: 'BOQ item ID (see BOQ page for the ID)', required: true },
      { name: 'current_quantity', label: 'Current quantity', type: 'number', step: '0.001', required: true },
      { name: 'remarks', label: 'Remarks', type: 'textarea' },
    ],
    rowActions: [
      {
        key: 'submit',
        label: 'Submit for approval',
        icon: 'Send',
        kind: 'simple',
        method: 'post',
        path: (row) => `/measurements/${row.id}/submit`,
        successMessage: 'Measurement submitted for approval.',
        visibleWhenStatus: ['draft'],
      },
      {
        key: 'approve',
        label: 'Approve',
        icon: 'CheckCircle2',
        kind: 'simple',
        method: 'post',
        path: (row) => `/measurements/${row.id}/approve`,
        successMessage: 'Measurement approved.',
        visibleWhenStatus: ['submitted'],
      },
      {
        key: 'reject',
        label: 'Reject',
        icon: 'XCircle',
        kind: 'remarks',
        method: 'post',
        path: (row) => `/measurements/${row.id}/reject`,
        successMessage: 'Measurement rejected.',
        visibleWhenStatus: ['submitted'],
      },
      {
        key: 'pdf',
        label: 'Export PDF',
        icon: 'FileDown',
        kind: 'download',
        method: 'get',
        path: (row) => `/measurements/${row.id}/pdf`,
        downloadName: (row) => `measurement-${row.id}.pdf`,
        visibleWhenStatus: ['approved'],
      },
    ],
  },
  {
    key: 'documents',
    title: 'Documents',
    description: 'Securely upload, classify, share and download project records.',
    endpoint: '/documents',
    createLabel: 'Upload document',
    icon: 'FolderLock',
    columns: [
      { key: 'title', label: 'Title' },
      { key: 'category', label: 'Category' },
      { key: 'confidentiality_level', label: 'Confidentiality', format: 'status' },
      { key: 'original_filename', label: 'File' },
      { key: 'created_at', label: 'Uploaded', format: 'date' },
    ],
    fields: [
      { name: 'file', label: 'File', type: 'file', required: true },
      {
        name: 'category',
        label: 'Category',
        type: 'select',
        required: true,
        options: ['contract', 'work_order', 'purchase_order', 'drawing', 'boq_document', 'invoice', 'bill', 'certificate', 'insurance', 'labour', 'equipment', 'compliance', 'other'],
      },
      { name: 'title', label: 'Title', required: true },
      { name: 'description', label: 'Description', type: 'textarea' },
      {
        name: 'confidentiality_level',
        label: 'Confidentiality',
        type: 'select',
        required: true,
        options: ['organization', 'project', 'restricted', 'management_only'],
      },
      { name: 'expiry_date', label: 'Expiry date', type: 'date' },
    ],
    rowActions: [
      {
        key: 'download',
        label: 'Download',
        icon: 'Download',
        kind: 'download',
        method: 'get',
        path: (row) => `/documents/${row.id}/download`,
        downloadName: (row) => String(row.original_filename || 'document'),
      },
    ],
  },
  {
    key: 'compliance-items',
    title: 'Compliance',
    description: 'Monitor licences, insurance, certificates and expiry alerts.',
    endpoint: '/compliance-items',
    createLabel: 'Create compliance item',
    icon: 'ShieldCheck',
    columns: [
      { key: 'title', label: 'Item' },
      { key: 'type', label: 'Type' },
      { key: 'expiry_date', label: 'Expiry', format: 'date' },
      { key: 'days_until_expiry', label: 'Days remaining' },
      { key: 'status', label: 'Status', format: 'status' },
    ],
    fields: [
      { name: 'title', label: 'Title', required: true },
      {
        name: 'type',
        label: 'Type',
        type: 'select',
        required: true,
        options: ['insurance', 'labour_licence', 'equipment_certificate', 'calibration', 'vehicle_document', 'other'],
      },
      { name: 'issue_date', label: 'Issue date', type: 'date' },
      { name: 'expiry_date', label: 'Expiry date', type: 'date', required: true },
      { name: 'responsible_person_id', label: 'Responsible person', type: 'reference', referenceEndpoint: '/users' },
    ],
  },
]

export const moduleMap = Object.fromEntries(modules.map((m) => [m.key, m]))
