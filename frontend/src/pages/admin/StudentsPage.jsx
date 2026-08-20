import { PageContainer } from '../../components/layout/PageContainer.jsx'
import { PageHeader } from '../../components/ui/PageHeader.jsx'
import { StudentManager } from '../../components/admin/StudentManager.jsx'

export default function StudentsPage() {
  return (
    <PageContainer>
      <div className="space-y-6">
        <PageHeader
          title="Students"
          description="Register students and enroll them in classes."
        />
        <StudentManager />
      </div>
    </PageContainer>
  )
}
