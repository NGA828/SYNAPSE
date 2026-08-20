import { useState } from 'react'
import { Trash2 } from 'lucide-react'
import { useAsyncList } from '../../hooks/useAsyncList.js'
import {
  createTeachingAssignment,
  deleteTeachingAssignment,
  listClasses,
  listSubjects,
  listTeachingAssignments,
} from '../../services/adminService.js'
import { listTeachers } from '../../services/registrationService.js'
import { Button } from '../ui/Button.jsx'
import { Select } from '../ui/Select.jsx'
import { Badge } from '../ui/Badge.jsx'
import { Card, CardBody, CardHeader } from '../ui/Card.jsx'
import { DataTable } from '../ui/DataTable.jsx'
import { ErrorDisplay } from '../forms/ErrorDisplay.jsx'
import { subjectPalette } from '../../utils/timetable.js'
import { cn } from '../../utils/cn.js'

export function AssignmentManager() {
  const { data: assignments, loading, error, reload } = useAsyncList(listTeachingAssignments)
  const { data: teachers } = useAsyncList(listTeachers)
  const { data: subjects } = useAsyncList(listSubjects)
  const { data: classes } = useAsyncList(listClasses)

  const [teacherId, setTeacherId] = useState('')
  const [subjectId, setSubjectId] = useState('')
  const [classId, setClassId] = useState('')
  const [formError, setFormError] = useState(null)
  const [submitting, setSubmitting] = useState(false)

  const handleCreate = async (event) => {
    event.preventDefault()
    setSubmitting(true)
    setFormError(null)
    try {
      await createTeachingAssignment({
        teacher_id: teacherId,
        subject_id: subjectId,
        class_id: classId,
      })
      setTeacherId('')
      setSubjectId('')
      setClassId('')
      await reload()
    } catch (err) {
      setFormError(err?.response?.data?.message ?? 'Could not create the assignment.')
    } finally {
      setSubmitting(false)
    }
  }

  const handleDelete = async (id) => {
    await deleteTeachingAssignment(id)
    await reload()
  }

  const columns = [
    { key: 'teacher', header: 'Teacher', render: (a) => <span className="font-medium text-slate-800">{a.teacher.name}</span> },
    {
      key: 'subject',
      header: 'Subject',
      render: (a) => (
        <span className="inline-flex items-center gap-2">
          <span className={cn('size-2 rounded-full', subjectPalette(a.subject.name).dot)} />
          {a.subject.name}
        </span>
      ),
    },
    {
      key: 'class',
      header: 'Class',
      render: (a) => <Badge variant="info">{a.class.name}</Badge>,
    },
    { key: 'year', header: 'Year', render: (a) => <span className="text-slate-600">{a.academic_year.name}</span> },
    {
      key: 'actions',
      header: '',
      align: 'right',
      render: (a) => (
        <button
          type="button"
          onClick={() => handleDelete(a.id)}
          className="rounded-lg p-1.5 text-slate-400 transition hover:bg-rose-50 hover:text-rose-600"
          aria-label="Remove assignment"
        >
          <Trash2 className="size-4" />
        </button>
      ),
    },
  ]

  return (
    <Card>
      <CardHeader
        title="Teaching assignments"
        description="Assign teachers to classes and subjects"
        action={<Badge variant="teal" dot>{assignments?.length ?? 0} assignments</Badge>}
      />
      <CardBody>
        <form onSubmit={handleCreate} className="mb-5 grid gap-2 sm:grid-cols-4">
          <Select name="teacher" label="Teacher" value={teacherId} onChange={(event) => setTeacherId(event.target.value)}>
            <option value="">Select…</option>
            {teachers?.map((teacher) => (
              <option key={teacher.id} value={teacher.id}>
                {teacher.name}
              </option>
            ))}
          </Select>
          <Select name="subject" label="Subject" value={subjectId} onChange={(event) => setSubjectId(event.target.value)}>
            <option value="">Select…</option>
            {subjects?.map((subject) => (
              <option key={subject.id} value={subject.id}>
                {subject.name}
              </option>
            ))}
          </Select>
          <Select name="class" label="Class" value={classId} onChange={(event) => setClassId(event.target.value)}>
            <option value="">Select…</option>
            {classes?.map((item) => (
              <option key={item.id} value={item.id}>
                {item.name}
              </option>
            ))}
          </Select>
          <div className="flex items-end">
            <Button type="submit" loading={submitting} className="w-full">
              Assign
            </Button>
          </div>
        </form>
        <ErrorDisplay message={formError} />

        <DataTable
          columns={columns}
          rows={assignments}
          loading={loading}
          emptyTitle="No assignments yet"
          emptyDescription="Assign a teacher to a class and subject to get started."
        />
        {error ? <p className="mt-3 text-sm text-slate-500">Could not load assignments.</p> : null}
      </CardBody>
    </Card>
  )
}
