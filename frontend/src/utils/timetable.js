/**
 * Timetable helpers — pure functions for pivoting backend entries into a grid
 * and for assigning consistent subject colors across the app.
 */

export const TIMETABLE_DAYS = [
  { key: 'monday', label: 'Monday', day: 1 },
  { key: 'tuesday', label: 'Tuesday', day: 2 },
  { key: 'wednesday', label: 'Wednesday', day: 3 },
  { key: 'thursday', label: 'Thursday', day: 4 },
  { key: 'friday', label: 'Friday', day: 5 },
]

const PALETTE = [
  { chip: 'bg-indigo-50 text-indigo-700 ring-indigo-600/20', dot: 'bg-indigo-500' },
  { chip: 'bg-violet-50 text-violet-700 ring-violet-600/20', dot: 'bg-violet-500' },
  { chip: 'bg-sky-50 text-sky-700 ring-sky-600/20', dot: 'bg-sky-500' },
  { chip: 'bg-emerald-50 text-emerald-700 ring-emerald-600/20', dot: 'bg-emerald-500' },
  { chip: 'bg-amber-50 text-amber-700 ring-amber-600/20', dot: 'bg-amber-500' },
  { chip: 'bg-rose-50 text-rose-700 ring-rose-600/20', dot: 'bg-rose-500' },
  { chip: 'bg-teal-50 text-teal-700 ring-teal-600/20', dot: 'bg-teal-500' },
  { chip: 'bg-fuchsia-50 text-fuchsia-700 ring-fuchsia-600/20', dot: 'bg-fuchsia-500' },
]

/**
 * Deterministic color pair for a subject name, so the same subject always
 * renders with the same color everywhere (timetable, grades, assignments).
 *
 * @returns {{ chip: string, dot: string }}
 */
export function subjectPalette(name = '') {
  let hash = 0
  const value = String(name ?? '')
  for (let i = 0; i < value.length; i += 1) {
    hash = (hash * 31 + value.charCodeAt(i)) >>> 0
  }
  return PALETTE[hash % PALETTE.length]
}
