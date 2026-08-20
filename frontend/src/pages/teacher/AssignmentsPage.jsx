import { useAsyncList } from '../../hooks/useAsyncList.js'
import { listAssignments } from '../../services/teacherService.js'
import { PageContainer } from '../../components/layout/PageContainer.jsx'
import { PageHeader } from '../../components/ui/PageHeader.jsx'
import { AssignmentCard } from '../../components/dashboard/AssignmentCard.jsx'
import { Card } from '../../components/ui/Card.jsx'
import { Spinner } from '../../components/ui/Spinner.jsx'

export default function AssignmentsPage() {
  const { data: assignments, loading, error } = useAsyncList(listAssignments)

  return (
    <PageContainer>
      <div className="space-y-6">
        <PageHeader
          title="My teaching assignments"
          description="The classes and subjects the administrator assigned to you."
        />

        {loading ? (
          <div className="flex justify-center py-20">
            <Spinner className="size-8" />
          </div>
        ) : error ? (
          <Card className="p-6 text-sm text-slate-500">Could not load your assignments.</Card>
        ) : (
          <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            {assignments?.map((assignment) => (
              <AssignmentCard
                key={assignment.id}
                klass={assignment.class.name}
                subject={assignment.subject.name}
                students={assignment.students_count}
                to={`/teacher/classes/${assignment.class.id}/subjects/${assignment.subject.id}`}
                gradesTo={`/teacher/classes/${assignment.class.id}/subjects/${assignment.subject.id}/grades`}
              />
            ))}
          </div>
        )}
      </div>
    </PageContainer>
  )
}
