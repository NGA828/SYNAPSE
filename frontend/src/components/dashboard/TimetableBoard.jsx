import { Plus } from 'lucide-react'
import { TIMETABLE_DAYS, subjectPalette } from '../../utils/timetable.js'
import { cn } from '../../utils/cn.js'
import { EmptyState } from './EmptyState.jsx'

const todayDayNumber = (() => {
  const jsDay = new Date().getDay() // 0 Sun … 6 Sat
  return jsDay === 0 ? 7 : jsDay // Mon=1 … Sun=7
})()

/**
 * A weekly timetable rendered as a real grid: time rows × day columns.
 *
 * Read-only for students; interactive for administrators (click an empty cell
 * to add, click a subject chip to edit).
 */
export function TimetableBoard({
  entries = [],
  interactive = false,
  legend = false,
  onSelectEntry,
  onSelectSlot,
}) {
  const list = entries ?? []

  const slotStarts = [...new Set(list.map((entry) => entry.start))].sort((a, b) =>
    a.localeCompare(b),
  )
  const entryAt = (start, day) =>
    list.find((entry) => entry.start === start && Number(entry.day) === day)

  const subjects = [
    ...new Map(list.map((entry) => [entry.subject?.id, entry.subject]).filter(([, s]) => s)),
  ].map(([, s]) => s)

  if (list.length === 0) {
    return (
      <EmptyState
        title="No timetable yet"
        description={
          interactive
            ? 'Add your first class slot to build this week’s schedule.'
            : 'Your weekly timetable will appear here.'
        }
      />
    )
  }

  return (
    <div>
      <div className="mb-4 flex flex-wrap items-center gap-2">
        <span className="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-600">
          {list.length} lesson{list.length === 1 ? '' : 's'}
        </span>
        <span className="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-600">
          {subjects.length} subject{subjects.length === 1 ? '' : 's'}
        </span>
        <span className="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-600">
          {slotStarts.length} time slot{slotStarts.length === 1 ? '' : 's'}
        </span>
      </div>

      <div className="overflow-x-auto">
        <table className="w-full min-w-[40rem] border-separate border-spacing-1.5">
          <thead>
            <tr>
              <th className="w-28 px-2 py-1 text-left text-xs font-semibold uppercase tracking-wide text-slate-400">
                Time
              </th>
              {TIMETABLE_DAYS.map((day) => {
                const isToday = todayDayNumber === day.day
                return (
                  <th key={day.key} className="px-1 py-1">
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
                <td className="whitespace-nowrap px-2 py-1 align-top">
                  <span className="block text-xs font-semibold text-slate-600">{start}</span>
                  <span className="block text-[10px] text-slate-400">
                    {entryAt(start, TIMETABLE_DAYS[0].day)?.end ?? ''}
                  </span>
                </td>
                {TIMETABLE_DAYS.map((day) => {
                  const entry = entryAt(start, day.day)

                  if (entry) {
                    const palette = subjectPalette(entry.subject?.name)
                    const chip = (
                      <span
                        className={cn(
                          'flex min-h-11 flex-col justify-center rounded-lg px-2.5 py-1.5 ring-1 ring-inset',
                          palette.chip,
                        )}
                      >
                        <span className="truncate text-xs font-semibold">{entry.subject?.name}</span>
                        <span className="text-[10px] opacity-70">
                          {entry.start} – {entry.end}
                        </span>
                      </span>
                    )

                    return (
                      <td key={day.key} className="px-1 py-1 align-top">
                        {interactive ? (
                          <button
                            type="button"
                            onClick={() => onSelectEntry?.(entry)}
                            className="block w-full text-left transition hover:brightness-95"
                            aria-label={`Edit ${entry.subject?.name} on ${day.label}`}
                          >
                            {chip}
                          </button>
                        ) : (
                          chip
                        )}
                      </td>
                    )
                  }

                  return (
                    <td key={day.key} className="px-1 py-1 align-top">
                      {interactive ? (
                        <button
                          type="button"
                          onClick={() => onSelectSlot?.(start, day.day)}
                          className="group flex min-h-11 w-full items-center justify-center rounded-lg border border-dashed border-transparent text-slate-300 transition hover:border-slate-300 hover:bg-slate-50 hover:text-slate-400"
                          aria-label={`Add class at ${start} on ${day.label}`}
                        >
                          <Plus className="size-4 opacity-0 transition group-hover:opacity-100" />
                        </button>
                      ) : (
                        <span className="flex min-h-11 items-center justify-center text-slate-200">—</span>
                      )}
                    </td>
                  )
                })}
              </tr>
            ))}
          </tbody>
        </table>
      </div>

      {legend && subjects.length > 0 ? (
        <div className="mt-4 flex flex-wrap items-center gap-2 border-t border-slate-100 pt-4">
          <span className="text-xs font-medium text-slate-400">Subjects:</span>
          {subjects.map((subject) => {
            const palette = subjectPalette(subject.name)
            return (
              <span
                key={subject.id}
                className={cn(
                  'inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-medium ring-1 ring-inset',
                  palette.chip,
                )}
              >
                <span className={cn('size-1.5 rounded-full', palette.dot)} />
                {subject.name}
              </span>
            )
          })}
        </div>
      ) : null}
    </div>
  )
}
