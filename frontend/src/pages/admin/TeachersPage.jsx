import { PageContainer } from '../../components/layout/PageContainer.jsx'
import { PageHeader } from '../../components/ui/PageHeader.jsx'
import { TeacherManager } from '../../components/admin/TeacherManager.jsx'

export default function TeachersPage() {
  return (
    <PageContainer>
      <div className="space-y-6">
        <PageHeader
          title="Teachers"
          description="Register teachers and issue staff accounts."
        />
        <TeacherManager />
      </div>
    </PageContainer>
  )
}
