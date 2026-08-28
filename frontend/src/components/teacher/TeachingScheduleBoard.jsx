import { TIMETABLE_DAYS, subjectPalette } from '../../utils/timetable.js'
import { cn } from '../../utils/cn.js'
import { EmptyState } from '../dashboard/EmptyState.jsx'

const todayDayNumber = (() => {
  const jsDay = new Date().getDay() // 0 Sun … 6 Sat
  return jsDay === 0 ? 7 : jsDay // Mon=1 … Sun=7
})()

/**
 * A teacher's weekly schedule as a time × day grid.
 *
 * Differs from the student/class board in two ways that matter for teachers:
 * every chip carries the class it is taught to, and a slot can legitimately
 * hold more than one entry — which is a clash the teacher needs to see, so
 * they are stacked and flagged rather than silently dropped.
 */
export function TeachingScheduleBoard({ entries = [], conflicts = [] }) {
  const list = entries ?? []

  const conflictKeys = new Set((conflicts ?? []).map((clash) => `${clash.day}|${clash.start}`))

  const slotStarts = [...new Set(list.map((entry) => entry.start))].sort((a, b) => a.localeCompare(b))

  const entriesAt = (start, day) =>
    list.filter((entry) => entry.start === start && Number(entry.day) === day)

  const endFor = (start) => list.find((entry) => entry.start === start)?.end ?? ''

  if (list.length === 0) {
    return (
      <EmptyState
        title="No lessons scheduled"
        description="Once the office adds your subjects to a class timetable, your week appears here."
      />
    )
  }

  return (
    <div className="overflow-x-auto">
      <table className="w-full min-w-[46rem] border-separate border-spacing-1.5">
        <caption className="sr-only">Weekly teaching schedule</caption>
        <thead>
          <tr>
            <th scope="col" className="w-24 px-2 py-1 text-left text-xs font-semibold uppercase tracking-wide text-slate-400">
              Time
            </th>
            {TIMETABLE_DAYS.map((day) => {
              const isToday = todayDayNumber === day.day
              return (
                <th key={day.key} scope="col" className="px-1 py-1">
                  <span
                    className={cn(
                      'flex items-center justify-center gap-1.5 rounded-lg px-2 py-1.5 text-xs font-semibold',
                      isToday ? 'bg-brand-600 text-white' : 'text-slate-500',
                    )}
                  >
                    {day.label}
                    {isToday ? <span className="size-1.5 rounded-full bg-white" /> : null}
                  </span>
                </th>
              )
            })}
          </tr>
        </thead>
        <tbody>
          {slotStarts.map((start) => (
            <tr key={start}>
              <th scope="row" className="whitespace-nowrap px-2 py-1 text-left align-top">
                <span className="block text-xs font-semibold text-slate-600">{start}</span>
                <span className="block text-[10px] font-normal text-slate-400">{endFor(start)}</span>
              </th>

              {TIMETABLE_DAYS.map((day) => {
                const cell = entriesAt(start, day.day)
                const clashing = cell.length > 1 || conflictKeys.has(`${day.day}|${start}`)

                if (cell.length === 0) {
                  return (
                    <td key={day.key} className="px-1 py-1 align-top">
                      <span className="flex min-h-14 items-center justify-center text-slate-200">—</span>
                    </td>
                  )
                }

                return (
                  <td key={day.key} className="px-1 py-1 align-top">
                    <div className="flex flex-col gap-1">
                      {cell.map((entry) => {
                        const palette = subjectPalette(entry.subject?.name)
                        return (
                          <span
                            key={entry.id}
                            className={cn(
                              'flex min-h-14 flex-col justify-center rounded-lg px-2.5 py-1.5 ring-1 ring-inset',
                              palette.chip,
                              clashing && 'ring-2 ring-rose-500',
                            )}
                          >
                            <span className="truncate text-xs font-semibold">{entry.subject?.name}</span>
                            <span className="truncate text-[11px] font-medium opacity-80">
                              {entry.class?.name}
                            </span>
                            <span className="text-[10px] opacity-70">
                              {entry.start} – {entry.end}
                            </span>
                          </span>
                        )
                      })}
                      {clashing ? (
                        <span className="text-[10px] font-semibold uppercase tracking-wide text-rose-600">
                          Clash
                        </span>
                      ) : null}
                    </div>
                  </td>
                )
              })}
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  )
}
