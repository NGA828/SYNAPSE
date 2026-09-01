import apiClient from './apiClient.js'

/**
 * The personal calendar.
 *
 * A read-only projection of the timetable, exams, homework due dates, quiz
 * deadlines and school events. Nothing is written here — each item links back
 * to the screen that owns it.
 *
 * Every item has the same shape:
 * `{ kind, id, title, subtitle, starts_at, ends_at, all_day, url }`
 */

/** Items between two `YYYY-MM-DD` dates. Omit them for the current week. */
export async function getCalendar({ from, to } = {}) {
  const { data } = await apiClient.get('/calendar', {
    params: { ...(from ? { from } : {}), ...(to ? { to } : {}) },
  })
  return data
}

/** Today only — the dashboard strip. */
export async function getToday() {
  const { data } = await apiClient.get('/calendar/today')
  return data
}
