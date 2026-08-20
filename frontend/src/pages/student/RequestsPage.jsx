import { useAsyncList } from '../../hooks/useAsyncList.js'
import { getStudentRequests } from '../../services/requestService.js'
import { PageContainer } from '../../components/layout/PageContainer.jsx'
import { PageHeader } from '../../components/ui/PageHeader.jsx'
import { NewRequestForm } from '../../components/requests/NewRequestForm.jsx'
import { RequestCard } from '../../components/requests/RequestCard.jsx'
import { Card, CardBody, CardHeader } from '../../components/ui/Card.jsx'
import { Spinner } from '../../components/ui/Spinner.jsx'

export default function RequestsPage() {
  const { data: requests, loading, error, reload } = useAsyncList(getStudentRequests)

  return (
    <PageContainer>
      <div className="space-y-6">
        <PageHeader
          title="Requests"
          description="Submit document requests and track their progress."
        />

        <div className="grid gap-6 lg:grid-cols-3">
          <Card className="self-start lg:col-span-1">
            <CardHeader title="New request" description="Tell us what you need" />
            <CardBody>
              <NewRequestForm onCreated={reload} />
            </CardBody>
          </Card>

          <div className="space-y-4 lg:col-span-2">
            {loading ? (
              <div className="flex justify-center py-20">
                <Spinner className="size-8" />
              </div>
            ) : error ? (
              <Card className="p-6 text-sm text-slate-500">Could not load your requests.</Card>
            ) : requests?.length === 0 ? (
              <Card className="p-10 text-center text-sm text-slate-500">
                You haven&apos;t submitted any requests yet.
              </Card>
            ) : (
              requests?.map((request) => <RequestCard key={request.id} request={request} />)
            )}
          </div>
        </div>
      </div>
    </PageContainer>
  )
}
