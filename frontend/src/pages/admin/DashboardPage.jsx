import { Link } from 'react-router-dom'
import {
  BookMarked,
  CalendarDays,
  ClipboardList,
  GraduationCap,
  Megaphone,
  School,
  UserPlus,
  Users,
} from 'lucide-react'
import { useAuth } from '../../hooks/useAuth.js'
import { useAsyncList } from '../../hooks/useAsyncList.js'
import { getAdminDashboard, listAcademicYears } from '../../services/adminService.js'
import { PageContainer } from '../../components/layout/PageContainer.jsx'
import { WelcomeHeader } from '../../components/dashboard/WelcomeHeader.jsx'
import { StatCard } from '../../components/dashboard/StatCard.jsx'
import { BarList } from '../../components/ui/BarList.jsx'
import { Badge } from '../../components/ui/Badge.jsx'
import { Card, CardBody, CardHeader } from '../../components/ui/Card.jsx'
import { Spinner } from '../../components/ui/Spinner.jsx'

const QUICK_ACTIONS = [
  { to: '/admin/structure', icon: School, label: 'Academic structure', hint: 'Years, classes, subjects & assignments', tone: 'bg-brand-50 text-brand-600' },
  { to: '/admin/students', icon: UserPlus, label: 'Register students', hint: 'Create accounts and enroll in classes', tone: 'bg-violet-50 text-violet-600' },
  { to: '/admin/teachers', icon: GraduationCap, label: 'Register teachers', hint: 'Create accounts and assign staff', tone: 'bg-teal-50 text-teal-600' },
  { to: '/admin/timetable', icon: CalendarDays, label: 'Timetables', hint: 'Plan each class weekly schedule', tone: 'bg-amber-50 text-amber-600' },
  { to: '/admin/requests', icon: ClipboardList, label: 'Requests', hint: 'Review and approve student requests', tone: 'bg-emerald-50 text-emerald-600' },
  { to: '/admin/announcements', icon: Megaphone, label: 'Announcements', hint: 'Publish school-wide updates', tone: 'bg-sky-50 text-sky-600' },
]

export default function DashboardPage() {
  const { user } = useAuth()
  const { data, loading, error } = useAsyncList(getAdminDashboard)
  const { data: years } = useAsyncList(listAcademicYears)

  const summary = data?.summary
  const currentYear = years?.find((year) => year.is_current)

  const stats = [
    { icon: Users, label: 'Students', value: summary?.students ?? '—', tone: 'brand' },
    { icon: GraduationCap, label: 'Teachers', value: summary?.teachers ?? '—', tone: 'violet' },
    { icon: School, label: 'Classes', value: summary?.classes ?? '—', tone: 'teal' },
    { icon: BookMarked, label: 'Subjects', value: summary?.subjects ?? '—', tone: 'amber' },
  ]

  const overview = [
    { label: 'Students', value: summary?.students ?? 0, tone: 'brand' },
    { label: 'Teachers', value: summary?.teachers ?? 0, tone: 'violet' },
    { label: 'Classes', value: summary?.classes ?? 0, tone: 'teal' },
    { label: 'Subjects', value: summary?.subjects ?? 0, tone: 'amber' },
  ]

  return (
    <PageContainer>
      <div className="space-y-6">
        <WelcomeHeader name={user?.name} subtitle="Administrator dashboard" role="admin" />

        {loading ? (
          <div className="flex justify-center py-20">
            <Spinner className="size-8" />
          </div>
        ) : error ? (
          <Card className="p-6 text-sm text-slate-500">Could not load the dashboard.</Card>
        ) : (
          <>
            <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
              {stats.map((stat) => (
                <StatCard key={stat.label} {...stat} />
              ))}
            </div>

            <div className="grid gap-6 lg:grid-cols-3">
              <Card className="lg:col-span-2">
                <CardHeader title="Quick actions" description="Common tasks, one click away" />
                <CardBody className="grid gap-3 sm:grid-cols-2">
                  {QUICK_ACTIONS.map((action) => (
                    <Link
                      key={action.to}
                      to={action.to}
                      className="group flex items-start gap-3 rounded-xl border border-slate-200 p-4 transition hover:border-brand-300 hover:bg-brand-50/40"
                    >
                      <span className={`flex size-10 shrink-0 items-center justify-center rounded-xl ${action.tone}`}>
                        <action.icon className="size-5" aria-hidden="true" />
                      </span>
                      <span>
                        <span className="block text-sm font-semibold text-slate-800">{action.label}</span>
                        <span className="mt-0.5 block text-xs text-slate-500">{action.hint}</span>
                      </span>
                    </Link>
                  ))}
                </CardBody>
              </Card>

              <div className="space-y-6">
                <Card>
                  <CardHeader title="Academic year" />
                  <CardBody>
                    <div className="rounded-xl bg-gradient-to-br from-brand-600 to-violet-600 p-5 text-white">
                      <p className="text-xs uppercase tracking-wide text-brand-100">Current</p>
                      <p className="mt-1 text-2xl font-bold">{currentYear?.name ?? '—'}</p>
                    </div>
                    <p className="mt-4 flex items-center gap-2 text-xs text-slate-500">
                      <ClipboardList className="size-3.5" aria-hidden="true" />
                      <Badge variant="warning" dot>{summary?.pending_requests ?? 0} pending requests</Badge>
                    </p>
                  </CardBody>
                </Card>

                <Card>
                  <CardHeader title="School overview" />
                  <CardBody>
                    <BarList items={overview} />
                  </CardBody>
                </Card>
              </div>
            </div>
          </>
        )}
      </div>
    </PageContainer>
  )
}
