/**
 * Attendance helpers — status metadata and pure functions.
 */

export const ATTENDANCE_STATUS = {
  present: {
    label: 'Present',
    badge: 'success',
    dot: 'bg-emerald-500',
    active: 'bg-emerald-600 text-white',
    idle: 'bg-slate-100 text-slate-500 hover:bg-emerald-50 hover:text-emerald-700',
  },
  absent: {
    label: 'Absent',
    badge: 'danger',
    dot: 'bg-rose-500',
    active: 'bg-rose-600 text-white',
    idle: 'bg-slate-100 text-slate-500 hover:bg-rose-50 hover:text-rose-700',
  },
  late: {
    label: 'Late',
    badge: 'warning',
    dot: 'bg-amber-500',
    active: 'bg-amber-500 text-white',
    idle: 'bg-slate-100 text-slate-500 hover:bg-amber-50 hover:text-amber-700',
  },
  excused: {
    label: 'Excused',
    badge: 'info',
    dot: 'bg-sky-500',
    active: 'bg-sky-600 text-white',
    idle: 'bg-slate-100 text-slate-500 hover:bg-sky-50 hover:text-sky-700',
  },
}

export const ATTENDANCE_OPTIONS = ['present', 'absent', 'late', 'excused']

export function countStatuses(students = []) {
  const counts = { present: 0, absent: 0, late: 0, excused: 0, unmarked: 0 }
  for (const student of students) {
    if (!student.status) counts.unmarked += 1
    else counts[student.status] = (counts[student.status] ?? 0) + 1
  }
  return counts
}

/**
 * Today's date in YYYY-MM-DD (local time).
 */
export function todayString() {
  const now = new Date()
  const offset = now.getTimezoneOffset()
  const local = new Date(now.getTime() - offset * 60000)
  return local.toISOString().slice(0, 10)
}
