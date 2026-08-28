/**
 * Build an iCalendar (RFC 5545) feed from a teaching schedule so a teacher can
 * import their week into Google Calendar, Outlook or a phone calendar.
 *
 * Times are written as floating local times (no TZID): a lesson at 08:00 stays
 * at 08:00 wherever the calendar is read, which is what schools expect.
 */

const pad = (value) => String(value).padStart(2, '0')

const stamp = (date) =>
  `${date.getUTCFullYear()}${pad(date.getUTCMonth() + 1)}${pad(date.getUTCDate())}T${pad(
    date.getUTCHours(),
  )}${pad(date.getUTCMinutes())}${pad(date.getUTCSeconds())}Z`

const localStamp = (date, time) => {
  const [hour, minute] = String(time ?? '00:00').split(':')
  return `${date.getFullYear()}${pad(date.getMonth() + 1)}${pad(date.getDate())}T${pad(hour)}${pad(
    minute,
  )}00`
}

const escapeText = (value) =>
  String(value ?? '')
    .replace(/\\/g, '\\\\')
    .replace(/;/g, '\\;')
    .replace(/,/g, '\\,')
    .replace(/\r?\n/g, '\\n')

/** Fold long lines at 75 octets, as required by RFC 5545. */
const fold = (line) => {
  if (line.length <= 75) return line
  const parts = [line.slice(0, 75)]
  let rest = line.slice(75)
  while (rest.length > 74) {
    parts.push(` ${rest.slice(0, 74)}`)
    rest = rest.slice(74)
  }
  if (rest) parts.push(` ${rest}`)
  return parts.join('\r\n')
}

const ICS_DAYS = ['SU', 'MO', 'TU', 'WE', 'TH', 'FR', 'SA']

/** First date on/after `from` whose ISO weekday (Mon=1) is `isoDay`. */
function firstOccurrence(from, isoDay) {
  const date = new Date(from.getFullYear(), from.getMonth(), from.getDate())
  const currentIso = date.getDay() === 0 ? 7 : date.getDay()
  date.setDate(date.getDate() + ((Number(isoDay) - currentIso + 7) % 7))
  return date
}

/**
 * @param {{ entries?: Array, academicYear?: object, calendarName?: string }} input
 * @returns {string} the .ics document
 */
export function buildScheduleIcs({ entries = [], academicYear, calendarName = 'Teaching schedule' }) {
  const now = new Date()

  const startsOn = academicYear?.start_date ? new Date(academicYear.start_date) : null
  const endsOn = academicYear?.end_date ? new Date(academicYear.end_date) : null

  // Recur from the start of the year if it is still ahead of us, otherwise
  // from this week, and stop at the end of the year (default: 20 weeks).
  const anchor = startsOn && startsOn > now ? startsOn : now
  const until = endsOn ?? new Date(now.getTime() + 20 * 7 * 24 * 60 * 60 * 1000)
  // DTSTART is a floating local time, so UNTIL must be floating too (RFC 5545
  // §3.3.10: the value types have to match).
  const untilFloating = `${until.getFullYear()}${pad(until.getMonth() + 1)}${pad(until.getDate())}T235959`

  const lines = [
    'BEGIN:VCALENDAR',
    'VERSION:2.0',
    'PRODID:-//SYNAPSE//Teaching Schedule//EN',
    'CALSCALE:GREGORIAN',
    'METHOD:PUBLISH',
    `X-WR-CALNAME:${escapeText(calendarName)}`,
  ]

  entries.forEach((entry) => {
    const day = Number(entry.day)
    const first = firstOccurrence(anchor, day)
    const summary = `${entry.subject?.name ?? 'Lesson'} — ${entry.class?.name ?? ''}`.trim()

    lines.push(
      'BEGIN:VEVENT',
      `UID:synapse-lesson-${entry.id}-${day}@synapse.school`,
      `DTSTAMP:${stamp(now)}`,
      `DTSTART:${localStamp(first, entry.start)}`,
      `DTEND:${localStamp(first, entry.end)}`,
      `RRULE:FREQ=WEEKLY;BYDAY=${ICS_DAYS[day % 7]};UNTIL=${untilFloating}`,
      `SUMMARY:${escapeText(summary)}`,
      `LOCATION:${escapeText(entry.class?.name ?? '')}`,
      `DESCRIPTION:${escapeText(
        `${entry.subject?.name ?? 'Lesson'} with ${entry.class?.name ?? 'your class'} (${entry.start}–${entry.end}). Scheduled in SYNAPSE.`,
      )}`,
      'END:VEVENT',
    )
  })

  lines.push('END:VCALENDAR')

  return lines.map(fold).join('\r\n')
}

/** Hand an .ics document to the browser as a download. */
export function downloadIcs(filename, content) {
  const blob = new Blob([content], { type: 'text/calendar;charset=utf-8' })
  const href = URL.createObjectURL(blob)
  const link = document.createElement('a')

  link.href = href
  link.download = filename
  document.body.appendChild(link)
  link.click()
  link.remove()

  window.setTimeout(() => URL.revokeObjectURL(href), 1000)
}
