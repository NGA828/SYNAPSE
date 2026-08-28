import { History } from 'lucide-react'
import { usePaginatedList } from '../../hooks/usePaginatedList.js'
import { listAuditLogs } from '../../services/auditService.js'
import { PageContainer } from '../../components/layout/PageContainer.jsx'
import { PageHeader } from '../../components/ui/PageHeader.jsx'
import { Card, CardBody, CardHeader } from '../../components/ui/Card.jsx'
import { DataTable } from '../../components/ui/DataTable.jsx'
import { Pagination } from '../../components/ui/Pagination.jsx'
import { SearchInput } from '../../components/ui/SearchInput.jsx'
import { Badge } from '../../components/ui/Badge.jsx'
import { EmptyState } from '../../components/dashboard/EmptyState.jsx'
import { formatDateTime } from '../../utils/formatters.js'

const TONE = {
  created: 'success',
  updated: 'info',
  deleted: 'danger',
  reset: 'warning',
  changed: 'warning',
}

function actionTone(action = '') {
  const verb = action.split('.').pop()
  return TONE[verb] ?? 'neutral'
}

export default function AuditLogPage() {
  const { rows, meta, page, setPage, search, setSearch, loading, error } = usePaginatedList(listAuditLogs, {
    perPage: 25,
  })

  const columns = [
    {
      key: 'created_at',
      header: 'When',
      render: (row) => <span className="whitespace-nowrap text-slate-600">{formatDateTime(row.created_at)}</span>,
    },
    {
      key: 'action',
      header: 'Action',
      render: (row) => <Badge variant={actionTone(row.action)}>{row.action}</Badge>,
    },
    {
      key: 'entity',
      header: 'Entity',
      render: (row) => (
        <span className="text-slate-700">
          {row.entity_type}
          {row.entity_id ? <span className="text-slate-400"> #{row.entity_id}</span> : null}
        </span>
      ),
    },
    {
      key: 'user',
      header: 'By',
      render: (row) =>
        row.user?.name ? (
          <span className="text-slate-700">
            {row.user.name} <span className="text-xs text-slate-400">({row.user.role})</span>
          </span>
        ) : (
          <span className="text-slate-400">system</span>
        ),
    },
  ]

  return (
    <PageContainer>
      <div className="space-y-6">
        <PageHeader
          title="Audit trail"
          description="Every create, update and delete recorded for your school."
        />

        <Card>
          <CardHeader
            title="Activity"
            description={meta ? `${meta.total} recorded events` : 'Recorded events'}
            action={
              <SearchInput
                value={search}
                onChange={setSearch}
                placeholder="Search action or entity…"
                className="w-64"
              />
            }
          />
          <CardBody>
            {!loading && rows.length === 0 ? (
              <EmptyState
                icon={History}
                title="Nothing recorded yet"
                description="Actions taken by your staff will be listed here."
              />
            ) : (
              <>
                <DataTable columns={columns} rows={rows} loading={loading} />
                <Pagination meta={meta} page={page} onPageChange={setPage} />
              </>
            )}
            {error ? <p className="mt-3 text-sm text-slate-500">Could not load the audit trail.</p> : null}
          </CardBody>
        </Card>
      </div>
    </PageContainer>
  )
}
