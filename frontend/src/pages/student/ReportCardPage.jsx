import { Printer, Trophy } from 'lucide-react'
import { useAsync } from '../../hooks/useAsyncList.js'
import { useTenant } from '../../hooks/useTenant.js'
import { getReportCard } from '../../services/studentService.js'
import { PageContainer } from '../../components/layout/PageContainer.jsx'
import { PageHeader } from '../../components/ui/PageHeader.jsx'
import { ReportCardTable } from '../../components/dashboard/ReportCardTable.jsx'
import { ProgressRing } from '../../components/ui/ProgressRing.jsx'
import { Card, CardBody } from '../../components/ui/Card.jsx'
import { Badge } from '../../components/ui/Badge.jsx'
import { Button } from '../../components/ui/Button.jsx'
import { Spinner } from '../../components/ui/Spinner.jsx'
import { formatDate } from '../../utils/formatters.js'

export default function ReportCardPage() {
  const { data, loading, error } = useAsync(getReportCard)
  const { school } = useTenant()

  return (
    <PageContainer>
      <div className="space-y-6">
        <PageHeader
          title="Report card"
          description={`${data?.class?.name ?? 'Your class'} · ${data?.academic_year?.name ?? ''}`}
        >
          <Button variant="secondary" onClick={() => window.print()}>
            <Printer className="size-4" aria-hidden="true" />
            Print
          </Button>
        </PageHeader>

        {loading ? (
          <div className="flex justify-center py-20">
            <Spinner className="size-8" />
          </div>
        ) : error ? (
          <Card className="p-6 text-sm text-slate-500">Could not load your report card.</Card>
        ) : (
          <Card variant="elevated" className="overflow-hidden">
            {/* Document header */}
            <div className="flex flex-col gap-4 border-b border-slate-200 bg-slate-50/70 px-6 py-5 sm:flex-row sm:items-center sm:justify-between">
              <div className="flex items-center gap-4">
                {school?.logo ? (
                  <img src={school.logo} alt={school?.name} className="size-12 rounded-xl border border-slate-200 object-cover" />
                ) : (
                  <span className="flex size-12 items-center justify-center rounded-xl bg-gradient-to-br from-brand-600 to-violet-600 text-lg font-bold text-white">
                    {String(school?.name ?? 'S').slice(0, 1).toUpperCase()}
                  </span>
                )}
                <div>
                  <p className="text-sm font-semibold text-slate-900">{school?.name ?? 'SYNAPSE'}</p>
                  <p className="text-xs text-slate-500">Official term report card</p>
                </div>
              </div>
              <div className="text-sm text-slate-500">
                <p>{data?.student?.matricule}</p>
                <p>{data?.class?.name} · {data?.academic_year?.name}</p>
                <p className="text-xs text-slate-400">Issued {formatDate(new Date().toISOString())}</p>
              </div>
            </div>

            <CardBody className="space-y-6">
              {/* Summary strip */}
              <div className="flex flex-col items-center justify-between gap-5 rounded-2xl border border-slate-100 bg-white p-5 sm:flex-row">
                <div className="flex items-center gap-5">
                  <ProgressRing value={data?.average ?? 0} max={20} size={96} label="Average" />
                  {data?.rank ? (
                    <div className="flex flex-col gap-2">
                      <Badge variant="success" dot className="self-start">
                        <Trophy className="size-3" aria-hidden="true" />
                        Rank {data.rank} of {data.class_size}
                      </Badge>
                      <p className="text-xs text-slate-500">
                        Position in {data?.class?.name ?? 'your class'} by term average.
                      </p>
                    </div>
                  ) : null}
                </div>
              </div>

              {/* Grades */}
              <div>
                <div className="mb-3">
                  <h2 className="text-base font-semibold text-slate-900">Subject grades</h2>
                  <p className="text-sm text-slate-500">Component scores and term averages</p>
                </div>
                <ReportCardTable grades={data?.grades} />
              </div>
            </CardBody>
          </Card>
        )}
      </div>
    </PageContainer>
  )
}
