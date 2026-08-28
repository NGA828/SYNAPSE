import { AlertTriangle, CalendarDays, CalendarPlus, Clock, GraduationCap, Layers, Printer } from 'lucide-react'
import { Link } from 'react-router-dom'
import { useAsyncList } from '../../hooks/useAsyncList.js'
import { getTeacherTimetable } from '../../services/teacherService.js'
import { PageContainer } from '../../components/layout/PageContainer.jsx'
import { PageHeader } from '../../components/ui/PageHeader.jsx'
import { Button } from '../../components/ui/Button.jsx'
import { Badge } from '../../components/ui/Badge.jsx'
import { Card, CardBody, CardHeader } from '../../components/ui/Card.jsx'
import { Spinner } from '../../components/ui/Spinner.jsx'
import { StatCard } from '../../components/dashboard/StatCard.jsx'
import { TeachingScheduleBoard } from '../../components/teacher/TeachingScheduleBoard.jsx'
import { TIMETABLE_DAYS, subjectPalette } from '../../utils/timetable.js'
import { buildScheduleIcs, downloadIcs } from '../../utils/calendar.js'
import { cn } from '../../utils/cn.js'

const dayLabel = (day) => TIMETABLE_DAYS.find((entry) => entry.day === Number(day))?.label ?? '—'

const nowHhMm = () => {
  const now = new Date()
  return `${String(now.getHours()).padStart(2, '0')}:${String(now.getMinutes()).padStart(2, '0')}`
}

function LessonRow({ entry, state }) {
  const palette = subjectPalette(entry.subject?.name)

  return (
    <li
      className={cn(
        'flex items-center gap-3 rounded-xl border px-3 py-2.5',
        state === 'now' ? 'border-brand-300 bg-brand-50/60' : 'border-slate-200 bg-white',
        state === 'done' && 'opacity-60',
      )}
    >
      <span className={cn('flex w-16 shrink-0 flex-col rounded-lg px-2 py-1 text-center ring-1 ring-inset', palette.chip)}>
        <span className="text-xs font-bold">{entry.start}</span>
        <span className="text-[10px] opacity-70">{entry.end}</span>
      </span>

      <span className="min-w-0 flex-1">
        <span className="block truncate text-sm font-semibold text-slate-900">{entry.subject?.name}</span>
        <span className="block truncate text-xs text-slate-500">{entry.class?.name}</span>
      </span>

      {state === 'now' ? <Badge variant="success">In progress</Badge> : null}
      {state === 'next' ? <Badge variant="info">Up next</Badge> : null}

      {entry.class?.id && entry.subject?.id ? (
        <Link
          to={`/teacher/classes/${entry.class.id}/subjects/${entry.subject.id}`}
          className="shrink-0 text-xs font-semibold text-brand-600 hover:underline"
        >
          Open class
        </Link>
      ) : null}
    </li>
  )
}

export default function TimetablePage() {
  const { data, loading, error, reload } = useAsyncList(getTeacherTimetable)

  const entries = data?.entries ?? []
  const summary = data?.summary
  const today = data?.today ?? []
  const conflicts = data?.conflicts ?? []
  const next = data?.next

  const time = nowHhMm()
  const stateOf = (entry) => {
    if (entry.start <= time && time < entry.end) return 'now'
    if (next && entry.id === next.id && entry.day === next.day) return 'next'
    if (entry.end <= time) return 'done'
    return 'later'
  }

  const handleExport = () => {
    downloadIcs(
      'teaching-schedule.ics',
      buildScheduleIcs({
        entries,
        academicYear: data?.academic_year,
        calendarName: `Teaching schedule — ${data?.academic_year?.name ?? ''}`.trim(),
      }),
    )
  }

  return (
    <PageContainer>
      <div className="space-y-6">
        <PageHeader
          title="My schedule"
          description={
            data?.academic_year?.name
              ? `Weekly teaching timetable · ${data.academic_year.name}`
              : 'Your weekly teaching timetable'
          }
        >
          <Button variant="secondary" size="sm" onClick={handleExport} disabled={entries.length === 0}>
            <CalendarPlus className="size-4" aria-hidden="true" />
            Add to calendar
          </Button>
          <Button variant="secondary" size="sm" onClick={() => window.print()} disabled={entries.length === 0}>
            <Printer className="size-4" aria-hidden="true" />
            Print
          </Button>
        </PageHeader>

        {loading ? (
          <div className="flex justify-center py-20">
            <Spinner className="size-8" />
          </div>
        ) : error ? (
          <Card className="flex flex-col items-start gap-3 p-6">
            <p className="text-sm text-slate-500">
              {error?.response?.data?.message ?? 'Could not load your schedule.'}
            </p>
            <Button variant="secondary" size="sm" onClick={reload}>
              Try again
            </Button>
          </Card>
        ) : (
          <>
            <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
              <StatCard icon={CalendarDays} label="Lessons per week" value={summary?.lessons ?? 0} tone="brand" />
              <StatCard
                icon={Clock}
                label="Contact hours"
                value={`${summary?.hours_per_week ?? 0} h`}
                hint="Per week, across all classes"
                tone="violet"
              />
              <StatCard icon={GraduationCap} label="Classes taught" value={summary?.classes ?? 0} tone="teal" />
              <StatCard
                icon={Layers}
                label="Subjects"
                value={summary?.subjects ?? 0}
                hint={summary?.busiest_day ? `Busiest day: ${dayLabel(summary.busiest_day)}` : undefined}
                tone="amber"
              />
            </div>

            {conflicts.length > 0 ? (
              <Card className="border-rose-200 bg-rose-50/70 p-4">
                <div className="flex items-start gap-3">
                  <AlertTriangle className="mt-0.5 size-5 shrink-0 text-rose-600" aria-hidden="true" />
                  <div className="text-sm text-rose-800">
                    <p className="font-semibold">
                      {conflicts.length} scheduling clash{conflicts.length === 1 ? '' : 'es'} detected
                    </p>
                    <ul className="mt-1 space-y-0.5">
                      {conflicts.map((clash) => (
                        <li key={`${clash.day}-${clash.start}`}>
                          {dayLabel(clash.day)} at {clash.start} —{' '}
                          {clash.entries.map((entry) => `${entry.subject?.name} (${entry.class?.name})`).join(' vs ')}
                        </li>
                      ))}
                    </ul>
                    <p className="mt-1 text-xs text-rose-700">
                      Ask the administration to move one of these slots.
                    </p>
                  </div>
                </div>
              </Card>
            ) : null}

            <div className="grid gap-6 lg:grid-cols-3">
              <Card className="lg:col-span-2">
                <CardHeader title="This week" description="Every lesson you teach, by day and time." />
                <CardBody>
                  <TeachingScheduleBoard entries={entries} conflicts={conflicts} />
                </CardBody>
              </Card>

              <div className="space-y-6">
                <Card>
                  <CardHeader
                    title="Today"
                    description={`${today.length} lesson${today.length === 1 ? '' : 's'} scheduled`}
                  />
                  <CardBody>
                    {today.length === 0 ? (
                      <p className="text-sm text-slate-500">No lessons today. Enjoy the quiet.</p>
                    ) : (
                      <ul className="space-y-2">
                        {today.map((entry) => (
                          <LessonRow key={entry.id} entry={entry} state={stateOf(entry)} />
                        ))}
                      </ul>
                    )}
                  </CardBody>
                </Card>

                {next ? (
                  <Card>
                    <CardHeader title="Next lesson" />
                    <CardBody className="space-y-1">
                      <p className="text-lg font-semibold text-slate-900">{next.subject?.name}</p>
                      <p className="text-sm text-slate-500">{next.class?.name}</p>
                      <p className="text-sm font-medium text-brand-600">
                        {dayLabel(next.day)} · {next.start} – {next.end}
                      </p>
                      {next.class?.id && next.subject?.id ? (
                        <Link
                          to={`/teacher/classes/${next.class.id}/subjects/${next.subject.id}`}
                          className="inline-block pt-2 text-sm font-semibold text-brand-600 hover:underline"
                        >
                          Open gradebook & roster
                        </Link>
                      ) : null}
                    </CardBody>
                  </Card>
                ) : null}
              </div>
            </div>
          </>
        )}
      </div>
    </PageContainer>
  )
}
