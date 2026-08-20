import { Link } from 'react-router-dom'
import { useAsyncList } from '../../hooks/useAsyncList.js'
import { listAssignments } from '../../services/teacherService.js'
import { PageContainer } from '../../components/layout/PageContainer.jsx'
import { PageHeader } from '../../components/ui/PageHeader.jsx'
import { AssignmentCard } from '../../components/dashboard/AssignmentCard.jsx'
import { Card } from '../../components/ui/Card.jsx'
import { Spinner } from '../../components/ui/Spinner.jsx'

export default function GradeEntryPage() {
  const { data: assignments, loading, error } = useAsyncList(listAssignments)

  return (
    <PageContainer>
      <div className="space-y-6">
        <PageHeader
          title="Grade entry"
          description="Choose a class to enter grades for your assigned subjects."
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
              <Link
                key={assignment.id}
                to={`/teacher/classes/${assignment.class.id}/subjects/${assignment.subject.id}/grades`}
                className="block"
              >
                <AssignmentCard
                  klass={assignment.class.name}
                  subject={assignment.subject.name}
                  students={assignment.students_count}
                />
              </Link>
            ))}
          </div>
        )}
      </div>
    </PageContainer>
  )
}
