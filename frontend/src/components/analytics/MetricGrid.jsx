import { CalendarCheck2, ClipboardCheck, GraduationCap, HelpCircle, ShieldAlert, TrendingUp, Users } from 'lucide-react'
import { StatCard } from '../dashboard/StatCard.jsx'

const percent = (value) => (value === null || value === undefined ? '—' : `${value}%`)
const score = (value) => (value === null || value === undefined ? '—' : value)

/**
 * The headline numbers. Values that cannot be computed yet render as an em dash
 * rather than 0, because "no data" and "zero" mean very different things to an
 * administrator deciding where to look.
 */
export function MetricGrid({ counts, performance, attendance, engagement, atRisk }) {
  return (
    <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
      <StatCard icon={Users} label="Students" value={counts.students} hint={`${counts.classes} classes`} />
      <StatCard
        icon={TrendingUp}
        label="Average mark"
        value={score(performance.average)}
        hint={`${percent(performance.pass_rate)} at or above the pass mark`}
        tone="violet"
      />
      <StatCard
        icon={CalendarCheck2}
        label="Attendance"
        value={percent(attendance.rate)}
        hint={`${attendance.absent} absence(s) recorded`}
        tone="teal"
      />
      <StatCard
        icon={ShieldAlert}
        label="At risk"
        value={atRisk.flagged}
        hint={`${atRisk.critical} critical · ${atRisk.monitored} monitored`}
        tone={atRisk.critical > 0 ? 'rose' : 'amber'}
      />
      <StatCard
        icon={ClipboardCheck}
        label="Homework submitted"
        value={percent(engagement.submission_rate)}
        hint={`${engagement.submissions} of ${engagement.assignments_published} expected per student`}
        tone="emerald"
      />
      <StatCard
        icon={HelpCircle}
        label="Quiz average"
        value={percent(engagement.quiz_average)}
        hint={`${engagement.quiz_attempts} attempt(s) across ${engagement.quizzes_published} paper(s)`}
        tone="sky"
      />
      <StatCard icon={GraduationCap} label="Teachers" value={counts.teachers} hint={`${counts.subjects} subjects`} />
      <StatCard
        icon={TrendingUp}
        label="Graded students"
        value={performance.graded_students}
        hint="Included in the average"
        tone="violet"
      />
    </div>
  )
}
