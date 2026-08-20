import { useState } from 'react'
import { Modal } from '../ui/Modal.jsx'
import { Select } from '../ui/Select.jsx'
import { Input } from '../ui/Input.jsx'
import { Button } from '../ui/Button.jsx'
import { ErrorDisplay } from '../forms/ErrorDisplay.jsx'
import { TIMETABLE_DAYS } from '../../utils/timetable.js'

export function TimetableEditorModal({
  open,
  onClose,
  mode,
  initial,
  subjects = [],
  onSave,
  onDelete,
  saving,
  error,
}) {
  const [form, setForm] = useState(() => ({
    day: initial?.day ?? '1',
    start: initial?.start ?? '',
    end: initial?.end ?? '',
    subject_id: initial?.subject_id ?? '',
  }))

  const setField = (field) => (event) =>
    setForm((current) => ({ ...current, [field]: event.target.value }))

  const submit = (event) => {
    event.preventDefault()
    onSave(form)
  }

  return (
    <Modal
      open={open}
      onClose={onClose}
      title={mode === 'edit' ? 'Edit class slot' : 'Add class slot'}
      description={
        mode === 'edit'
          ? 'Update the time or subject for this slot.'
          : 'Schedule a new subject for this class.'
      }
    >
      <form onSubmit={submit} className="space-y-4">
        <ErrorDisplay message={error} />

        <Select label="Day" value={form.day} onChange={setField('day')}>
          {TIMETABLE_DAYS.map((day) => (
            <option key={day.day} value={day.day}>
              {day.label}
            </option>
          ))}
        </Select>

        <div className="grid grid-cols-2 gap-3">
          <Input label="Start" type="time" value={form.start} onChange={setField('start')} />
          <Input label="End" type="time" value={form.end} onChange={setField('end')} />
        </div>

        <Select label="Subject" value={form.subject_id} onChange={setField('subject_id')}>
          <option value="">Select a subject…</option>
          {subjects.map((subject) => (
            <option key={subject.id} value={subject.id}>
              {subject.name}
            </option>
          ))}
        </Select>

        <div className="flex items-center justify-between gap-3 pt-2">
          {onDelete ? (
            <Button type="button" variant="dangerSoft" onClick={onDelete}>
              Delete
            </Button>
          ) : (
            <span />
          )}
          <div className="flex gap-2">
            <Button type="button" variant="secondary" onClick={onClose}>
              Cancel
            </Button>
            <Button type="submit" loading={saving}>
              Save slot
            </Button>
          </div>
        </div>
      </form>
    </Modal>
  )
}
