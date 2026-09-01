import { BarChart3, CalendarCheck2, ClipboardCheck, GraduationCap } from 'lucide-react'
import { useAsyncList } from '../../hooks/useAsyncList.js'
import { getOverview } from '../../services/analyticsService.js'
import { AtRiskRegister } from '../../components/analytics/AtRiskRegister.jsx'
import { MetricGrid } from '../../components/analytics/MetricGrid.jsx'
import { Card, CardBody, CardHeader } from '../../components/ui/Card.jsx'
import { BarList } from '../../components/ui/BarList.jsx'
import { PageHeader } from '../../components/ui/PageHeader.jsx'
import { Spinner } from '../../components/ui/Spinner.jsx'
import { EmptyState } from '../../components/dashboard/EmptyState.jsx'
import { ErrorDisplay } from '../../components/forms/ErrorDisplay.jsx'

/**
 * Admin analytics: whole-school picture plus the pastoral register.
 *
 * The numbers are computed from the grade book, homework and attendance
 * already in the system rather than entered again, so there is nothing for
 * staff to keep up to date.
 */
export default function AnalyticsPage() {
  const { data, loading, error } = useAsyncList(() => getOverview('/admin'), [])

  if (error) {
    return (
      <div className="space-y-6">
        <PageHeader title="Analytics" description="Performance, engagement and pastoral signals." />
        <ErrorDisplay message={error.response?.data?.message ?? 'Analytics could not be loaded.'} />
      </div>
    )
  }

  if (loading || !data?.data) {
    return (
      <div className="space-y-6">
        <PageHeader title="Analytics" description="Performance, engagement and pastoral signals." />
        <div className="flex justify-center py-16">
          <Spinner className="size-8" />
        </div>
      </div>
    )
  }

  const overview = data.data
  const attendanceItems = [
    { label: 'Present', value: overview.attendance.present, dot: 'bg-emerald-500' },
    { label: 'Late', value: overview.attendance.late, dot: 'bg-amber-500' },
    { label: 'Absent', value: overview.attendance.absent, dot: 'bg-rose-500' },
    { label: 'Excused', value: overview.attendance.excused, dot: 'bg-sky-500' },
  ]

  return (
    <div className="space-y-6">
      <PageHeader
        title="Analytics"
        description={
          overview.academic_year
            ? `${overview.academic_year.name} · ${overview.counts.students} students across ${overview.counts.classes} classes`
            : 'Performance, engagement and pastoral signals.'
        }
      />

      <MetricGrid
        counts={overview.counts}
        performance={overview.performance}
        attendance={overview.attendance}
        engagement={overview.engagement}
        atRisk={overview.at_risk}
      />

      <div className="grid gap-6 lg:grid-cols-2">
        <Card>
          <CardHeader
            title="Average by class"
            description="Classes with no enrolled students are left out rather than shown as zero."
          />
          <CardBody>
            {overview.by_class.length ? (
              <BarList items={overview.by_class} formatValue={(value) => (value === null ? '—' : value)} />
            ) : (
              <EmptyState
                icon={GraduationCap}
                title="No class averages yet"
                description="Averages appear once marks are entered."
              />
            )}
          </CardBody>
        </Card>

        <Card>
          <CardHeader
            title="Distribution of marks"
            description={`Across ${overview.performance.graded_students} graded student(s).`}
          />
          <CardBody>
            <BarList items={overview.distribution} formatValue={(value) => `${value} student(s)`} />
          </CardBody>
        </Card>

        <Card>
          <CardHeader
            title="Attendance"
            description={`${overview.attendance.rate}% present or late across ${overview.attendance.records} register(s).`}
          />
          <CardBody>
            <BarList items={attendanceItems} formatValue={(value) => `${value}`} />
          </CardBody>
        </Card>

        <Card>
          <CardHeader
            title="Engagement"
            description="Homework and quizzes, against what has been published."
          />
          <CardBody>
            <ul className="space-y-3 text-sm">
              <li className="flex items-center justify-between">
                <span className="flex items-center gap-2 text-slate-600">
                  <ClipboardCheck className="size-4 text-slate-400" aria-hidden="true" />
                  Homework submitted
                </span>
                <span className="font-medium text-slate-900">{overview.engagement.submission_rate}%</span>
              </li>
              <li className="flex items-center justify-between">
                <span className="flex items-center gap-2 text-slate-600">
                  <BarChart3 className="size-4 text-slate-400" aria-hidden="true" />
                  Quiz average
                </span>
                <span className="font-medium text-slate-900">
                  {overview.engagement.quiz_average === null ? '—' : `${overview.engagement.quiz_average}%`}
                </span>
              </li>
              <li className="flex items-center justify-between">
                <span className="flex items-center gap-2 text-slate-600">
                  <CalendarCheck2 className="size-4 text-slate-400" aria-hidden="true" />
                  Published homework
                </span>
                <span className="font-medium text-slate-900">{overview.engagement.assignments_published}</span>
              </li>
              <li className="flex items-center justify-between">
                <span className="flex items-center gap-2 text-slate-600">
                  <BarChart3 className="size-4 text-slate-400" aria-hidden="true" />
                  Quiz attempts
                </span>
                <span className="font-medium text-slate-900">{overview.engagement.quiz_attempts}</span>
              </li>
            </ul>
          </CardBody>
        </Card>
      </div>

      <AtRiskRegister path="/admin" />
    </div>
  )
}
