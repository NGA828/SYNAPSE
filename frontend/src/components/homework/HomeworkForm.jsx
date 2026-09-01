import { useState } from 'react'
import { Modal } from '../ui/Modal.jsx'
import { Button } from '../ui/Button.jsx'
import { Input } from '../ui/Input.jsx'
import { Select } from '../ui/Select.jsx'
import { Textarea } from '../ui/Textarea.jsx'
import { useAsyncList } from '../../hooks/useAsyncList.js'
import { listAssignments } from '../../services/teacherService.js'
import { createHomework, updateHomework } from '../../services/homeworkService.js'
import { AttachmentPicker } from './AttachmentPicker.jsx'
import { AttachmentList } from './AttachmentList.jsx'

/** `2026-09-30T23:59` (datetime-local) → an ISO string the `date` rule accepts. */
const toIso = (value) => (value ? new Date(value).toISOString() : '')

const toLocalInput = (iso) => {
  if (!iso) return ''
  const date = new Date(iso)
  const pad = (n) => String(n).padStart(2, '0')
  return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`
}

/**
 * Create/edit dialog for one piece of homework.
 *
 * The parent gives this component a `key` so it remounts per open, which is how
 * the initial form state is derived — the same pattern as
 * `TimetableEditorModal`. Class and subject come from the teacher's own
 * TeachingAssignment records, so they cannot target a class they do not teach.
 */
export function HomeworkForm({ open, onClose, onSaved, homework }) {
  const { data: assignments } = useAsyncList(listAssignments)
  const editing = Boolean(homework)

  const [form, setForm] = useState(() =>
    homework
      ? {
          class_id: String(homework.class?.id ?? ''),
          subject_id: String(homework.subject?.id ?? ''),
          title: homework.title ?? '',
          instructions: homework.instructions ?? '',
          max_score: String(homework.max_score ?? 20),
          due_at: toLocalInput(homework.due_at),
        }
      : { class_id: '', subject_id: '', title: '', instructions: '', max_score: '20', due_at: '' },
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
      instructions: form.instructions || null,
      max_score: Number(form.max_score),
      due_at: toIso(form.due_at),
    }

    try {
      const saved = editing
        ? await updateHomework(homework.id, payload, files)
        : await createHomework(payload, files)
      onSaved?.(saved)
      onClose()
    } catch (err) {
      const first = Object.values(err?.response?.data?.errors ?? {})[0]?.[0]
      setError(first ?? err?.response?.data?.message ?? 'Could not save this homework.')
    } finally {
      setSaving(false)
    }
  }

  return (
    <Modal
      open={open}
      onClose={onClose}
      title={editing ? 'Edit homework' : 'Set homework'}
      description={editing ? 'Only the content and deadline can change.' : 'It stays a draft until you publish it.'}
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

        <Textarea
          label="Instructions"
          name="instructions"
          value={form.instructions}
          onChange={set('instructions')}
          rows={4}
          maxLength={5000}
          hint="Optional, up to 5000 characters."
        />

        <div className="grid gap-4 sm:grid-cols-2">
          <Input
            label="Maximum score"
            name="max_score"
            type="number"
            min="1"
            max="100"
            value={form.max_score}
            onChange={set('max_score')}
            required
          />
          <Input label="Deadline" name="due_at" type="datetime-local" value={form.due_at} onChange={set('due_at')} required />
        </div>

        <AttachmentPicker
          files={files}
          onChange={setFiles}
          label={editing ? 'Add more documents' : 'Attach the homework document'}
        />

        {editing && homework.attachments?.length ? (
          <AttachmentList attachments={homework.attachments} label="Already attached" />
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
