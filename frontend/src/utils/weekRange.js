/**
 * Monday-based week helpers for the calendar.
 *
 * Kept out of the component so the module exports no React values — the
 * `react-refresh/only-export-components` rule is enforced as an error here.
 */

export function isoDate(date) {
  const year = date.getFullYear()
  const month = String(date.getMonth() + 1).padStart(2, '0')
  const day = String(date.getDate()).padStart(2, '0')
  return `${year}-${month}-${day}`
}

/** Monday of the week containing `date`. */
export function mondayOf(date) {
  const copy = new Date(date)
  // getDay() is Sunday 0 … Saturday 6; shift it so Monday is 0.
  copy.setDate(copy.getDate() - ((copy.getDay() + 6) % 7))
  return copy
}

/** Sunday of the week containing `date`. */
export function sundayOf(date) {
  const copy = mondayOf(date)
  copy.setDate(copy.getDate() + 6)
  return copy
}

/** The same week, `weeks` forward (or back for a negative value). */
export function shiftWeek(date, weeks) {
  const copy = new Date(date)
  copy.setDate(copy.getDate() + weeks * 7)
  return copy
}

export function currentWeek() {
  const now = new Date()
  return { from: isoDate(mondayOf(now)), to: isoDate(sundayOf(now)) }
}
