import { useState } from 'react'
import { Megaphone } from 'lucide-react'
import { useAsyncList } from '../../hooks/useAsyncList.js'
import { createAnnouncement, getAnnouncements } from '../../services/announcementService.js'
import { formatDate } from '../../utils/formatters.js'
import { Button } from '../ui/Button.jsx'
import { Input } from '../ui/Input.jsx'
import { Select } from '../ui/Select.jsx'
import { Badge } from '../ui/Badge.jsx'
import { Card, CardBody, CardHeader } from '../ui/Card.jsx'
import { Spinner } from '../ui/Spinner.jsx'
import { ErrorDisplay } from '../forms/ErrorDisplay.jsx'

const AUDIENCES = [
  { value: 'all', label: 'All users' },
  { value: 'students', label: 'Students' },
  { value: 'teachers', label: 'Teachers' },
]

export function AnnouncementManager() {
  const { data: announcements, loading, error, reload } = useAsyncList(getAnnouncements)
  const [title, setTitle] = useState('')
  const [body, setBody] = useState('')
  const [audience, setAudience] = useState('all')
  const [formError, setFormError] = useState(null)
  const [submitting, setSubmitting] = useState(false)

  const handleCreate = async (event) => {
    event.preventDefault()
    setSubmitting(true)
    setFormError(null)
    try {
      await createAnnouncement({ title, body, audience })
      setTitle('')
      setBody('')
      setAudience('all')
      await reload()
    } catch (err) {
      setFormError(err?.response?.data?.message ?? 'Could not publish the announcement.')
    } finally {
      setSubmitting(false)
    }
  }

  return (
    <Card>
      <CardHeader title="Announcements" description="Publish updates to your school community" />
      <CardBody>
        <form onSubmit={handleCreate} className="mb-4 space-y-3">
          <Input
            label="Title"
            name="title"
            placeholder="e.g. Exam Timetable Published"
            value={title}
            onChange={(event) => setTitle(event.target.value)}
          />
          <div>
            <label htmlFor="body" className="mb-1.5 block text-sm font-medium text-slate-700">
              Message
            </label>
            <textarea
              id="body"
              name="body"
              rows={3}
              value={body}
              onChange={(event) => setBody(event.target.value)}
              placeholder="Write your announcement…"
              className="w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2.5 text-sm text-slate-900 placeholder:text-slate-400 focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-100"
            />
          </div>
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
