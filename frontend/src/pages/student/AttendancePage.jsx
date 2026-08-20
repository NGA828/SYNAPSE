import { CalendarCheck2 } from 'lucide-react'
import { useAsync } from '../../hooks/useAsyncList.js'
import { getStudentAttendance } from '../../services/attendanceService.js'
import { PageContainer } from '../../components/layout/PageContainer.jsx'
import { PageHeader } from '../../components/ui/PageHeader.jsx'
import { ProgressRing } from '../../components/ui/ProgressRing.jsx'
import { Badge } from '../../components/ui/Badge.jsx'
import { Card, CardBody, CardHeader } from '../../components/ui/Card.jsx'
import { Spinner } from '../../components/ui/Spinner.jsx'
import { EmptyState } from '../../components/dashboard/EmptyState.jsx'
import { ATTENDANCE_STATUS } from '../../utils/attendance.js'
import { formatDate } from '../../utils/formatters.js'

export default function AttendancePage() {
  const { data, loading, error } = useAsync(getStudentAttendance)
  const summary = data?.summary
  const recent = data?.recent ?? []

  const statCards = [
    { key: 'present', label: 'Present' },
    { key: 'absent', label: 'Absent' },
    { key: 'late', label: 'Late' },
    { key: 'excused', label: 'Excused' },
  ]

  return (
    <PageContainer>
      <div className="space-y-6">
        <PageHeader
          title="My attendance"
          description={`${data?.academic_year?.name ?? 'This year'} · your attendance record`}
        />

        {loading ? (
          <div className="flex justify-center py-20">
            <Spinner className="size-8" />
          </div>
        ) : error ? (
          <Card className="p-6 text-sm text-slate-500">Could not load your attendance.</Card>
        ) : (
          <>
            <div className="overflow-hidden rounded-2xl bg-gradient-to-br from-brand-600 via-violet-600 to-brand-800">
              <div className="flex flex-col items-center gap-6 p-6 text-white sm:flex-row sm:justify-between">
                <div>
                  <p className="text-xs uppercase tracking-wide text-brand-100">Attendance rate</p>
                  <p className="mt-1 text-5xl font-bold tracking-tight">
                    {summary?.percentage ?? '—'}
                    <span className="text-xl font-normal text-brand-200">%</span>
                  </p>
                  <p className="mt-2 text-sm text-brand-100">
                    {summary?.present ?? 0} present · {summary?.absent ?? 0} absent · {summary?.late ?? 0} late of{' '}
                    {summary?.total ?? 0} days
                  </p>
                </div>
                <ProgressRing
                  value={summary?.percentage ?? 0}
                  max={100}
                  size={110}
                  label="Attendance"
                  sublabel="This year"
                />
              </div>
            </div>

            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
              {statCards.map(({ key, label }) => {
                const meta = ATTENDANCE_STATUS[key]
                return (
                  <Card key={key} className="p-5">
                    <span className={`inline-flex size-2 rounded-full ${meta.dot}`} />
                    <p className="mt-3 text-sm text-slate-500">{label}</p>
                    <p className="text-2xl font-bold tracking-tight text-slate-900">{summary?.[key] ?? 0}</p>
                  </Card>
                )
              })}
            </div>

            <Card>
              <CardHeader title="Recent attendance" description="Your latest recorded days" />
              <CardBody>
                {recent.length === 0 ? (
                  <EmptyState
                    icon={CalendarCheck2}
                    title="No records yet"
                    description="Your attendance history will appear here."
                  />
                ) : (
                  <ul className="divide-y divide-slate-100">
                    {recent.map((record) => {
                      const meta = ATTENDANCE_STATUS[record.status] ?? ATTENDANCE_STATUS.present
                      return (
                        <li key={record.id} className="flex items-center justify-between py-3 first:pt-0 last:pb-0">
                          <div className="flex items-center gap-3">
                            <span className="flex size-9 items-center justify-center rounded-xl bg-slate-100 text-slate-500">
                              <CalendarCheck2 className="size-4" aria-hidden="true" />
                            </span>
                            <div>
                              <p className="text-sm font-medium text-slate-800">{formatDate(record.date)}</p>
                              <p className="text-xs text-slate-400">{record.class}</p>
                            </div>
                          </div>
                          <Badge variant={meta.badge} dot>{meta.label}</Badge>
                        </li>
                      )
                    })}
                  </ul>
                )}
              </CardBody>
            </Card>
          </>
        )}
      </div>
    </PageContainer>
  )
}
