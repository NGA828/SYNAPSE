import { Link } from 'react-router-dom'
import { ArrowRight, Award, BookOpen, ClipboardList, Megaphone } from 'lucide-react'
import { useAuth } from '../../hooks/useAuth.js'
import { useStudentDashboard } from '../../hooks/useStudentDashboard.js'
import { PageContainer } from '../../components/layout/PageContainer.jsx'
import { WelcomeHeader } from '../../components/dashboard/WelcomeHeader.jsx'
import { StatCard } from '../../components/dashboard/StatCard.jsx'
import { GradesTable } from '../../components/dashboard/GradesTable.jsx'
import { TimetableBoard } from '../../components/dashboard/TimetableBoard.jsx'
import { AnnouncementList } from '../../components/dashboard/AnnouncementList.jsx'
import { ProgressRing } from '../../components/ui/ProgressRing.jsx'
import { BarList } from '../../components/ui/BarList.jsx'
import { Card, CardBody, CardHeader } from '../../components/ui/Card.jsx'
import { Spinner } from '../../components/ui/Spinner.jsx'
import { formatDecimal } from '../../utils/formatters.js'

export default function DashboardPage() {
  const { user } = useAuth()
  const { data, loading, error } = useStudentDashboard()

  const student = data?.student
  const summary = data?.summary
  const subjectBars = (data?.grades ?? []).map((grade) => ({
    label: grade.subject,
    value: grade.average ?? 0,
  }))

  return (
    <PageContainer>
      <div className="space-y-6">
        <WelcomeHeader
          name={user?.name}
          subtitle={`${data?.class?.name ?? 'Student'} · Matricule ${student?.matricule ?? '—'}`}
          role="student"
        />

        {loading ? (
          <div className="flex justify-center py-20">
            <Spinner className="size-8" />
          </div>
        ) : error ? (
          <Card className="p-6 text-sm text-slate-500">
            We could not load your dashboard. Please try again.
          </Card>
        ) : (
          <>
            <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
              <StatCard
                icon={Award}
                label="Average"
                value={formatDecimal(summary?.average)}
                hint="Out of 20, across all subjects"
                tone="brand"
              />
              <StatCard icon={BookOpen} label="Subjects" value={summary?.subjects ?? 0} tone="violet" />
              <StatCard icon={ClipboardList} label="Pending requests" value={summary?.pending_requests ?? 0} tone="amber" />
              <StatCard icon={Megaphone} label="Announcements" value={summary?.announcements ?? 0} tone="teal" />
            </div>

            <div className="grid gap-6 lg:grid-cols-5">
              <Card className="lg:col-span-2">
                <CardHeader title="Performance" description="Your term average by subject" />
                <CardBody className="flex flex-col items-center gap-6">
                  <ProgressRing
                    value={summary?.average ?? 0}
                    max={20}
                    label="Overall average"
                    sublabel={`${summary?.subjects ?? 0} subjects assessed`}
                  />
                  <BarList
                    className="w-full"
                    items={subjectBars}
                    formatValue={(value) => formatDecimal(value)}
                  />
                </CardBody>
              </Card>

              <Card className="lg:col-span-3">
                <CardHeader
                  title="My grades"
                  action={
                    <Link to="/student/grades" className="inline-flex items-center gap-1 text-sm font-medium text-brand-600 hover:underline">
                      View all <ArrowRight className="size-3.5" aria-hidden="true" />
                    </Link>
                  }
                />
                <CardBody>
                  <GradesTable grades={data?.grades} />
                </CardBody>
              </Card>
            </div>

            <div className="grid gap-6 lg:grid-cols-2">
              <Card>
                <CardHeader
                  title="This week"
                  action={
                    <Link to="/student/timetable" className="text-sm font-medium text-brand-600 hover:underline">
                      Full timetable
                    </Link>
                  }
                />
                <CardBody>
                  <TimetableBoard entries={data?.timetable} />
                </CardBody>
              </Card>

              <Card>
                <CardHeader
                  title="Announcements"
                  action={
                    <Link to="/student/announcements" className="text-sm font-medium text-brand-600 hover:underline">
                      View all
                    </Link>
                  }
                />
                <CardBody>
                  <AnnouncementList announcements={data?.announcements} />
                </CardBody>
              </Card>
            </div>
          </>
        )}
      </div>
    </PageContainer>
  )
}
