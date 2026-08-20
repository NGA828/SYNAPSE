import { CalendarClock, Clock, DoorOpen } from 'lucide-react'
import { useAsync } from '../../hooks/useAsyncList.js'
import { listStudentExams } from '../../services/examService.js'
import { PageContainer } from '../../components/layout/PageContainer.jsx'
import { PageHeader } from '../../components/ui/PageHeader.jsx'
import { Card, CardBody, CardHeader } from '../../components/ui/Card.jsx'
import { Spinner } from '../../components/ui/Spinner.jsx'
import { EmptyState } from '../../components/dashboard/EmptyState.jsx'
import { subjectPalette } from '../../utils/timetable.js'
import { cn } from '../../utils/cn.js'
import { formatDate } from '../../utils/formatters.js'

export default function ExamsPage() {
  const { data, loading, error } = useAsync(listStudentExams)
  const exams = data?.exams ?? []

  const grouped = exams.reduce((acc, exam) => {
    ;(acc[exam.date] ??= []).push(exam)
    return acc
  }, {})

  return (
    <PageContainer>
      <div className="space-y-6">
        <PageHeader
          title="Exam timetable"
          description={`${data?.class?.name ?? 'Your class'} · ${data?.academic_year?.name ?? ''}`}
        />

        {loading ? (
          <div className="flex justify-center py-20">
            <Spinner className="size-8" />
          </div>
        ) : error ? (
          <Card className="p-6 text-sm text-slate-500">Could not load your exam timetable.</Card>
        ) : exams.length === 0 ? (
          <EmptyState
            icon={CalendarClock}
            title="No exams scheduled"
            description="Your examination sessions will appear here."
          />
        ) : (
          <div className="space-y-6">
            {Object.entries(grouped).map(([date, sessions]) => (
              <Card key={date}>
                <CardHeader title={formatDate(date)} description={`${sessions.length} session${sessions.length === 1 ? '' : 's'}`} />
                <CardBody>
                  <ul className="divide-y divide-slate-100">
                    {sessions.map((exam) => {
                      const palette = subjectPalette(exam.subject?.name)
                      return (
                        <li key={exam.id} className="flex items-center justify-between py-3 first:pt-0 last:pb-0">
                          <div className="flex items-center gap-3">
                            <span
                              className={cn(
                                'flex size-10 items-center justify-center rounded-xl text-sm font-bold ring-1 ring-inset',
                                palette.chip,
                              )}
                            >
                              {String(exam.subject?.name ?? '').slice(0, 1).toUpperCase()}
                            </span>
                            <div>
                              <p className="text-sm font-semibold text-slate-900">{exam.subject?.name}</p>
                              {exam.semester ? <p className="text-xs text-slate-400">{exam.semester.name}</p> : null}
                            </div>
                          </div>
                          <div className="flex items-center gap-4 text-sm text-slate-500">
                            <span className="flex items-center gap-1.5">
                              <Clock className="size-4" aria-hidden="true" />
                              {exam.start} – {exam.end}
                            </span>
                            {exam.room ? (
                              <span className="hidden items-center gap-1.5 sm:flex">
                                <DoorOpen className="size-4" aria-hidden="true" />
                                {exam.room}
                              </span>
                            ) : null}
                          </div>
                        </li>
                      )
                    })}
                  </ul>
                </CardBody>
              </Card>
            ))}
          </div>
        )}
      </div>
    </PageContainer>
  )
}
