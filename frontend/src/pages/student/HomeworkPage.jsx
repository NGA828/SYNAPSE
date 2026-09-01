import { BookOpenCheck, Hourglass, ListChecks } from 'lucide-react'
import { useAsyncList } from '../../hooks/useAsyncList.js'
import { listStudentHomework } from '../../services/homeworkService.js'
import { PageContainer } from '../../components/layout/PageContainer.jsx'
import { PageHeader } from '../../components/ui/PageHeader.jsx'
import { Card } from '../../components/ui/Card.jsx'
import { Spinner } from '../../components/ui/Spinner.jsx'
import { StatCard } from '../../components/dashboard/StatCard.jsx'
import { HomeworkSubmissionCard } from '../../components/homework/HomeworkSubmissionCard.jsx'

/**
 * The student's homework inbox: everything set for their class, with their own
 * progress against each item.
 */
export default function StudentHomeworkPage() {
  const { data, loading, error, reload } = useAsyncList(listStudentHomework)

  const rows = data?.data ?? []
  const summary = data?.summary ?? { pending: 0, awaiting_grade: 0, graded: 0 }

  return (
    <PageContainer>
      <div className="space-y-6">
        <PageHeader title="Homework" description="Work set by your teachers, with your marks and feedback." />

        <div className="grid gap-4 sm:grid-cols-3">
          <StatCard label="To do" value={summary.pending} icon={ListChecks} tone="brand" />
          <StatCard label="Awaiting mark" value={summary.awaiting_grade} icon={Hourglass} tone="amber" />
          <StatCard label="Marked" value={summary.graded} icon={BookOpenCheck} tone="emerald" />
        </div>

        {loading ? (
          <div className="flex justify-center py-20">
            <Spinner className="size-8" />
          </div>
        ) : error ? (
          <Card className="p-6 text-sm text-slate-500">Could not load your homework.</Card>
        ) : rows.length === 0 ? (
          <Card className="p-12 text-center text-sm text-slate-500">
            No homework has been set for your class yet.
          </Card>
        ) : (
          <div className="space-y-4">
            {rows.map((item) => (
              <HomeworkSubmissionCard key={item.assignment.id} item={item} onSubmitted={reload} />
            ))}
          </div>
        )}
      </div>
    </PageContainer>
  )
}
