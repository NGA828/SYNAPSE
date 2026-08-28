import { Download, GraduationCap } from 'lucide-react'
import { useState } from 'react'
import { useAsync } from '../../hooks/useAsyncList.js'
import { getTranscript } from '../../services/transcriptService.js'
import { PageContainer } from '../../components/layout/PageContainer.jsx'
import { PageHeader } from '../../components/ui/PageHeader.jsx'
import { Button } from '../../components/ui/Button.jsx'
import { downloadTranscript } from '../../services/downloadService.js'
import { Card, CardBody, CardHeader } from '../../components/ui/Card.jsx'
import { Badge } from '../../components/ui/Badge.jsx'
import { Spinner } from '../../components/ui/Spinner.jsx'
import { EmptyState } from '../../components/dashboard/EmptyState.jsx'
import { formatDecimal } from '../../utils/formatters.js'
import { subjectPalette } from '../../utils/timetable.js'
import { cn } from '../../utils/cn.js'

export default function TranscriptPage() {
  const { data, loading, error } = useAsync(getTranscript)
  const years = data?.years ?? []

  const [downloading, setDownloading] = useState(false)
  const [downloadError, setDownloadError] = useState(null)

  const handleDownload = async () => {
    setDownloading(true)
    setDownloadError(null)

    try {
      await downloadTranscript()
    } catch {
      setDownloadError('Could not generate the PDF. Please try again.')
    } finally {
      setDownloading(false)
    }
  }

  return (
    <PageContainer>
      <div className="space-y-6">
        <PageHeader
          title="Transcript"
          description={`${data?.student?.matricule ?? 'Academic history'} · cumulative across all years`}
        >
          <Button onClick={handleDownload} loading={downloading}>
            <Download className="size-4" aria-hidden="true" />
            Download PDF
          </Button>
        </PageHeader>

        {downloadError ? <p className="text-sm text-rose-600">{downloadError}</p> : null}

        {loading ? (
          <div className="flex justify-center py-20">
            <Spinner className="size-8" />
          </div>
        ) : error ? (
          <Card className="p-6 text-sm text-slate-500">Could not load your transcript.</Card>
        ) : years.length === 0 ? (
          <EmptyState
            icon={GraduationCap}
            title="No academic history yet"
            description="Your yearly records will appear here."
          />
        ) : (
          <>
            <div className="overflow-hidden rounded-2xl bg-gradient-to-br from-brand-600 via-violet-600 to-brand-800">
              <div className="flex items-center justify-between p-6 text-white">
                <div>
                  <p className="text-xs uppercase tracking-wide text-brand-100">Cumulative average</p>
                  <p className="mt-1 text-5xl font-bold tracking-tight">
                    {formatDecimal(data?.cumulative)}
                    <span className="text-xl font-normal text-brand-200"> / 20</span>
                  </p>
                </div>
                <GraduationCap className="size-12 text-white/30" aria-hidden="true" />
              </div>
            </div>

            <div className="space-y-6">
              {years.map((year, index) => (
                <Card key={year.academic_year?.id ?? index}>
                  <CardHeader
                    title={year.academic_year?.name ?? 'Year'}
                    description={year.class?.name ?? ''}
                    action={<Badge variant="info" dot>Average {formatDecimal(year.average)}</Badge>}
                  />
                  <CardBody>
                    {year.grades.length === 0 ? (
                      <p className="text-sm text-slate-500">No grades recorded for this year.</p>
                    ) : (
                      <ul className="grid gap-2 sm:grid-cols-2">
                        {year.grades.map((grade) => (
                          <li
                            key={grade.subject}
                            className="flex items-center justify-between rounded-xl border border-slate-200 px-3 py-2"
                          >
                            <span className="flex items-center gap-2">
                              <span className={cn('size-2 rounded-full', subjectPalette(grade.subject).dot)} />
                              <span className="text-sm font-medium text-slate-800">{grade.subject}</span>
                            </span>
                            <span className="text-sm font-semibold tabular-nums text-slate-700">
                              {formatDecimal(grade.average)}
                            </span>
                          </li>
                        ))}
                      </ul>
                    )}
                  </CardBody>
                </Card>
              ))}
            </div>
          </>
        )}
      </div>
    </PageContainer>
  )
}
