import { useState } from 'react'
import {
  createEvent,
  updateEvent,
} from '../../services/eventService.js'
import {
  EVENT_AUDIENCES,
  EVENT_TYPES,
  toLocalInput,
} from './eventOptions.js'
import { Modal } from '../ui/Modal.jsx'
import { Button } from '../ui/Button.jsx'
import { Input } from '../ui/Input.jsx'
import { Select } from '../ui/Select.jsx'
import { Textarea } from '../ui/Textarea.jsx'
import { ErrorDisplay } from '../forms/ErrorDisplay.jsx'

const blank = {
  title: '',
  description: '',
  type: 'assembly',
  starts_at: '',
  ends_at: '',
  all_day: false,
  location: '',
  audience: 'all',
}

const fromEvent = (event) => ({
  title: event.title ?? '',
  description: event.description ?? '',
  type: event.type ?? 'other',
  starts_at: toLocalInput(event.starts_at),
  ends_at: toLocalInput(event.ends_at),
  all_day: Boolean(event.all_day),
  location: event.location ?? '',
  audience: event.audience ?? 'all',
})

/**
 * Create or edit a school event.
 *
 * Saving always leaves the event as a draft; publishing is a separate,
 * deliberate action because that is when the audience is notified.
 */
export function EventForm({ open, onClose, event, onSaved }) {
  const [form, setForm] = useState(() => (event ? fromEvent(event) : blank))
  const [error, setError] = useState(null)
  const [errors, setErrors] = useState({})
  const [submitting, setSubmitting] = useState(false)

  const set = (field) => (handler) => {
    const target = handler?.target ?? handler
    const value = target?.type === 'checkbox' ? target.checked : target?.value ?? handler
    setForm((current) => ({ ...current, [field]: value }))
  }

  const submit = async (submitEvent) => {
    submitEvent.preventDefault()
    setSubmitting(true)
    setError(null)
    setErrors({})

    const payload = {
      title: form.title,
      description: form.description || null,
      type: form.type,
      starts_at: form.starts_at ? new Date(form.starts_at).toISOString() : null,
      ends_at: form.ends_at ? new Date(form.ends_at).toISOString() : null,
      all_day: form.all_day,
      location: form.location || null,
      audience: form.audience,
    }

    try {
      const saved = event ? await updateEvent(event.id, payload) : await createEvent(payload)
      onSaved(saved)
    } catch (err) {
      setError(err?.response?.data?.message ?? 'Could not save the event.')
      setErrors(err?.response?.data?.errors ?? {})
    } finally {
      setSubmitting(false)
    }
  }

  return (
    <Modal
      open={open}
      onClose={onClose}
      title={event ? 'Edit event' : 'New event'}
      description="Saved as a draft until you publish it."
    >
      <form onSubmit={submit} className="space-y-3">
        <ErrorDisplay message={error} />

        <Input
          label="Title"
          name="title"
          value={form.title}
          error={errors.title?.[0]}
          placeholder="e.g. First Semester Examinations"
          onChange={set('title')}
        />

        <Textarea
          label="Description"
          name="description"
          rows={3}
          value={form.description}
          error={errors.description?.[0]}
          placeholder="Anything the audience should know."
          onChange={set('description')}
        />

        <div className="grid gap-3 sm:grid-cols-2">
          <Select label="Type" name="type" value={form.type} error={errors.type?.[0]} onChange={set('type')}>
            {EVENT_TYPES.map((option) => (
              <option key={option.value} value={option.value}>
                {option.label}
              </option>
            ))}
          </Select>

          <Select
            label="Audience"
            name="audience"
            value={form.audience}
            error={errors.audience?.[0]}
            onChange={set('audience')}
          >
            {EVENT_AUDIENCES.map((option) => (
              <option key={option.value} value={option.value}>
                {option.label}
              </option>
            ))}
          </Select>

          <Input
            label="Starts"
            name="starts_at"
            type="datetime-local"
            value={form.starts_at}
            error={errors.starts_at?.[0]}
            onChange={set('starts_at')}
          />

          <Input
            label="Ends"
            name="ends_at"
            type="datetime-local"
            value={form.ends_at}
            hint="Leave empty for a point in time."
            error={errors.ends_at?.[0]}
            onChange={set('ends_at')}
          />
        </div>

        <Input
          label="Location"
          name="location"
          value={form.location}
          error={errors.location?.[0]}
          placeholder="e.g. Hall A"
          onChange={set('location')}
        />

        <label className="flex items-center gap-2 text-sm text-slate-700">
          <input
            type="checkbox"
            name="all_day"
            checked={form.all_day}
            onChange={set('all_day')}
            className="size-4 rounded border-slate-300 text-brand-600 focus:ring-brand-500"
          />
          All day
        </label>

        <div className="flex justify-end gap-2 pt-1">
          <Button type="button" variant="secondary" onClick={onClose}>
            Cancel
          </Button>
          <Button type="submit" loading={submitting}>
            {event ? 'Save changes' : 'Create draft'}
          </Button>
        </div>
      </form>
    </Modal>
  )
}
