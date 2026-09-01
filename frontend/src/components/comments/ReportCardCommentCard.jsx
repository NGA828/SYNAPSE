import { useState } from 'react'
import { Lock, RefreshCw, Sparkles } from 'lucide-react'
import {
  draftReportCardComment,
  getReportCardComment,
  saveReportCardComment,
} from '../../services/reportCardCommentService.js'
import { useAsyncList } from '../../hooks/useAsyncList.js'
import { Badge } from '../ui/Badge.jsx'
import { Button } from '../ui/Button.jsx'
import { Card, CardBody, CardHeader } from '../ui/Card.jsx'
import { Spinner } from '../ui/Spinner.jsx'
import { Textarea } from '../ui/Textarea.jsx'
import { ErrorDisplay } from '../forms/ErrorDisplay.jsx'

const SOURCE_META = {
  teacher: { label: 'Written by a teacher', variant: 'success' },
  ai: { label: 'AI draft, not yet approved', variant: 'warning' },
  generated: { label: 'Generated from the marks', variant: 'info' },
}

/**
 * Review and approve a report-card comment.
 *
 * The draft is generated from the pupil's actual marks, and the numbers in it
 * are the ones on the card — the writer describes figures, it does not compute
 * them. Nothing reaches the PDF until a teacher locks it, which is why "Save"
 * and "Save & lock" are separate actions.
 */
export function ReportCardCommentCard({ studentId, studentName }) {
  const { data, loading, reload } = useAsyncList(() => getReportCardComment(studentId), [studentId])
  const [body, setBody] = useState(null)
  const [saving, setSaving] = useState(false)
  const [drafting, setDrafting] = useState(false)
  const [error, setError] = useState(null)
  const [saved, setSaved] = useState(null)

  const current = body ?? data?.comment?.body ?? data?.effective ?? ''
  const locked = Boolean(data?.comment?.is_locked)
  const meta = SOURCE_META[data?.comment?.source] ?? null

  const run = async (fn) => {
    setError(null)
    try {
      return await fn()
    } catch (err) {
      setError(err?.response?.data?.message ?? 'Something went wrong.')
      return null
    }
  }

  const redraft = async () => {
    setDrafting(true)
    const draft = await run(() => draftReportCardComment(studentId))
    setDrafting(false)
    if (draft) {
      setBody(draft.body)
      setSaved(null)
    }
  }

  const save = async (lock) => {
    setSaving(true)
    const result = await run(() => saveReportCardComment(studentId, { body: current, lock }))
    setSaving(false)
    if (result) {
      setSaved(result)
      setBody(result.body)
      reload()
    }
  }

  if (loading) {
    return (
      <Card>
        <CardBody>
          <div className="flex justify-center py-10">
            <Spinner className="size-7" />
          </div>
        </CardBody>
      </Card>
    )
  }

  return (
    <Card>
      <CardHeader
        title="Report-card comment"
        description={
          studentName
            ? `For ${studentName}. Drafted from the marks on the card — edit anything that is not right.`
            : 'Drafted from the marks on the card — edit anything that is not right.'
        }
        action={
          <Button variant="secondary" size="sm" onClick={redraft} loading={drafting}>
            <RefreshCw className="size-4" aria-hidden="true" />
            Redraft
          </Button>
        }
      />
      <CardBody className="space-y-3">
        <ErrorDisplay message={error} />

        {meta ? (
          <div className="flex flex-wrap items-center gap-2">
            <Badge variant={meta.variant} dot>
              {meta.label}
            </Badge>
            {locked ? (
              <Badge variant="success">
                <Lock className="size-3" aria-hidden="true" />
                Locked onto the report card
              </Badge>
            ) : (
              <Badge variant="neutral">Not yet locked</Badge>
            )}
          </div>
        ) : (
          <p className="flex items-center gap-2 text-xs text-slate-500">
            <Sparkles className="size-3.5" aria-hidden="true" />
            No comment saved yet — the card will use the generated text below.
          </p>
        )}

        {data?.ai_available === false ? (
          <p className="text-xs text-slate-400">
            Drafted from the figures on this card. AI phrasing is not enabled for this school.
          </p>
        ) : null}

        <Textarea
          label="Comment"
          rows={4}
          value={current}
          onChange={(event) => {
            setBody(event.target.value)
            setSaved(null)
          }}
        />

        <div className="flex flex-wrap justify-end gap-2">
          <Button variant="secondary" onClick={() => save(false)} loading={saving}>
            Save
          </Button>
          <Button onClick={() => save(true)} loading={saving}>
            <Lock className="size-4" aria-hidden="true" />
            Save &amp; lock
          </Button>
        </div>

        {saved ? (
          <p className="text-right text-xs text-emerald-700">
            {saved.is_locked ? 'Locked — this text will appear on the report card.' : 'Saved as a draft.'}
          </p>
        ) : null}
      </CardBody>
    </Card>
  )
}
