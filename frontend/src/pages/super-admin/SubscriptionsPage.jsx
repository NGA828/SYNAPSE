import { useAsyncList } from '../../hooks/useAsyncList.js'
import { listSubscriptions } from '../../services/subscriptionService.js'
import { SuperAdminLayout } from '../../components/layout/SuperAdminLayout.jsx'
import { StatusBadge } from '../../components/super-admin/StatusBadge.jsx'
import { PageHeader } from '../../components/ui/PageHeader.jsx'
import { Card, CardBody } from '../../components/ui/Card.jsx'
import { DataTable } from '../../components/ui/DataTable.jsx'

export default function SubscriptionsPage() {
  const { data: subscriptions, loading, error } = useAsyncList(listSubscriptions)

  const columns = [
    { key: 'school', header: 'School', render: (s) => <span className="font-medium text-slate-800">{s.school?.name}</span> },
    { key: 'plan', header: 'Plan', render: (s) => <span className="text-slate-600">{s.plan?.name}</span> },
    { key: 'status', header: 'Status', render: (s) => <StatusBadge status={s.status} /> },
    { key: 'start_date', header: 'Start' },
    { key: 'end_date', header: 'End' },
    {
      key: 'amount',
      header: 'Amount',
      align: 'right',
      render: (s) => (
        <span className="text-slate-600">
          {s.amount} {s.currency}
        </span>
      ),
    },
  ]

  return (
    <SuperAdminLayout>
      <PageHeader title="Subscriptions" description="Subscription history across all schools." />

      <Card>
        <CardBody>
          <DataTable
            columns={columns}
            rows={subscriptions}
            loading={loading}
            emptyTitle="No subscriptions"
            emptyDescription="Subscriptions will appear here."
          />
          {error ? <p className="mt-3 text-sm text-slate-500">Could not load subscriptions.</p> : null}
        </CardBody>
      </Card>
    </SuperAdminLayout>
  )
}
