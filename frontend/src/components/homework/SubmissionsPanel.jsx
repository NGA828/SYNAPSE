import { useState } from 'react'
import { CheckCircle2, CircleDashed, Clock } from 'lucide-react'
import { useAsyncList } from '../../hooks/useAsyncList.js'
import { getHomeworkSubmissions } from '../../services/homeworkService.js'
import { Badge } from '../ui/Badge.jsx'
import { Button } from '../ui/Button.jsx'
import { Card, CardBody, CardHeader } from '../ui/Card.jsx'
import { Spinner } from '../ui/Spinner.jsx'
import { GradeSubmissionModal } from './GradeSubmissionModal.jsx'
import { formatDate } from '../../utils/formatters.js'

const statusBadge = {
  not_submitted: { variant: 'neutral', label: 'Not submitted', icon: CircleDashed },
  submitted: { variant: 'info', label: 'Awaiting mark', icon: Clock },
  late: { variant: 'danger', label: 'Late', icon: Clock },
  graded: { variant: 'success', label: 'Graded', icon: CheckCircle2 },
}

/**
 * The class roster for one piece of homework, with per-student marking.
 */
export function SubmissionsPanel({ homeworkId, onBack }) {
  const { data, loading, reload } = useAsyncList(() => getHomeworkSubmissions(homeworkId), [homeworkId])
  const [marking, setMarking] = useState(null)

  if (loading || !data) {
    return (
      <div className="flex justify-center py-20">
        <Spinner className="size-8" />
      </div>
    )
  }

  const { assignment, students, stats } = data

  return (
    <div className="space-y-6">
      <div className="flex flex-wrap items-end justify-between gap-3">
        <div>
          <button
            type="button"
            onClick={onBack}
            className="mb-1 text-sm font-medium text-brand-600 hover:underline"
          >
            ← All homework
          </button>
          <h2 className="text-xl font-bold tracking-tight text-slate-900">{assignment.title}</h2>
          <p className="mt-0.5 text-sm text-slate-500">
            {assignment.subject?.name} · {assignment.class?.name} · due {formatDate(assignment.due_at)}
          </p>
        </div>
        <div className="flex flex-wrap gap-2">
          <Badge variant="neutral">{stats.total} students</Badge>
          <Badge variant="info">{stats.submitted} submitted</Badge>
          <Badge variant="success">{stats.graded} graded</Badge>
          {stats.submitted - stats.graded > 0 ? (
            <Badge variant="warning">{stats.submitted - stats.graded} to mark</Badge>
          ) : null}
        </div>
      </div>

      <Card>
        <CardHeader title="Submissions" description="Click a submitted row to mark it and return the work." />
        <CardBody>
          <ul className="divide-y divide-slate-100">
            {students.map((row) => {
              const badge = statusBadge[row.status] ?? statusBadge.not_submitted
              const Icon = badge.icon
              return (
                <li key={row.student_id} className="flex items-center justify-between gap-3 py-3">
                  <div className="min-w-0">
                    <p className="truncate text-sm font-medium text-slate-800">{row.name}</p>
                    <p className="text-xs text-slate-500">
                      {row.matricule ?? '—'}
                      {row.submission ? ` · ${formatDate(row.submission.submitted_at)}` : ''}
                      {row.submission?.attempts > 1 ? ` · attempt ${row.submission.attempts}` : ''}
                    </p>
                  </div>

                  <div className="flex shrink-0 items-center gap-3">
                    {row.score !== null && row.score !== undefined ? (
                      <span className="text-sm font-semibold text-slate-800">
                        {row.score}/{row.max_score}
                      </span>
                    ) : null}
                    <Badge variant={badge.variant} dot>
                      <Icon className="size-3" aria-hidden="true" />
                      {badge.label}
                    </Badge>
                    {row.submission ? (
                      <Button size="sm" variant="soft" onClick={() => setMarking(row)}>
                        {row.score !== null && row.score !== undefined ? 'Remark' : 'Mark'}
                      </Button>
                    ) : null}
                  </div>
                </li>
              )
            })}
          </ul>
        </CardBody>
      </Card>

      <GradeSubmissionModal
        key={marking ? `mark-${marking.submission?.id}` : 'closed'}
        open={Boolean(marking)}
        onClose={() => setMarking(null)}
        onSaved={reload}
        row={marking}
        maxScore={assignment.max_score}
      />
    </div>
  )
}
