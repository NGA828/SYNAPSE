/**
 * Vocabulary for school events.
 *
 * Kept in its own module so the component files export only components — the
 * `react-refresh/only-export-components` rule is enforced as an error here.
 * The lists mirror `App\Models\Event::TYPES` / `::AUDIENCES`.
 */

export const EVENT_TYPES = [
  { value: 'assembly', label: 'Assembly' },
  { value: 'exam', label: 'Examination' },
  { value: 'holiday', label: 'Holiday' },
  { value: 'sports', label: 'Sports' },
  { value: 'meeting', label: 'Meeting' },
  { value: 'deadline', label: 'Deadline' },
  { value: 'other', label: 'Other' },
]

export const EVENT_AUDIENCES = [
  { value: 'all', label: 'Everyone' },
  { value: 'students', label: 'Students only' },
  { value: 'teachers', label: 'Teachers only' },
]

export const typeLabel = (value) =>
  EVENT_TYPES.find((option) => option.value === value)?.label ?? value

export const audienceLabel = (value) =>
  EVENT_AUDIENCES.find((option) => option.value === value)?.label ?? value

/**
 * An ISO timestamp into the `YYYY-MM-DDTHH:mm` a `datetime-local` input wants.
 * Returns an empty string when there is nothing to show, so an optional field
 * stays visibly empty rather than displaying the epoch.
 */
export function toLocalInput(value) {
  if (!value) return ''
  const date = new Date(value)
  if (Number.isNaN(date.getTime())) return ''

  const pad = (part) => String(part).padStart(2, '0')
  return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`
}
