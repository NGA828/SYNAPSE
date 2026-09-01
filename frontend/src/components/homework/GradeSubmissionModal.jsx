import { useState } from 'react'
import { Modal } from '../ui/Modal.jsx'
import { Button } from '../ui/Button.jsx'
import { Input } from '../ui/Input.jsx'
import { Textarea } from '../ui/Textarea.jsx'
import { Badge } from '../ui/Badge.jsx'
import { gradeSubmission } from '../../services/homeworkService.js'
import { AttachmentList } from './AttachmentList.jsx'

/**
 * Mark one student's work and return it to them.
 *
 * Remounted per open (via `key`) so the form is seeded from the row without an
 * effect. The score ceiling comes from the homework, and the backend re-checks
 * it — so a mark above `max_score` is rejected even if this form is bypassed.
 */
export function GradeSubmissionModal({ open, onClose, onSaved, row, maxScore }) {
  const submission = row?.submission

  const [score, setScore] = useState(() =>
    submission?.score !== null && submission?.score !== undefined ? String(submission.score) : '',
  )
  const [feedback, setFeedback] = useState(submission?.feedback ?? '')
  const [saving, setSaving] = useState(false)
  const [error, setError] = useState(null)

  if (!row || !submission) return null

  const handleSubmit = async (event) => {
    event.preventDefault()
    setSaving(true)
    setError(null)

    try {
      const saved = await gradeSubmission(submission.id, {
        score: Number(score),
        feedback: feedback || null,
      })
      onSaved?.(saved)
      onClose()
    } catch (err) {
      const first = Object.values(err?.response?.data?.errors ?? {})[0]?.[0]
      setError(first ?? err?.response?.data?.message ?? 'Could not save this mark.')
    } finally {
      setSaving(false)
    }
  }

  return (
    <Modal open={open} onClose={onClose} title={row.name} description={`Matricule ${row.matricule ?? '—'}`}>
      <form onSubmit={handleSubmit} className="mt-4 space-y-4">
        <div className="rounded-xl border border-slate-200 bg-slate-50 p-3">
          <div className="mb-2 flex items-center justify-between">
            <p className="text-xs font-semibold uppercase tracking-wide text-slate-500">Student answer</p>
            {submission.attempts > 1 ? <Badge variant="warning">Attempt {submission.attempts}</Badge> : null}
          </div>
          {submission.content ? (
            <p className="max-h-48 overflow-y-auto whitespace-pre-wrap text-sm leading-relaxed text-slate-700">
              {submission.content}
            </p>
          ) : (
            <p className="text-sm italic text-slate-400">No text was written — the work is attached below.</p>
          )}

          {submission.attachments?.length ? (
            <div className="mt-2.5">
              <AttachmentList attachments={submission.attachments} label="Attached files" />
            </div>
          ) : null}
        </div>

        <div className="grid items-end gap-4 sm:grid-cols-2">
          <Input
            label="Score"
            name="score"
            type="number"
            step="0.5"
            min="0"
            max={maxScore}
            value={score}
            onChange={(event) => setScore(event.target.value)}
            hint={`Out of ${maxScore}`}
            required
          />
          <div className="pb-2.5 text-sm">
            {submission.is_late ? <Badge variant="danger">Submitted late</Badge> : <span className="text-slate-500">On time</span>}
          </div>
        </div>

        <Textarea
          label="Feedback"
          name="feedback"
          value={feedback}
          onChange={(event) => setFeedback(event.target.value)}
          rows={4}
          maxLength={2000}
          hint="Returned to the student with the mark."
        />

        {error ? <p className="rounded-xl bg-rose-50 px-3 py-2 text-sm text-rose-700">{error}</p> : null}

        <div className="flex justify-end gap-2 pt-1">
          <Button variant="secondary" onClick={onClose} type="button">
            Cancel
          </Button>
          <Button type="submit" loading={saving}>
            Save and return
          </Button>
        </div>
      </form>
    </Modal>
  )
}
