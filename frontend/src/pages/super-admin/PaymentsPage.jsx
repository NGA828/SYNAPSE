import { useAsyncList } from '../../hooks/useAsyncList.js'
import { listPayments } from '../../services/subscriptionService.js'
import { SuperAdminLayout } from '../../components/layout/SuperAdminLayout.jsx'
import { StatusBadge } from '../../components/super-admin/StatusBadge.jsx'
import { PageHeader } from '../../components/ui/PageHeader.jsx'
import { Badge } from '../../components/ui/Badge.jsx'
import { Card, CardBody } from '../../components/ui/Card.jsx'
import { DataTable } from '../../components/ui/DataTable.jsx'

export default function PaymentsPage() {
  const { data: payments, loading, error } = useAsyncList(listPayments)

  const columns = [
    { key: 'school', header: 'School', render: (p) => <span className="font-medium text-slate-800">{p.school?.name}</span> },
    {
      key: 'provider',
      header: 'Provider',
      render: (p) => (
        <span className="inline-flex items-center gap-1.5 text-slate-600">
          {p.provider}
          {p.sandbox ? <Badge variant="neutral">sandbox</Badge> : null}
        </span>
      ),
    },
    {
      key: 'amount',
      header: 'Amount',
      align: 'right',
      render: (p) => (
        <span className="text-slate-600">
          {p.amount} {p.currency}
        </span>
      ),
    },
    { key: 'status', header: 'Status', render: (p) => <StatusBadge status={p.status} /> },
    { key: 'reference', header: 'Reference', cellClassName: 'font-mono text-xs text-slate-500' },
  ]

  return (
    <SuperAdminLayout>
      <PageHeader title="Payments" description="Payment records across the platform." />

      <Card>
        <CardBody>
          <DataTable
            columns={columns}
            rows={payments}
            loading={loading}
            emptyTitle="No payments"
            emptyDescription="Payments will appear here."
          />
          {error ? <p className="mt-3 text-sm text-slate-500">Could not load payments.</p> : null}
        </CardBody>
      </Card>
    </SuperAdminLayout>
  )
}
