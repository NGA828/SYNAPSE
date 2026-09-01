import { PageContainer } from '../../components/layout/PageContainer.jsx'
import { PageHeader } from '../../components/ui/PageHeader.jsx'
import { EventManager } from '../../components/events/EventManager.jsx'

export default function EventsPage() {
  return (
    <PageContainer>
      <div className="space-y-6">
        <PageHeader
          title="School events"
          description="Publish what is happening at your school to students, teachers or everyone."
        />
        <EventManager />
      </div>
    </PageContainer>
  )
}
