import { Link } from 'react-router-dom'
import { Download, FileText } from 'lucide-react'
import { REQUEST_STATUS_META } from '../../utils/requests.js'
import { formatDate } from '../../utils/formatters.js'
import { Badge } from '../ui/Badge.jsx'
import { Card } from '../ui/Card.jsx'
import { Button } from '../ui/Button.jsx'
import { RequestStatusStepper } from './RequestStatusStepper.jsx'
import { cn } from '../../utils/cn.js'

const ACCENT = {
  submitted: 'border-l-amber-400',
  under_review: 'border-l-brand-400',
  approved: 'border-l-violet-400',
  ready: 'border-l-emerald-400',
  rejected: 'border-l-rose-400',
}

export function RequestCard({ request }) {
  const meta = REQUEST_STATUS_META[request.status] ?? REQUEST_STATUS_META.submitted
  const ready = request.status === 'ready'

  return (
    <Card className={cn('border-l-4 p-5', ACCENT[request.status])}>
      <div className="flex items-start justify-between gap-3">
        <div className="flex items-center gap-3">
          <span className="flex size-10 shrink-0 items-center justify-center rounded-xl bg-brand-50 text-brand-600">
            <FileText className="size-5" aria-hidden="true" />
          </span>
          <div>
            <p className="text-sm font-semibold text-slate-900">
              {request.reference} · {request.type}
            </p>
            <p className="text-xs text-slate-500">Submitted {formatDate(request.created_at)}</p>
          </div>
        </div>
        <Badge variant={meta.variant} dot>{meta.label}</Badge>
      </div>

      {request.reason ? <p className="mt-3 text-sm text-slate-500">{request.reason}</p> : null}
      {request.admin_note ? (
        <p className="mt-2 rounded-lg bg-amber-50 px-3 py-2 text-xs text-amber-700">
          Admin note: {request.admin_note}
        </p>
      ) : null}

      <div className="mt-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <RequestStatusStepper status={request.status} />
        {ready ? (
          <Link to="/student/documents" className="shrink-0">
            <Button size="sm" variant="soft">
              <Download className="size-4" aria-hidden="true" />
              Download
            </Button>
          </Link>
        ) : null}
      </div>
    </Card>
  )
}
