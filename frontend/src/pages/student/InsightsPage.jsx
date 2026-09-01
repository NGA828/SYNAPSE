import { CalendarCheck2, ClipboardCheck, HelpCircle, ShieldAlert, TrendingUp } from 'lucide-react'
import { useAsyncList } from '../../hooks/useAsyncList.js'
import { getMyInsights } from '../../services/analyticsService.js'
import { SignalList } from '../../components/analytics/SignalList.jsx'
import { Card, CardBody, CardHeader } from '../../components/ui/Card.jsx'
import { PageHeader } from '../../components/ui/PageHeader.jsx'
import { Spinner } from '../../components/ui/Spinner.jsx'
import { ErrorDisplay } from '../../components/forms/ErrorDisplay.jsx'
import { Badge } from '../../components/ui/Badge.jsx'

const metric = (Icon, label, value, hint) => (
  <div className="rounded-xl border border-slate-200 bg-white p-4">
    <div className="flex items-center gap-2 text-slate-500">
      <Icon className="size-4" aria-hidden="true" />
      <span className="text-xs font-medium uppercase tracking-wide">{label}</span>
    </div>
    <p className="mt-2 text-2xl font-semibold text-slate-900">{value}</p>
    <p className="text-xs text-slate-500">{hint}</p>
  </div>
)

/**
 * A student's own signals.
 *
 * Framed as "here is what to look at", not as a verdict — the detail sentences
 * come from the same thresholds staff see, so a student can act on them.
 */
export default function InsightsPage() {
  const { data, loading, error } = useAsyncList(() => getMyInsights(), [])

  if (error) {
    return (
      <div className="space-y-6">
        <PageHeader title="My progress" description="How you are tracking this term." />
        <ErrorDisplay message={error.response?.data?.message ?? 'Your progress could not be loaded.'} />
      </div>
    )
  }

  if (loading || !data?.data) {
    return (
      <div className="space-y-6">
        <PageHeader title="My progress" description="How you are tracking this term." />
        <div className="flex justify-center py-16">
          <Spinner className="size-8" />
        </div>
      </div>
    )
  }

  const insights = data.data
  const attendanceText =
    insights.attendance === null || insights.attendance === undefined ? '—' : `${insights.attendance}%`
  const quizText =
    insights.quizzes?.percentage === null || insights.quizzes?.percentage === undefined
      ? '—'
      : `${insights.quizzes.percentage}%`

  return (
    <div className="space-y-6">
      <PageHeader
        title="My progress"
        description={
          insights.student?.class?.name
            ? `${insights.student.name} · ${insights.student.class.name}`
            : 'How you are tracking this term.'
        }
      >
        {insights.severity === 'critical' ? (
          <Badge variant="danger" dot>
            Needs attention
          </Badge>
        ) : insights.severity === 'warning' ? (
          <Badge variant="warning" dot>
            Worth a look
          </Badge>
        ) : (
          <Badge variant="success" dot>
            On track
          </Badge>
        )}
      </PageHeader>

      <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        {metric(TrendingUp, 'Average', insights.average === null ? '—' : insights.average, 'Out of 20')}
        {metric(CalendarCheck2, 'Attendance', attendanceText, 'Present or late')}
        {metric(
          ClipboardCheck,
          'Homework',
          insights.homework ? `${insights.homework.submitted}/${insights.homework.published}` : '—',
          insights.homework?.missing ? `${insights.homework.missing} past due` : 'All in',
        )}
        {metric(
          HelpCircle,
          'Quizzes',
          quizText,
          `${insights.quizzes?.attempts ?? 0} attempt(s)`,
        )}
      </div>

      <Card>
        <CardHeader
          title="What to look at"
          description="Raised by the same thresholds your teachers see, so there are no surprises."
          action={<ShieldAlert className="size-5 text-slate-400" aria-hidden="true" />}
        />
        <CardBody>
          <SignalList signals={insights.signals} />
        </CardBody>
      </Card>
    </div>
  )
}
