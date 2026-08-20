import { useState } from 'react'
import { useAsyncList } from '../../hooks/useAsyncList.js'
import { createStudent, listStudents } from '../../services/registrationService.js'
import { listClasses } from '../../services/adminService.js'
import { Button } from '../ui/Button.jsx'
import { Input } from '../ui/Input.jsx'
import { Select } from '../ui/Select.jsx'
import { Badge } from '../ui/Badge.jsx'
import { Card, CardBody, CardHeader } from '../ui/Card.jsx'
import { DataTable } from '../ui/DataTable.jsx'
import { Avatar } from '../ui/Avatar.jsx'
import { ErrorDisplay } from '../forms/ErrorDisplay.jsx'

const EMPTY = { name: '', email: '', password: '', matricule: '', class_id: '' }

export function StudentManager() {
  const { data: students, loading, error, reload } = useAsyncList(listStudents)
  const { data: classes } = useAsyncList(listClasses)
  const [form, setForm] = useState(EMPTY)
  const [formError, setFormError] = useState(null)
  const [submitting, setSubmitting] = useState(false)

  const setField = (field) => (event) => setForm((current) => ({ ...current, [field]: event.target.value }))

  const handleCreate = async (event) => {
    event.preventDefault()
    setSubmitting(true)
    setFormError(null)
    try {
      await createStudent(form)
      setForm(EMPTY)
      await reload()
    } catch (err) {
      setFormError(err?.response?.data?.message ?? 'Could not register the student.')
    } finally {
      setSubmitting(false)
    }
  }

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
    { key: 'email', header: 'Email' },
    { key: 'matricule', header: 'Matricule', cellClassName: 'font-mono text-xs text-slate-600' },
    {
      key: 'class',
      header: 'Class',
      render: (student) =>
        student.class ? <Badge variant="info">{student.class.name}</Badge> : <span className="text-slate-400">—</span>,
    },
  ]

  return (
    <Card>
      <CardHeader title="Register a student" description="Create an account and enroll in a class" />
      <CardBody>
        <form onSubmit={handleCreate} className="mb-4 grid gap-2 sm:grid-cols-2 lg:grid-cols-5">
          <Input name="name" label="Full name" placeholder="Jane Doe" value={form.name} onChange={setField('name')} />
          <Input name="email" label="Email" placeholder="jane@school.edu" value={form.email} onChange={setField('email')} />
          <Input name="password" label="Password" placeholder="min 8 chars" value={form.password} onChange={setField('password')} />
          <Input name="matricule" label="Matricule" placeholder="ST2026…" value={form.matricule} onChange={setField('matricule')} />
          <Select name="class" label="Class" value={form.class_id} onChange={setField('class_id')}>
            <option value="">Select…</option>
            {classes?.map((item) => (
              <option key={item.id} value={item.id}>
                {item.name}
              </option>
            ))}
          </Select>
        </form>
        <div className="mb-4 flex items-center gap-3">
          <Button onClick={handleCreate} loading={submitting}>
            Add student
          </Button>
          <ErrorDisplay message={formError} />
        </div>

        <DataTable
          columns={columns}
          rows={students}
          loading={loading}
          emptyTitle="No students yet"
          emptyDescription="Register your first student to get started."
        />
        {error ? <p className="mt-3 text-sm text-slate-500">Could not load students.</p> : null}
      </CardBody>
    </Card>
  )
}
