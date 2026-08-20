import { useAsyncList } from '../../hooks/useAsyncList.js'
import {
  generateRequestDocument,
  getAdminRequests,
  updateRequestStatus,
} from '../../services/requestService.js'
import { REQUEST_STATUS_META } from '../../utils/requests.js'
import { formatDate } from '../../utils/formatters.js'
import { Badge } from '../ui/Badge.jsx'
import { Button } from '../ui/Button.jsx'
import { Card, CardBody, CardHeader } from '../ui/Card.jsx'
import { DataTable } from '../ui/DataTable.jsx'

function Actions({ request, onChanged }) {
  const setStatus = (status) => async () => {
    await updateRequestStatus(request.id, { status })
    onChanged()
  }

  const generate = async () => {
    await generateRequestDocument(request.id)
    onChanged()
  }

  if (request.status === 'submitted') {
    return (
      <div className="flex justify-end gap-1.5">
        <Button size="sm" variant="secondary" onClick={setStatus('under_review')}>
          Review
        </Button>
        <Button size="sm" onClick={setStatus('approved')}>
          Approve
        </Button>
        <Button size="sm" variant="dangerSoft" onClick={setStatus('rejected')}>
          Reject
        </Button>
      </div>
    )
  }

  if (request.status === 'under_review') {
    return (
      <div className="flex justify-end gap-1.5">
        <Button size="sm" onClick={setStatus('approved')}>
          Approve
        </Button>
        <Button size="sm" variant="dangerSoft" onClick={setStatus('rejected')}>
          Reject
        </Button>
      </div>
    )
  }

  if (request.status === 'approved') {
    return (
      <div className="flex justify-end">
        <Button size="sm" onClick={generate}>
          Generate document
        </Button>
      </div>
    )
  }

  if (request.status === 'ready') {
    return (
      <div className="flex justify-end">
        <Badge variant="success" dot>
          Ready · {request.documents?.length ?? 0} doc
        </Badge>
      </div>
    )
  }

  return (
    <div className="flex justify-end">
      <Badge variant="danger" dot>Rejected</Badge>
    </div>
  )
}

export function RequestManager() {
  const { data: requests, loading, error, reload } = useAsyncList(getAdminRequests)

  const columns = [
    { key: 'reference', header: 'Reference', cellClassName: 'font-mono text-xs text-slate-600' },
    {
      key: 'student',
      header: 'Student',
      render: (request) => (
        <span className="font-medium text-slate-800">
          {request.student?.user?.name ?? request.student?.name}
        </span>
      ),
    },
    { key: 'type', header: 'Type' },
    {
      key: 'date',
      header: 'Date',
      render: (request) => <span className="whitespace-nowrap text-slate-600">{formatDate(request.created_at)}</span>,
    },
    {
      key: 'status',
      header: 'Status',
      render: (request) => (
        <Badge variant={REQUEST_STATUS_META[request.status]?.variant} dot>
          {REQUEST_STATUS_META[request.status]?.label}
        </Badge>
      ),
    },
    {
      key: 'actions',
      header: '',
      align: 'right',
      render: (request) => <Actions request={request} onChanged={reload} />,
    },
  ]

  return (
    <Card>
      <CardHeader title="Student requests" description="Review, approve and generate documents" />
      <CardBody>
        <DataTable
          columns={columns}
          rows={requests}
          loading={loading}
          emptyTitle="No requests"
          emptyDescription="Student requests will appear here."
        />
        {error ? <p className="mt-3 text-sm text-slate-500">Could not load requests.</p> : null}
      </CardBody>
    </Card>
  )
}
