import { useState } from 'react'
import { CalendarClock, CheckCircle2, CircleDashed, Clock, Send } from 'lucide-react'
import { submitHomework } from '../../services/homeworkService.js'
import { Badge } from '../ui/Badge.jsx'
import { Button } from '../ui/Button.jsx'
import { Card, CardBody } from '../ui/Card.jsx'
import { Textarea } from '../ui/Textarea.jsx'
import { formatDate } from '../../utils/formatters.js'
import { cn } from '../../utils/cn.js'
import { AttachmentList } from './AttachmentList.jsx'
import { AttachmentPicker } from './AttachmentPicker.jsx'

const statusMeta = {
  not_submitted: { variant: 'neutral', label: 'Not submitted', icon: CircleDashed },
  submitted: { variant: 'info', label: 'Awaiting mark', icon: Clock },
  late: { variant: 'danger', label: 'Late', icon: Clock },
  graded: { variant: 'success', label: 'Graded', icon: CheckCircle2 },
}

/**
 * One piece of homework from the student's point of view: the brief, their own
 * answer, the mark and the teacher's feedback.
 *
 * They may replace their answer any number of times until the deadline or until
 * the teacher marks it — both rules are enforced server-side too.
 */
export function HomeworkSubmissionCard({ item, onSubmitted }) {
  const { assignment, submission } = item
  const [composing, setComposing] = useState(false)
  const [content, setContent] = useState('')
  const [files, setFiles] = useState([])
  const [saving, setSaving] = useState(false)
  const [error, setError] = useState(null)

  const status = submission?.status ?? 'not_submitted'
  const meta = statusMeta[status]
  const Icon = meta.icon
  const canSubmit = assignment.is_open && status !== 'graded'

  const handleSubmit = async (event) => {
    event.preventDefault()
    setSaving(true)
    setError(null)

    try {
      await submitHomework(assignment.id, content, files)
      setContent('')
      setFiles([])
      setComposing(false)
      onSubmitted?.()
    } catch (err) {
      const first = Object.values(err?.response?.data?.errors ?? {})[0]?.[0]
      setError(first ?? err?.response?.data?.message ?? 'Could not submit your work.')
    } finally {
      setSaving(false)
    }
  }

  return (
    <Card>
      <CardBody>
        <div className="flex flex-wrap items-start justify-between gap-3">
          <div className="min-w-0">
            <div className="flex flex-wrap items-center gap-2">
              <h3 className="font-semibold text-slate-900">{assignment.title}</h3>
              <Badge variant={meta.variant} dot>
                <Icon className="size-3" aria-hidden="true" />
                {meta.label}
              </Badge>
            </div>
            <p className="mt-0.5 text-xs text-slate-500">
              {assignment.subject?.name} · {assignment.class?.name} · out of {assignment.max_score}
            </p>
          </div>

          <p
            className={cn(
              'flex shrink-0 items-center gap-1.5 text-xs font-medium',
              assignment.is_past_due ? 'text-slate-400' : 'text-slate-600',
            )}
          >
            <CalendarClock className="size-3.5" aria-hidden="true" />
            {assignment.is_past_due ? 'Closed' : `Due ${formatDate(assignment.due_at)}`}
          </p>
        </div>

        {assignment.instructions ? (
          <p className="mt-3 whitespace-pre-wrap rounded-xl bg-slate-50 px-3 py-2.5 text-sm leading-relaxed text-slate-700">
            {assignment.instructions}
          </p>
        ) : null}

        {assignment.attachments?.length ? (
          <div className="mt-3">
            <AttachmentList attachments={assignment.attachments} label="Homework document" />
          </div>
        ) : null}

        {submission ? (
          <div className="mt-3 rounded-xl border border-slate-200 px-3 py-2.5">
            <div className="mb-1.5 flex items-center justify-between">
              <p className="text-xs font-semibold uppercase tracking-wide text-slate-500">Your answer</p>
              <p className="text-xs text-slate-400">
                {formatDate(submission.submitted_at)}
                {submission.attempts > 1 ? ` · attempt ${submission.attempts}` : ''}
              </p>
            </div>
            {submission.content ? (
              <p className="whitespace-pre-wrap text-sm leading-relaxed text-slate-700">{submission.content}</p>
            ) : (
              <p className="text-sm italic text-slate-400">Submitted as an attached file.</p>
            )}
            {submission.attachments?.length ? (
              <div className="mt-2.5">
                <AttachmentList attachments={submission.attachments} label="Your files" />
              </div>
            ) : null}
          </div>
        ) : null}

        {status === 'graded' ? (
          <div className="mt-3 rounded-xl border border-emerald-200 bg-emerald-50 px-3 py-2.5">
            <p className="text-sm font-semibold text-emerald-800">
              {submission.score}/{assignment.max_score}
            </p>
            {submission.feedback ? (
              <p className="mt-1 whitespace-pre-wrap text-sm leading-relaxed text-emerald-900">
                {submission.feedback}
              </p>
            ) : null}
          </div>
        ) : null}

        {!assignment.is_open ? (
          <p className="mt-3 text-xs text-slate-400">
            {assignment.is_past_due
              ? 'The deadline has passed, so this can no longer be changed.'
              : 'Not yet open for submissions.'}
          </p>
        ) : null}

        {canSubmit ? (
          composing ? (
            <form onSubmit={handleSubmit} className="mt-3 space-y-3">
              <Textarea
                label={submission ? 'Replace your answer' : 'Your answer'}
                name="content"
                value={content}
                onChange={(event) => setContent(event.target.value)}
                rows={5}
                maxLength={20000}
                hint="Optional if you attach a file instead."
              />
              <AttachmentPicker files={files} onChange={setFiles} label="Attach your work (PDF or Word)" />
              {error ? <p className="rounded-xl bg-rose-50 px-3 py-2 text-sm text-rose-700">{error}</p> : null}
              <div className="flex justify-end gap-2">
                <Button variant="secondary" onClick={() => setComposing(false)} type="button">
                  Cancel
                </Button>
                <Button type="submit" loading={saving} disabled={!content.trim() && files.length === 0}>
                  <Send className="size-4" />
                  {submission ? 'Replace submission' : 'Submit'}
                </Button>
              </div>
            </form>
          ) : (
            <div className="mt-3">
              <Button
                variant={submission ? 'secondary' : 'primary'}
                size="sm"
                onClick={() => {
                  setContent(submission?.content ?? '')
                  setComposing(true)
                }}
              >
                {submission ? 'Replace answer' : 'Submit your work'}
              </Button>
            </div>
          )
        ) : null}
      </CardBody>
    </Card>
  )
}
