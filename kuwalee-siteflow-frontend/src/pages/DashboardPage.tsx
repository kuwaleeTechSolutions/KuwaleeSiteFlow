import { useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { AlertTriangle, Building2, ClipboardCheck, IndianRupee, Ruler, ShieldAlert, WalletCards } from 'lucide-react'
import { api } from '../lib/api'
import { demoDashboard } from '../lib/demo'
import type { ApiEnvelope, Dashboard, ProjectDashboard } from '../lib/types'
import { StatCard } from '../components/StatCard'
import { StatusPill } from '../components/StatusPill'
import { Modal } from '../components/Modal'
import { money, dateTime } from '../lib/format'

export function DashboardPage() {
  const demo = sessionStorage.getItem('siteflow_user')?.includes('demo-owner')
  const [detail, setDetail] = useState<ProjectDashboard | null>(null)

  const q = useQuery({
    queryKey: ['dashboard'],
    queryFn: async () => (demo ? demoDashboard : (await api.get<ApiEnvelope<Dashboard>>('/dashboard')).data.data),
  })

  const d = q.data || demoDashboard
  const s = d.summary || {}

  return (
    <>
      <div className="page-head">
        <div>
          <span className="eyebrow">Portfolio overview</span>
          <h1>Good morning</h1>
          <p>Track delivery, commercial performance and operational risks across your organisation.</p>
        </div>
        <div className="date-chip">Updated {dateTime(d.generated_at)}</div>
      </div>

      <div className="stats-grid">
        <StatCard label="Total projects" value={s.projects_total ?? d.projects?.length ?? 0} icon={Building2} />
        <StatCard label="Active projects" value={s.projects_active ?? 0} icon={ClipboardCheck} tone="green" />
        <StatCard label="Pending reports" value={s.pending_daily_report_reviews ?? 0} icon={AlertTriangle} tone="amber" />
        <StatCard label="Measurement approvals" value={s.pending_measurement_approvals ?? 0} icon={Ruler} tone="purple" />
        <StatCard label="Compliance alerts" value={s.compliance_expiring_or_expired ?? 0} icon={ShieldAlert} tone="red" />
      </div>

      <section className="dashboard-grid">
        <article className="panel span-2">
          <div className="panel-head">
            <div>
              <h2>Project portfolio</h2>
              <p>Delivery, financial and risk indicators. Click a project to see full detail.</p>
            </div>
          </div>
          <div className="project-list">
            {d.projects?.map((x) => (
              <button className="project-row" key={x.project.id} onClick={() => setDetail(x)}>
                <div className="project-name">
                  <span>{x.project.project_code}</span>
                  <strong>{x.project.project_name}</strong>
                  <StatusPill value={x.project.status} />
                </div>
                <div className="project-metrics">
                  <div>
                    <small>Contract value</small>
                    <b>{money(x.project.contract_value)}</b>
                  </div>
                  <div>
                    <small>Outstanding</small>
                    <b>{money(x.financial.outstanding_amount)}</b>
                  </div>
                  <div>
                    <small>Pending review</small>
                    <b>{x.delivery.daily_reports_pending_review ?? 0}</b>
                  </div>
                  <div>
                    <small>Risk alerts</small>
                    <b>{Number(x.risk.low_stock_items || 0) + Number(x.risk.compliance_expiring_or_expired || 0)}</b>
                  </div>
                </div>
              </button>
            ))}
          </div>
        </article>

        <article className="panel">
          <div className="panel-head">
            <div>
              <h2>Commercial snapshot</h2>
              <p>Across displayed projects</p>
            </div>
          </div>
          <div className="money-summary">
            <div>
              <IndianRupee />
              <span>Net payable</span>
              <strong>{money(d.projects?.reduce((a, p) => a + Number(p.financial.net_payable || 0), 0))}</strong>
            </div>
            <div>
              <WalletCards />
              <span>Outstanding</span>
              <strong>{money(d.projects?.reduce((a, p) => a + Number(p.financial.outstanding_amount || 0), 0))}</strong>
            </div>
          </div>
          <div className="alert-list">
            <h3>Attention required</h3>
            <p>
              <span className="dot red" />
              Compliance items need review
            </p>
            <p>
              <span className="dot amber" />
              Material levels below minimum
            </p>
            <p>
              <span className="dot blue" />
              Submitted reports awaiting approval
            </p>
          </div>
        </article>
      </section>

      {detail && (
        <Modal title={detail.project.project_name} onClose={() => setDetail(null)}>
          <div style={{ padding: 22 }}>
            <table className="data-table" style={{ minWidth: 'auto' }}>
              <tbody>
                <tr><td style={{ fontWeight: 600, width: 200 }}>Project code</td><td>{detail.project.project_code}</td></tr>
                <tr><td style={{ fontWeight: 600 }}>Status</td><td><StatusPill value={detail.project.status} /></td></tr>
                <tr><td style={{ fontWeight: 600 }}>Contract value</td><td>{money(detail.project.contract_value)}</td></tr>
                {Object.entries(detail.delivery).map(([k, v]) => (
                  <tr key={k}><td style={{ fontWeight: 600 }}>{k.replaceAll('_', ' ')}</td><td>{v}</td></tr>
                ))}
                {Object.entries(detail.financial).map(([k, v]) => (
                  <tr key={k}><td style={{ fontWeight: 600 }}>{k.replaceAll('_', ' ')}</td><td>{k.includes('amount') || k.includes('payable') ? money(v) : v}</td></tr>
                ))}
                {Object.entries(detail.risk).map(([k, v]) => (
                  <tr key={k}><td style={{ fontWeight: 600 }}>{k.replaceAll('_', ' ')}</td><td>{v}</td></tr>
                ))}
              </tbody>
            </table>
          </div>
        </Modal>
      )}
    </>
  )
}
