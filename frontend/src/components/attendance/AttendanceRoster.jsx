import { ATTENDANCE_OPTIONS, ATTENDANCE_STATUS, countStatuses } from '../../utils/attendance.js'
import { cn } from '../../utils/cn.js'
import { Avatar } from '../ui/Avatar.jsx'
import { Badge } from '../ui/Badge.jsx'
import { EmptyState } from '../dashboard/EmptyState.jsx'

export function AttendanceRoster({ students = [], marks = {}, editable = true, onChange }) {
  const statusFor = (id) => marks[id] ?? null
  const counts = countStatuses(students.map((student) => ({ ...student, status: statusFor(student.id) })))

  if (!students || students.length === 0) {
    return <EmptyState title="No students" description="Students enrolled in this class will appear here." />
  }

  return (
    <div>
      {/* Summary chips */}
      <div className="mb-4 grid grid-cols-2 gap-2 sm:grid-cols-5">
        {['present', 'absent', 'late', 'excused', 'unmarked'].map((key) => {
          const meta = ATTENDANCE_STATUS[key]
          return (
            <div
              key={key}
              className="flex items-center justify-between rounded-xl border border-slate-200 bg-white px-3 py-2"
            >
              <span className="flex items-center gap-1.5 text-xs font-medium text-slate-500">
                {key !== 'unmarked' ? <span className={cn('size-2 rounded-full', meta?.dot)} /> : null}
                {key === 'unmarked' ? 'Unmarked' : meta?.label}
              </span>
              <span className="text-sm font-bold tabular-nums text-slate-800">{counts[key]}</span>
            </div>
          )
        })}
      </div>

      <ul className="divide-y divide-slate-100">
        {students.map((student) => {
          const status = statusFor(student.id)
          return (
            <li
              key={student.id}
              className="flex flex-col gap-2 py-3 sm:flex-row sm:items-center sm:justify-between"
            >
              <div className="flex min-w-0 items-center gap-3">
                <Avatar name={student.name} size="sm" />
                <div className="min-w-0">
                  <p className="truncate text-sm font-medium text-slate-800">{student.name}</p>
                  <p className="font-mono text-xs text-slate-400">{student.matricule}</p>
                </div>
                {status && !editable ? (
                  <Badge variant={ATTENDANCE_STATUS[status].badge} dot className="hidden sm:inline-flex">
                    {ATTENDANCE_STATUS[status].label}
                  </Badge>
                ) : null}
              </div>

              {editable ? (
                <div className="flex justify-end">
                  <div className="flex gap-1 rounded-xl bg-slate-100 p-1">
                    {ATTENDANCE_OPTIONS.map((option) => {
                      const meta = ATTENDANCE_STATUS[option]
                      const active = status === option
                      return (
                        <button
                          key={option}
                          type="button"
                          onClick={() => onChange(student.id, active ? null : option)}
                          className={cn(
                            'rounded-lg px-2.5 py-1.5 text-xs font-semibold transition',
                            active ? meta.active : meta.idle,
                          )}
                          aria-pressed={active}
                        >
                          {meta.label}
                        </button>
                      )
                    })}
                  </div>
                </div>
              ) : (
                <div className="flex">
                  {status ? (
                    <Badge variant={ATTENDANCE_STATUS[status].badge} dot>
                      {ATTENDANCE_STATUS[status].label}
                    </Badge>
                  ) : (
                    <Badge variant="neutral">Unmarked</Badge>
                  )}
                </div>
              )}
            </li>
          )
        })}
      </ul>
    </div>
  )
}
