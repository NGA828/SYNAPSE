import { useState } from 'react'
import { useAsyncList } from '../../hooks/useAsyncList.js'
import { createTeacher, listTeachers } from '../../services/registrationService.js'
import { Button } from '../ui/Button.jsx'
import { Input } from '../ui/Input.jsx'
import { Badge } from '../ui/Badge.jsx'
import { Card, CardBody, CardHeader } from '../ui/Card.jsx'
import { DataTable } from '../ui/DataTable.jsx'
import { Avatar } from '../ui/Avatar.jsx'
import { ErrorDisplay } from '../forms/ErrorDisplay.jsx'

const EMPTY = { name: '', email: '', password: '', staff_no: '' }

export function TeacherManager() {
  const { data: teachers, loading, error, reload } = useAsyncList(listTeachers)
  const [form, setForm] = useState(EMPTY)
  const [formError, setFormError] = useState(null)
  const [submitting, setSubmitting] = useState(false)

  const setField = (field) => (event) => setForm((current) => ({ ...current, [field]: event.target.value }))

  const handleCreate = async (event) => {
    event.preventDefault()
    setSubmitting(true)
    setFormError(null)
    try {
      await createTeacher(form)
      setForm(EMPTY)
      await reload()
    } catch (err) {
      setFormError(err?.response?.data?.message ?? 'Could not register the teacher.')
    } finally {
      setSubmitting(false)
    }
  }

  const columns = [
    {
      key: 'teacher',
      header: 'Teacher',
      render: (teacher) => (
        <span className="flex items-center gap-3">
          <Avatar name={teacher.name} size="sm" />
          <span className="font-medium text-slate-800">{teacher.name}</span>
        </span>
      ),
    },
    { key: 'email', header: 'Email' },
    {
      key: 'staff_no',
      header: 'Staff no.',
      render: (teacher) =>
        teacher.staff_no ? <Badge variant="neutral">{teacher.staff_no}</Badge> : <span className="text-slate-400">—</span>,
    },
  ]

  return (
    <Card>
      <CardHeader title="Register a teacher" description="Create an account and issue a staff number" />
      <CardBody>
        <form onSubmit={handleCreate} className="mb-4 grid gap-2 sm:grid-cols-2 lg:grid-cols-4">
          <Input name="name" label="Full name" placeholder="Jane Doe" value={form.name} onChange={setField('name')} />
          <Input name="email" label="Email" placeholder="jane@school.edu" value={form.email} onChange={setField('email')} />
          <Input name="password" label="Password" placeholder="min 8 chars" value={form.password} onChange={setField('password')} />
          <Input name="staff_no" label="Staff no." placeholder="TCH-003" value={form.staff_no} onChange={setField('staff_no')} />
        </form>
        <div className="mb-4 flex items-center gap-3">
          <Button onClick={handleCreate} loading={submitting}>
            Add teacher
          </Button>
          <ErrorDisplay message={formError} />
        </div>

        <DataTable
          columns={columns}
          rows={teachers}
          loading={loading}
          emptyTitle="No teachers yet"
          emptyDescription="Register your first teacher to get started."
        />
        {error ? <p className="mt-3 text-sm text-slate-500">Could not load teachers.</p> : null}
      </CardBody>
    </Card>
  )
}
