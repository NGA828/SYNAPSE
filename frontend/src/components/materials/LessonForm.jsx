import { useState } from 'react'
import { Modal } from '../ui/Modal.jsx'
import { Button } from '../ui/Button.jsx'
import { Input } from '../ui/Input.jsx'
import { Select } from '../ui/Select.jsx'
import { Textarea } from '../ui/Textarea.jsx'
import { useAsyncList } from '../../hooks/useAsyncList.js'
import { listAssignments } from '../../services/teacherService.js'
import { createLesson, updateLesson } from '../../services/lessonService.js'
import { AttachmentPicker } from '../homework/AttachmentPicker.jsx'
import { AttachmentList } from '../homework/AttachmentList.jsx'

/**
 * Create/edit dialog for one lesson.
 *
 * Remounted per open via `key`, like the other dialogs here. Class and subject
 * come from the teacher's own TeachingAssignment records, so they cannot target
 * a class they do not teach.
 */
export function LessonForm({ open, onClose, onSaved, lesson }) {
  const { data: assignments } = useAsyncList(listAssignments)
  const editing = Boolean(lesson)

  const [form, setForm] = useState(() =>
    lesson
      ? {
          class_id: String(lesson.class?.id ?? ''),
          subject_id: String(lesson.subject?.id ?? ''),
          title: lesson.title ?? '',
          topic: lesson.topic ?? '',
          summary: lesson.summary ?? '',
          body: lesson.body ?? '',
          minutes: lesson.minutes ? String(lesson.minutes) : '',
        }
      : { class_id: '', subject_id: '', title: '', topic: '', summary: '', body: '', minutes: '' },
  )
  const [files, setFiles] = useState([])
  const [saving, setSaving] = useState(false)
  const [error, setError] = useState(null)

  const set = (key) => (event) => setForm((current) => ({ ...current, [key]: event.target.value }))

  const options = assignments ?? []
  const classIds = [...new Set(options.map((item) => item.class?.id).filter(Boolean))]
  const subjectIds = [...new Set(options.map((item) => item.subject?.id).filter(Boolean))]

  const handleSubmit = async (event) => {
    event.preventDefault()
    setSaving(true)
    setError(null)

    const payload = {
      class_id: Number(form.class_id),
      subject_id: Number(form.subject_id),
      title: form.title,
      topic: form.topic || null,
      summary: form.summary || null,
      body: form.body || null,
      minutes: form.minutes ? Number(form.minutes) : null,
    }

    try {
      const saved = editing
        ? await updateLesson(lesson.id, payload, files)
        : await createLesson(payload, files)
      onSaved?.(saved)
      onClose()
    } catch (err) {
      const first = Object.values(err?.response?.data?.errors ?? {})[0]?.[0]
      setError(first ?? err?.response?.data?.message ?? 'Could not save this lesson.')
    } finally {
      setSaving(false)
    }
  }

  return (
    <Modal
      open={open}
      onClose={onClose}
      title={editing ? 'Edit lesson' : 'New lesson'}
      description={editing ? 'Only the content can change.' : 'It stays a draft until you publish it.'}
    >
      <form onSubmit={handleSubmit} className="mt-4 space-y-4">
        <div className="grid gap-4 sm:grid-cols-2">
          <Select label="Class" name="class_id" value={form.class_id} onChange={set('class_id')} disabled={editing} required>
            <option value="">Select a class…</option>
            {classIds.map((id) => (
              <option key={id} value={id}>
                {options.find((item) => item.class?.id === id)?.class?.name}
              </option>
            ))}
          </Select>

          <Select label="Subject" name="subject_id" value={form.subject_id} onChange={set('subject_id')} disabled={editing} required>
            <option value="">Select a subject…</option>
            {subjectIds.map((id) => (
              <option key={id} value={id}>
                {options.find((item) => item.subject?.id === id)?.subject?.name}
              </option>
            ))}
          </Select>
        </div>

        <Input label="Title" name="title" value={form.title} onChange={set('title')} maxLength={180} required />

        <div className="grid gap-4 sm:grid-cols-2">
          <Input
            label="Topic"
            name="topic"
            value={form.topic}
            onChange={set('topic')}
            maxLength={120}
            placeholder="e.g. Essay Writing"
            hint="Groups lessons on the student page."
          />
          <Input
            label="Reading time (minutes)"
            name="minutes"
            type="number"
            min="1"
            max="600"
            value={form.minutes}
            onChange={set('minutes')}
            hint="Optional — estimated from the text if left blank."
          />
        </div>

        <Input
          label="Summary"
          name="summary"
          value={form.summary}
          onChange={set('summary')}
          maxLength={500}
          placeholder="One line shown on the student card."
        />

        <Textarea
          label="Lesson content"
          name="body"
          value={form.body}
          onChange={set('body')}
          rows={8}
          maxLength={50000}
          hint="Optional if you are only sharing files."
        />

        <AttachmentPicker files={files} onChange={setFiles} label={editing ? 'Add more files' : 'Attach slides or notes'} />

        {editing && lesson.attachments?.length ? (
          <AttachmentList attachments={lesson.attachments} label="Already attached" />
        ) : null}

        {error ? <p className="rounded-xl bg-rose-50 px-3 py-2 text-sm text-rose-700">{error}</p> : null}

        <div className="flex justify-end gap-2 pt-1">
          <Button variant="secondary" onClick={onClose} type="button">
            Cancel
          </Button>
          <Button type="submit" loading={saving}>
            {editing ? 'Save changes' : 'Create draft'}
          </Button>
        </div>
      </form>
    </Modal>
  )
}
