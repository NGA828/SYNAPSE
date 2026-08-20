import { TrendingUp } from 'lucide-react'
import { useAsync } from '../../hooks/useAsyncList.js'
import { getGrades } from '../../services/studentService.js'
import { PageContainer } from '../../components/layout/PageContainer.jsx'
import { PageHeader } from '../../components/ui/PageHeader.jsx'
import { GradesTable } from '../../components/dashboard/GradesTable.jsx'
import { ProgressRing } from '../../components/ui/ProgressRing.jsx'
import { BarList } from '../../components/ui/BarList.jsx'
import { Badge } from '../../components/ui/Badge.jsx'
import { Card, CardBody, CardHeader } from '../../components/ui/Card.jsx'
import { Spinner } from '../../components/ui/Spinner.jsx'
import { formatDecimal } from '../../utils/formatters.js'
import { subjectPalette } from '../../utils/timetable.js'

export default function GradesPage() {
  const { data, loading, error } = useAsync(getGrades)

  const grades = data?.grades ?? []
  const average = data?.average ?? null

  const subjectBars = grades.map((grade) => ({
    label: grade.subject,
    value: grade.average ?? 0,
    dot: subjectPalette(grade.subject).dot,
  }))

  const best = grades.reduce(
    (top, grade) => (grade.average != null && (top == null || grade.average > top.average) ? grade : top),
    null,
  )

  return (
    <PageContainer>
      <div className="space-y-6">
        <PageHeader
          title="My grades"
          description={`${data?.class?.name ?? 'Your class'} · ${data?.academic_year?.name ?? ''}`}
        />

        {loading ? (
          <div className="flex justify-center py-20">
            <Spinner className="size-8" />
          </div>
        ) : error ? (
          <Card className="p-6 text-sm text-slate-500">Could not load your grades.</Card>
        ) : (
          <>
            <div className="overflow-hidden rounded-2xl bg-gradient-to-br from-brand-600 via-violet-600 to-brand-800">
              <div className="flex flex-col gap-6 p-6 text-white sm:flex-row sm:items-center sm:justify-between">
                <div>
                  <p className="text-xs uppercase tracking-wide text-brand-100">Overall average</p>
                  <p className="mt-1 text-5xl font-bold tracking-tight">
                    {formatDecimal(average)}
                    <span className="text-xl font-normal text-brand-200"> / 20</span>
                  </p>
                  <p className="mt-2 text-sm text-brand-100">
                    {grades.length} subject{grades.length === 1 ? '' : 's'} assessed this term
                  </p>
                </div>
                <div className="flex flex-col gap-3">
                  {best ? (
                    <div className="rounded-2xl bg-white/10 px-5 py-3">
                      <p className="flex items-center gap-1.5 text-xs text-brand-100">
                        <TrendingUp className="size-3.5" aria-hidden="true" />
                        Best subject
                      </p>
                      <p className="mt-0.5 text-lg font-semibold">
                        {best.subject} · {formatDecimal(best.average)}
                      </p>
                    </div>
                  ) : null}
                  <Badge variant="info" dot className="self-start">Academic year {data?.academic_year?.name ?? '—'}</Badge>
                </div>
              </div>
            </div>

            <div className="grid gap-6 lg:grid-cols-3">
              <Card className="lg:col-span-1">
                <CardHeader title="Subject averages" description="Your score per subject" />
                <CardBody className="flex flex-col items-center gap-6">
                  <ProgressRing value={average ?? 0} max={20} size={120} label="Term average" />
                  <BarList className="w-full" items={subjectBars} formatValue={(value) => formatDecimal(value)} />
                </CardBody>
              </Card>

              <Card className="lg:col-span-2">
                <CardHeader
                  title="Component scores"
                  description="Test 1, Test 2 and exam — with term average"
                />
                <CardBody>
                  <GradesTable grades={grades} />
                </CardBody>
              </Card>
            </div>
          </>
        )}
      </div>
    </PageContainer>
  )
}
