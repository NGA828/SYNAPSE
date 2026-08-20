import { useAsync } from '../../hooks/useAsyncList.js'
import { getTimetable } from '../../services/studentService.js'
import { PageContainer } from '../../components/layout/PageContainer.jsx'
import { PageHeader } from '../../components/ui/PageHeader.jsx'
import { TimetableBoard } from '../../components/dashboard/TimetableBoard.jsx'
import { Card, CardBody } from '../../components/ui/Card.jsx'
import { Spinner } from '../../components/ui/Spinner.jsx'

export default function TimetablePage() {
  const { data, loading, error } = useAsync(getTimetable)

  return (
    <PageContainer>
      <div className="space-y-6">
        <PageHeader
          title="Timetable"
          description={`${data?.class?.name ?? 'Your class'} · ${data?.academic_year?.name ?? ''}`}
        />

        {loading ? (
          <div className="flex justify-center py-20">
            <Spinner className="size-8" />
          </div>
        ) : error ? (
          <Card className="p-6 text-sm text-slate-500">Could not load your timetable.</Card>
        ) : (
          <Card>
            <CardBody>
              <TimetableBoard entries={data?.entries} legend />
            </CardBody>
          </Card>
        )}
      </div>
    </PageContainer>
  )
}
