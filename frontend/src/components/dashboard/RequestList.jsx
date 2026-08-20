import { FileText } from 'lucide-react'
import { formatDate } from '../../utils/formatters.js'
import { Badge } from '../ui/Badge.jsx'
import { EmptyState } from './EmptyState.jsx'

const STATUS_META = {
  pending: { label: 'Pending', variant: 'warning' },
  under_review: { label: 'Under Review', variant: 'info' },
  approved: { label: 'Approved', variant: 'success' },
  ready: { label: 'Ready', variant: 'success' },
}

export function RequestList({ requests = [] }) {
  if (!requests || requests.length === 0) {
    return <EmptyState title="No requests" description="Student requests will appear here." />
  }

  return (
    <ul className="divide-y divide-slate-100">
      {requests.map((request) => {
        const meta = STATUS_META[request.status] ?? STATUS_META.pending

        return (
          <li key={request.id} className="flex items-center gap-3 py-3 first:pt-0 last:pb-0">
            <span className="flex size-8 shrink-0 items-center justify-center rounded-lg bg-slate-100 text-slate-500">
              <FileText className="size-4" aria-hidden="true" />
            </span>
            <div className="min-w-0 flex-1">
              <p className="text-sm font-semibold text-slate-800">
                {request.id} · {request.student}
              </p>
              <p className="truncate text-sm text-slate-500">
                {request.type} · {formatDate(request.date)}
              </p>
            </div>
            <Badge variant={meta.variant} dot>{meta.label}</Badge>
          </li>
        )
      })}
    </ul>
  )
}
