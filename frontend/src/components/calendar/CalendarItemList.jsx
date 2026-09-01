import { BookOpen, CalendarDays, ClipboardCheck, FileText, HelpCircle } from 'lucide-react'
import { Badge } from '../ui/Badge.jsx'
import { EmptyState } from '../dashboard/EmptyState.jsx'
import { cn } from '../../utils/cn.js'

const kinds = {
  lesson: { label: 'Lesson', variant: 'info', icon: BookOpen },
  exam: { label: 'Exam', variant: 'danger', icon: ClipboardCheck },
  homework: { label: 'Homework', variant: 'warning', icon: FileText },
  quiz: { label: 'Quiz', variant: 'violet', icon: HelpCircle },
  event: { label: 'Event', variant: 'teal', icon: CalendarDays },
}

const timeOf = (value) => (value ? String(value).slice(11, 16) : '')

const dayLabel = (date) =>
  new Date(`${date}T00:00:00`).toLocaleDateString(undefined, {
    weekday: 'long',
    day: 'numeric',
    month: 'long',
  })

const groupByDay = (items) => {
  const groups = new Map()
  items.forEach((item) => {
    const date = String(item.starts_at ?? '').slice(0, 10)
    if (!groups.has(date)) groups.set(date, [])
    groups.get(date).push(item)
  })
  return [...groups.entries()].sort((a, b) => a[0].localeCompare(b[0]))
}

/**
 * Calendar items grouped into days.
 *
 * Every item carries the same shape and a `url` back to the screen that owns
 * it, so the calendar stays a view over other data rather than a second place
 * to edit anything.
 */
export function CalendarItemList({ items, loading }) {
  if (loading) {
    return <p className="py-10 text-center text-sm text-slate-500">Loading your calendar…</p>
  }

  if (!items?.length) {
    return (
      <EmptyState
        icon={CalendarDays}
        title="Nothing scheduled"
        description="No lessons, exams, deadlines or events fall in this range."
      />
    )
  }

  return (
    <div className="space-y-5">
      {groupByDay(items).map(([date, dayItems]) => (
        <section key={date}>
          <h3 className="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-500">
            {dayLabel(date)}
          </h3>

          <ul className="divide-y divide-slate-100 rounded-xl border border-slate-200">
            {dayItems.map((item) => {
              const meta = kinds[item.kind] ?? kinds.event
              const Icon = meta.icon

              return (
                <li key={`${item.kind}-${item.id}`}>
                  <div className="flex items-center gap-3 px-4 py-3">
                    <span
                      className={cn(
                        'flex size-9 shrink-0 items-center justify-center rounded-full bg-slate-100 text-slate-600',
                      )}
                    >
                      <Icon className="size-4" aria-hidden="true" />
                    </span>

                    <span className="min-w-0 flex-1">
                      <span className="block truncate text-sm font-medium text-slate-900">
                        {item.title}
                      </span>
                      {item.subtitle ? (
                        <span className="block truncate text-xs text-slate-500">
                          {item.subtitle}
                        </span>
                      ) : null}
                    </span>

                    <span className="shrink-0 text-right">
                      <span className="block text-xs font-medium text-slate-600">
                        {item.all_day
                          ? 'All day'
                          : item.ends_at && item.ends_at !== item.starts_at
                            ? `${timeOf(item.starts_at)}–${timeOf(item.ends_at)}`
                            : timeOf(item.starts_at)}
                      </span>
                      <Badge variant={meta.variant} className="mt-1">
                        {meta.label}
                      </Badge>
                    </span>
                  </div>
                </li>
              )
            })}
          </ul>
        </section>
      ))}
    </div>
  )
}
