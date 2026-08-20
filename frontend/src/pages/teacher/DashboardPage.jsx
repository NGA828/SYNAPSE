import { Link } from 'react-router-dom'
import { ArrowRight, BookMarked, BookOpen, Users } from 'lucide-react'
import { useAuth } from '../../hooks/useAuth.js'
import { useAsyncList } from '../../hooks/useAsyncList.js'
import { getTeacherDashboard } from '../../services/teacherService.js'
import { PageContainer } from '../../components/layout/PageContainer.jsx'
import { WelcomeHeader } from '../../components/dashboard/WelcomeHeader.jsx'
import { StatCard } from '../../components/dashboard/StatCard.jsx'
import { AssignmentCard } from '../../components/dashboard/AssignmentCard.jsx'
import { Card } from '../../components/ui/Card.jsx'
import { Spinner } from '../../components/ui/Spinner.jsx'

export default function DashboardPage() {
  const { user } = useAuth()
  const { data, loading, error } = useAsyncList(getTeacherDashboard)

  const summary = data?.summary
  const assignments = data?.assignments ?? []

  return (
    <PageContainer>
      <div className="space-y-6">
        <WelcomeHeader name={user?.name} subtitle="Teacher dashboard" role="teacher" />

        {loading ? (
          <div className="flex justify-center py-20">
            <Spinner className="size-8" />
          </div>
        ) : error ? (
          <Card className="p-6 text-sm text-slate-500">Could not load your dashboard.</Card>
        ) : (
          <>
            <div className="grid gap-4 sm:grid-cols-3">
              <StatCard icon={BookMarked} label="Assignments" value={summary?.assignments ?? 0} hint="Active this year" tone="violet" />
              <StatCard icon={Users} label="Students" value={summary?.students ?? 0} hint="Across your classes" tone="brand" />
              <StatCard icon={BookOpen} label="Classes" value={summary?.classes ?? 0} hint="You teach in" tone="teal" />
            </div>

            <section>
              <div className="mb-4 flex items-center justify-between">
                <div>
                  <h2 className="text-base font-semibold text-slate-900">My teaching assignments</h2>
                  <p className="text-sm text-slate-500">
                    Only the classes and subjects the administrator assigned to you.
                  </p>
                </div>
                <Link to="/teacher/assignments" className="inline-flex items-center gap-1 text-sm font-medium text-brand-600 hover:underline">
                  View all <ArrowRight className="size-3.5" aria-hidden="true" />
                </Link>
              </div>

              <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                {assignments.map((assignment) => (
                  <AssignmentCard
                    key={assignment.id}
                    klass={assignment.class.name}
                    subject={assignment.subject.name}
                    students={assignment.students_count}
                    to={`/teacher/classes/${assignment.class.id}/subjects/${assignment.subject.id}`}
                    gradesTo={`/teacher/classes/${assignment.class.id}/subjects/${assignment.subject.id}/grades`}
                  />
                ))}
              </div>
            </section>
          </>
        )}
      </div>
    </PageContainer>
  )
}
