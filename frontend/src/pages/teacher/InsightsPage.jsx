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
 * Teacher analytics.
 *
 * The same endpoint the admin uses, scoped by the backend to the classes and
 * subjects this teacher actually holds — so the numbers are comparable but
 * never include someone else's marks.
 */
export default function InsightsPage() {
  const { data, loading, error } = useAsyncList(() => getOverview('/teacher'), [])

  if (error) {
    return (
      <div className="space-y-6">
        <PageHeader title="My analytics" description="How your classes are tracking." />
        <ErrorDisplay message={error.response?.data?.message ?? 'Analytics could not be loaded.'} />
      </div>
    )
  }

  if (loading || !data?.data) {
    return (
      <div className="space-y-6">
        <PageHeader title="My analytics" description="How your classes are tracking." />
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
        title="My analytics"
        description={
          overview.scope.classes?.length
            ? `${overview.academic_year?.name ?? 'This year'} · ${overview.scope.classes.join(' · ')}`
            : 'How your classes are tracking.'
        }
      />

      {overview.counts.students === 0 ? (
        <Card>
          <CardBody>
            <EmptyState
              icon={GraduationCap}
              title="No students in your classes yet"
              description="Analytics appear once students are enrolled and marks are entered."
            />
          </CardBody>
        </Card>
      ) : (
        <>
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
                description="Only the classes you hold are shown."
              />
              <CardBody>
                {overview.by_class.length ? (
                  <BarList items={overview.by_class} formatValue={(value) => (value === null ? '—' : value)} />
                ) : (
                  <EmptyState
                    icon={GraduationCap}
                    title="No averages yet"
                    description="Averages appear once you enter marks."
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
                title="Homework and quizzes"
                description="Your own assignments and papers."
              />
              <CardBody>
                <ul className="space-y-3 text-sm">
                  <li className="flex items-center justify-between">
                    <span className="flex items-center gap-2 text-slate-600">
                      <ClipboardCheck className="size-4 text-slate-400" aria-hidden="true" />
                      Submissions received
                    </span>
                    <span className="font-medium text-slate-900">{overview.engagement.submissions}</span>
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
                      Quiz average
                    </span>
                    <span className="font-medium text-slate-900">
                      {overview.engagement.quiz_average === null ? '—' : `${overview.engagement.quiz_average}%`}
                    </span>
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

          <AtRiskRegister path="/teacher" />
        </>
      )}
    </div>
  )
}
