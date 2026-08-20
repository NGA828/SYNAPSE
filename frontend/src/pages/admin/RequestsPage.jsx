import { PageContainer } from '../../components/layout/PageContainer.jsx'
import { PageHeader } from '../../components/ui/PageHeader.jsx'
import { RequestManager } from '../../components/admin/RequestManager.jsx'

export default function RequestsPage() {
  return (
    <PageContainer>
      <div className="space-y-6">
        <PageHeader
          title="Student requests"
          description="Review, approve and generate documents for student requests."
        />
        <RequestManager />
      </div>
    </PageContainer>
  )
}
