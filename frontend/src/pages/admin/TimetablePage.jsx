import { PageContainer } from '../../components/layout/PageContainer.jsx'
import { PageHeader } from '../../components/ui/PageHeader.jsx'
import { TimetableManager } from '../../components/admin/TimetableManager.jsx'

export default function TimetablePage() {
  return (
    <PageContainer>
      <div className="space-y-6">
        <PageHeader
          title="Timetables"
          description="Plan the weekly schedule for each class."
        />
        <TimetableManager />
      </div>
    </PageContainer>
  )
}
