import { useState } from 'react'
import { Megaphone, Sparkles, Wand2 } from 'lucide-react'
import { useAsyncList } from '../../hooks/useAsyncList.js'
import {
  createAnnouncement,
  draftAnnouncement,
  getAnnouncements,
} from '../../services/announcementService.js'
import { formatDate } from '../../utils/formatters.js'
import { Button } from '../ui/Button.jsx'
import { Input } from '../ui/Input.jsx'
import { Select } from '../ui/Select.jsx'
import { Textarea } from '../ui/Textarea.jsx'
import { Badge } from '../ui/Badge.jsx'
import { Card, CardBody, CardHeader } from '../ui/Card.jsx'
import { Spinner } from '../ui/Spinner.jsx'
import { ErrorDisplay } from '../forms/ErrorDisplay.jsx'

const AUDIENCES = [
  { value: 'all', label: 'All users' },
  { value: 'students', label: 'Students' },
  { value: 'teachers', label: 'Teachers' },
]

const TONES = [
  { value: 'formal', label: 'Formal' },
  { value: 'friendly', label: 'Friendly' },
]

const LOCALES = [
  { value: 'en', label: 'English' },
  { value: 'fr', label: 'Français' },
]

const SOURCE_META = {
  http: { label: 'AI draft', variant: 'info' },
  deterministic: { label: 'Drafted from your notes', variant: 'neutral' },
}

export function AnnouncementManager() {
  const { data: announcements, loading, error, reload } = useAsyncList(getAnnouncements)
  const [title, setTitle] = useState('')
  const [body, setBody] = useState('')
  const [audience, setAudience] = useState('all')
  const [formError, setFormError] = useState(null)
  const [submitting, setSubmitting] = useState(false)

  const [showDrafter, setShowDrafter] = useState(false)
  const [subject, setSubject] = useState('')
  const [points, setPoints] = useState('')
  const [dateText, setDateText] = useState('')
  const [venue, setVenue] = useState('')
  const [actionRequired, setActionRequired] = useState('')
  const [tone, setTone] = useState('formal')
  const [locale, setLocale] = useState('en')
  const [drafting, setDrafting] = useState(false)
  const [draftError, setDraftError] = useState(null)
  const [draftMeta, setDraftMeta] = useState(null)

  const handleCreate = async (event) => {
    event.preventDefault()
    setSubmitting(true)
    setFormError(null)
    try {
      await createAnnouncement({ title, body, audience })
      setTitle('')
      setBody('')
      setAudience('all')
      setDraftMeta(null)
      await reload()
    } catch (err) {
      setFormError(err?.response?.data?.message ?? 'Could not publish the announcement.')
    } finally {
      setSubmitting(false)
    }
  }

  /*
   * Drafting fills the form and stops there. It cannot publish: the request
   * persists nothing server-side, so an administrator always reads and edits the
   * text before it reaches anyone.
   */
  const handleDraft = async () => {
    setDrafting(true)
    setDraftError(null)
    try {
      const draft = await draftAnnouncement({
        subject,
        key_points: points
          .split('\n')
          .map((line) => line.trim())
          .filter(Boolean),
        date_text: dateText || null,
        venue: venue || null,
        action_required: actionRequired || null,
        audience,
        tone,
        locale,
      })

      setTitle(draft.title)
      setBody(draft.body)
      setDraftMeta({
        source: draft.source,
        aiAvailable: draft.ai_available,
        shortBody: draft.short_body,
      })
    } catch (err) {
      setDraftError(
        err?.response?.data?.message ?? 'Could not produce a draft. You can still write it by hand.',
      )
    } finally {
      setDrafting(false)
    }
  }

  const meta = draftMeta ? SOURCE_META[draftMeta.source] : null

  return (
    <Card>
      <CardHeader title="Announcements" description="Publish updates to your school community" />
      <CardBody className="space-y-4">
        <div className="rounded-xl border border-slate-200 bg-slate-50/70 p-4">
          <div className="flex flex-wrap items-center justify-between gap-3">
            <div>
              <p className="flex items-center gap-2 text-sm font-semibold text-slate-800">
                <Wand2 className="size-4 text-brand-600" aria-hidden="true" />
                Draft it for me
              </p>
              <p className="mt-0.5 text-xs text-slate-500">
                Give the facts, get a written announcement in English or French. Nothing is sent
                until you publish it.
              </p>
            </div>
            <Button
              type="button"
              variant="secondary"
              size="sm"
              onClick={() => setShowDrafter((open) => !open)}
            >
              {showDrafter ? 'Hide' : 'Open'}
            </Button>
          </div>

          {showDrafter ? (
            <div className="mt-4 space-y-3">
              <ErrorDisplay message={draftError} />

              <Input
                label="What is it about?"
                name="draft-subject"
                placeholder="e.g. the mid-term examinations begin on Monday"
                value={subject}
                onChange={(event) => setSubject(event.target.value)}
              />

              <Textarea
                label="Key points"
                name="draft-points"
                rows={3}
                hint="One per line. Only put facts here — the wording is what gets drafted."
                placeholder={'Bring your student card\nPhones are not permitted'}
                value={points}
                onChange={(event) => setPoints(event.target.value)}
              />

              <div className="grid gap-3 sm:grid-cols-2">
                <Input
                  label="When"
                  name="draft-date"
                  placeholder="Monday 14 September at 08:00"
                  value={dateText}
                  onChange={(event) => setDateText(event.target.value)}
                />
                <Input
                  label="Where"
                  name="draft-venue"
                  placeholder="the main hall"
                  value={venue}
                  onChange={(event) => setVenue(event.target.value)}
                />
              </div>

              <Input
                label="What must people do?"
                name="draft-action"
                placeholder="Collect your timetable from the office"
                value={actionRequired}
                onChange={(event) => setActionRequired(event.target.value)}
              />

              <div className="grid gap-3 sm:grid-cols-2">
                <Select
                  label="Tone"
                  name="draft-tone"
                  value={tone}
                  onChange={(event) => setTone(event.target.value)}
                >
                  {TONES.map((option) => (
                    <option key={option.value} value={option.value}>
                      {option.label}
                    </option>
                  ))}
                </Select>
                <Select
                  label="Language"
                  name="draft-locale"
                  value={locale}
                  onChange={(event) => setLocale(event.target.value)}
                >
                  {LOCALES.map((option) => (
                    <option key={option.value} value={option.value}>
                      {option.label}
                    </option>
                  ))}
                </Select>
              </div>

              <div className="flex justify-end">
                <Button
                  type="button"
                  onClick={handleDraft}
                  loading={drafting}
                  disabled={!subject.trim()}
                >
                  <Sparkles className="size-4" aria-hidden="true" />
                  Generate draft
                </Button>
              </div>

              {meta ? (
                <div className="flex flex-wrap items-center gap-2 border-t border-slate-200 pt-3">
                  <Badge variant={meta.variant} dot>
                    {meta.label}
                  </Badge>
                  {draftMeta.aiAvailable ? null : (
                    <span className="text-xs text-slate-400">
                      Structured from your notes. AI phrasing is not enabled for this school.
                    </span>
                  )}
                </div>
              ) : null}
            </div>
          ) : null}
        </div>

        <form onSubmit={handleCreate} className="space-y-3">
          <Input
            label="Title"
            name="title"
            placeholder="e.g. Exam Timetable Published"
            value={title}
            onChange={(event) => setTitle(event.target.value)}
          />
          <Textarea
            label="Message"
            name="body"
            rows={5}
            value={body}
            onChange={(event) => setBody(event.target.value)}
            placeholder="Write your announcement, or draft one above and edit it here…"
          />
          <div className="flex flex-col gap-3 sm:flex-row sm:items-end">
            <Select
              label="Audience"
              name="audience"
              value={audience}
              onChange={(event) => setAudience(event.target.value)}
              className="sm:max-w-48"
            >
              {AUDIENCES.map((option) => (
                <option key={option.value} value={option.value}>
                  {option.label}
                </option>
              ))}
            </Select>
            <Button type="submit" loading={submitting}>
              Publish
            </Button>
          </div>
        </form>
        <ErrorDisplay message={formError} />

        {loading ? (
          <div className="flex justify-center py-10">
            <Spinner />
          </div>
        ) : error ? (
          <p className="text-sm text-slate-500">Could not load announcements.</p>
        ) : (
          <ul className="divide-y divide-slate-100">
            {announcements?.map((announcement) => (
              <li key={announcement.id} className="flex gap-3 py-3 first:pt-0 last:pb-0">
                <span className="mt-0.5 flex size-8 shrink-0 items-center justify-center rounded-lg bg-brand-50 text-brand-600">
                  <Megaphone className="size-4" aria-hidden="true" />
                </span>
                <div className="min-w-0 flex-1">
                  <div className="flex flex-wrap items-center gap-2">
                    <p className="text-sm font-semibold text-slate-800">{announcement.title}</p>
                    <Badge variant="neutral">{announcement.audience}</Badge>
                  </div>
                  <p className="mt-0.5 text-sm text-slate-500">{announcement.body}</p>
                  <p className="mt-1 text-xs text-slate-400">
                    {formatDate(announcement.published_at)} · by {announcement.author?.name}
                  </p>
                </div>
              </li>
            ))}
          </ul>
        )}
      </CardBody>
    </Card>
  )
}
