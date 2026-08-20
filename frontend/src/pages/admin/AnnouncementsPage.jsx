import { PageContainer } from '../../components/layout/PageContainer.jsx'
import { PageHeader } from '../../components/ui/PageHeader.jsx'
import { AnnouncementManager } from '../../components/admin/AnnouncementManager.jsx'

export default function AnnouncementsPage() {
  return (
    <PageContainer>
      <div className="space-y-6">
        <PageHeader
          title="Announcements"
          description="Publish updates to your school community."
        />
        <AnnouncementManager />
      </div>
    </PageContainer>
  )
}
