import { Link, useParams } from 'react-router-dom'
import { BookOpen, ShieldAlert } from 'lucide-react'
import { useAsyncList } from '../../hooks/useAsyncList.js'
import { getClassStudents } from '../../services/teacherService.js'
import { PageContainer } from '../../components/layout/PageContainer.jsx'
import { PageHeader } from '../../components/ui/PageHeader.jsx'
import { DataTable } from '../../components/ui/DataTable.jsx'
import { Avatar } from '../../components/ui/Avatar.jsx'
import { Badge } from '../../components/ui/Badge.jsx'
import { Button } from '../../components/ui/Button.jsx'
import { Card, CardBody, CardHeader } from '../../components/ui/Card.jsx'
import { Spinner } from '../../components/ui/Spinner.jsx'
import { subjectPalette } from '../../utils/timetable.js'
import { cn } from '../../utils/cn.js'

export default function ClassPage() {
  const { classId, subjectId } = useParams()
  const { data, loading, error } = useAsyncList(
    () => getClassStudents(classId, subjectId),
    [classId, subjectId],
  )

  const forbidden = error?.response?.status === 403 || error?.response?.status === 409
  const palette = subjectPalette(data?.subject?.name)
  const students = data?.students ?? []

  const columns = [
    {
      key: 'student',
      header: 'Student',
      render: (student) => (
        <span className="flex items-center gap-3">
          <Avatar name={student.name} size="sm" />
          <span className="font-medium text-slate-800">{student.name}</span>
        </span>
      ),
    },
    { key: 'matricule', header: 'Matricule', cellClassName: 'font-mono text-xs text-slate-600' },
  ]

  return (
    <PageContainer>
      <div className="space-y-6">
        <PageHeader
          title={data?.subject?.name ?? 'Class'}
          description={`${data?.class?.name ?? ''} · Academic year ${data?.academic_year?.name ?? ''}`}
          back="/teacher"
        >
          <Link to={`/teacher/classes/${classId}/subjects/${subjectId}/grades`}>
            <Button>
              <BookOpen className="size-4" aria-hidden="true" />
              Enter grades
            </Button>
          </Link>
        </PageHeader>

        {loading ? (
          <div className="flex justify-center py-20">
            <Spinner className="size-8" />
          </div>
        ) : forbidden ? (
          <Card className="p-10 text-center">
            <ShieldAlert className="mx-auto size-10 text-rose-500" aria-hidden="true" />
            <p className="mt-3 text-base font-semibold text-slate-800">Access denied</p>
            <p className="mt-1 text-sm text-slate-500">
              You are not assigned to teach this subject in this class.
            </p>
            <Link to="/teacher" className="mt-5 inline-block">
              <Button variant="secondary" size="sm">
                Back to my assignments
              </Button>
            </Link>
          </Card>
        ) : error ? (
          <Card className="p-6 text-sm text-slate-500">Could not load this class.</Card>
        ) : (
          <>
            <div className="overflow-hidden rounded-2xl bg-gradient-to-br from-violet-600 to-fuchsia-600">
              <div className="flex flex-col gap-5 p-6 text-white sm:flex-row sm:items-center sm:justify-between">
                <div className="flex items-center gap-4">
                  <span className={cn('flex size-14 items-center justify-center rounded-2xl text-2xl font-bold ring-1 ring-inset', palette.chip)}>
                    {String(data?.subject?.name ?? '').slice(0, 1).toUpperCase()}
                  </span>
                  <div>
                    <p className="text-xs uppercase tracking-wide text-violet-100">You teach</p>
                    <p className="text-2xl font-bold">{data?.subject?.name}</p>
                    <p className="text-sm text-violet-100">{data?.class?.name} · {data?.academic_year?.name}</p>
                  </div>
                </div>
                <div className="flex items-center gap-3">
                  <div className="rounded-2xl bg-white/10 px-5 py-3 text-center">
                    <p className="text-2xl font-bold">{students.length}</p>
                    <p className="text-xs text-violet-100">Students</p>
                  </div>
                  <Badge variant="violet" dot>Enrolled</Badge>
                </div>
              </div>
            </div>

            <Card>
              <CardHeader
                title="Class roster"
                description={`${students.length} enrolled students in ${data?.class?.name}`}
              />
              <CardBody>
                <DataTable
                  columns={columns}
                  rows={students}
                  loading={false}
                  emptyTitle="No students yet"
                  emptyDescription="Students enrolled in this class will appear here."
                />
              </CardBody>
            </Card>
          </>
        )}
      </div>
    </PageContainer>
  )
}
