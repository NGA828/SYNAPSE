import { useState } from 'react'
import { MessageSquare } from 'lucide-react'
import { useAsyncList } from '../../hooks/useAsyncList.js'
import { getQuizResults, reviewQuizAttempt } from '../../services/quizService.js'
import { Modal } from '../ui/Modal.jsx'
import { Button } from '../ui/Button.jsx'
import { Badge } from '../ui/Badge.jsx'
import { DataTable } from '../ui/DataTable.jsx'
import { Textarea } from '../ui/Textarea.jsx'
import { Spinner } from '../ui/Spinner.jsx'

/**
 * Class results for one quiz: overall marks plus a per-question breakdown that
 * shows which items the class actually missed.
 */
export function QuizResultsPanel({ quizId, open, onClose }) {
  const { data, loading, reload } = useAsyncList(() => getQuizResults(quizId), [quizId])
  const [feedbackFor, setFeedbackFor] = useState(null)
  const [feedback, setFeedback] = useState('')
  const [saving, setSaving] = useState(false)

  const stats = data?.stats ?? null
  const students = data?.students ?? []
  const questions = data?.questions ?? []

  const sendFeedback = async (event) => {
    event.preventDefault()
    setSaving(true)
    try {
      await reviewQuizAttempt(feedbackFor.attempt_id, feedback)
      setFeedbackFor(null)
      setFeedback('')
      reload()
    } finally {
      setSaving(false)
    }
  }

  const columns = [
    {
      key: 'name',
      header: 'Student',
      render: (row) => (
        <div className="min-w-0">
          <p className="truncate font-medium text-slate-800">{row.name}</p>
          <p className="text-xs text-slate-500">{row.matricule}</p>
        </div>
      ),
    },
    {
      key: 'score',
      header: 'Score',
      render: (row) =>
        row.score === null ? (
          <span className="text-slate-400">—</span>
        ) : (
          <span className="font-medium text-slate-800">
            {Number(row.score).toFixed(2)}
            <span className="text-slate-400"> / {row.max_score}</span>
          </span>
        ),
    },
    {
      key: 'correct',
      header: 'Correct',
      render: (row) =>
        row.correct_count === null ? (
          <span className="text-slate-400">—</span>
        ) : (
          <span className="text-slate-600">
            {row.correct_count}/{row.total_questions}
          </span>
        ),
    },
    {
      key: 'percentage',
      header: '%',
      render: (row) =>
        row.percentage === null ? (
          <span className="text-slate-400">—</span>
        ) : (
          <span className="text-slate-600">{row.percentage}%</span>
        ),
    },
    {
      key: 'status',
      header: 'Status',
      render: (row) =>
        row.status === 'not_attempted' ? (
          <Badge variant="neutral">Not attempted</Badge>
        ) : row.is_reviewed ? (
          <Badge variant="success" dot>
            Reviewed
          </Badge>
        ) : (
          <Badge variant="warning">Awaiting review</Badge>
        ),
    },
    {
      key: 'actions',
      header: '',
      align: 'right',
      render: (row) =>
        row.attempt_id ? (
          <Button
            size="sm"
            variant="ghost"
            onClick={() => {
              setFeedbackFor(row)
              setFeedback('')
            }}
          >
            <MessageSquare className="size-3.5" />
            Feedback
          </Button>
        ) : null,
    },
  ]

  return (
    <Modal open={open} onClose={onClose} title={data?.quiz?.title ?? 'Results'} description="Auto-marked results for the class.">
      {loading || !data ? (
        <div className="flex justify-center py-10">
          <Spinner className="size-7" />
        </div>
      ) : (
        <div className="mt-4 space-y-5">
          <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
            <SummaryTile label="Submitted" value={`${stats.submitted}/${stats.total}`} />
            <SummaryTile label="Average" value={stats.average === null ? '—' : Number(stats.average).toFixed(2)} />
            <SummaryTile label="Highest" value={stats.highest === null ? '—' : Number(stats.highest).toFixed(2)} />
            <SummaryTile label="Pass rate" value={stats.pass_rate === null ? '—' : `${stats.pass_rate}%`} />
          </div>

          <div>
            <p className="mb-2 text-sm font-medium text-slate-700">Where the class struggled</p>
            <div className="space-y-1.5">
              {questions.map((question) => (
                <div key={question.id} className="flex items-center gap-3">
                  <p className="min-w-0 flex-1 truncate text-sm text-slate-600">{question.prompt}</p>
                  <span className="shrink-0 text-xs text-slate-500">
                    {question.correct_count}/{stats.submitted || 1} correct
                  </span>
                  <span className="h-1.5 w-20 shrink-0 overflow-hidden rounded-full bg-slate-100">
                    <span
                      className="block h-full rounded-full bg-brand-500"
                      style={{ width: `${stats.submitted ? (question.correct_count / stats.submitted) * 100 : 0}%` }}
                    />
                  </span>
                </div>
              ))}
            </div>
          </div>

          <DataTable
            columns={columns}
            rows={students}
            loading={false}
            emptyTitle="Nobody has sat this quiz yet"
            emptyDescription="Results appear here as soon as the first student submits."
          />

          <div className="flex justify-end">
            <Button variant="secondary" onClick={onClose}>
              Close
            </Button>
          </div>
        </div>
      )}

      <Modal open={Boolean(feedbackFor)} onClose={() => setFeedbackFor(null)} title="Add feedback">
        <form onSubmit={sendFeedback} className="mt-4 space-y-3">
          <p className="text-sm text-slate-600">
            {feedbackFor?.name} scored {feedbackFor?.score === null ? '—' : Number(feedbackFor.score).toFixed(2)} /{' '}
            {feedbackFor?.max_score}.
          </p>
          <Textarea
            label="Feedback"
            name="feedback"
            value={feedback}
            onChange={(event) => setFeedback(event.target.value)}
            rows={4}
            maxLength={5000}
            hint="Releases the result to the student and notifies them."
          />
          <div className="flex justify-end gap-2">
            <Button variant="secondary" type="button" onClick={() => setFeedbackFor(null)}>
              Cancel
            </Button>
            <Button type="submit" loading={saving}>
              Send feedback
            </Button>
          </div>
        </form>
      </Modal>
    </Modal>
  )
}

function SummaryTile({ label, value }) {
  return (
    <div className="rounded-xl bg-slate-50 px-3 py-2">
      <p className="text-xs text-slate-500">{label}</p>
      <p className="text-lg font-semibold text-slate-800">{value}</p>
    </div>
  )
}
