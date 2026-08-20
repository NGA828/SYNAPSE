import { useState } from 'react'
import { Link } from 'react-router-dom'
import { useAsyncList } from '../../hooks/useAsyncList.js'
import { createSchool, listSchools } from '../../services/schoolService.js'
import { SuperAdminLayout } from '../../components/layout/SuperAdminLayout.jsx'
import { StatusBadge } from '../../components/super-admin/StatusBadge.jsx'
import { PageHeader } from '../../components/ui/PageHeader.jsx'
import { Button } from '../../components/ui/Button.jsx'
import { Input } from '../../components/ui/Input.jsx'
import { Card, CardBody, CardHeader } from '../../components/ui/Card.jsx'
import { DataTable } from '../../components/ui/DataTable.jsx'
import { ErrorDisplay } from '../../components/forms/ErrorDisplay.jsx'

export default function SchoolsPage() {
  const { data: schools, loading, error, reload } = useAsyncList(listSchools)
  const [form, setForm] = useState({ name: '', slug: '', email: '', phone: '', address: '' })
  const [formError, setFormError] = useState(null)
  const [submitting, setSubmitting] = useState(false)

  const setField = (field) => (event) => setForm((current) => ({ ...current, [field]: event.target.value }))

  const handleCreate = async (event) => {
    event.preventDefault()
    setSubmitting(true)
    setFormError(null)
    try {
      await createSchool(form)
      setForm({ name: '', slug: '', email: '', phone: '', address: '' })
      await reload()
    } catch (err) {
      setFormError(err?.response?.data?.message ?? 'Could not create the school.')
    } finally {
      setSubmitting(false)
    }
  }

  const columns = [
    {
      key: 'school',
      header: 'School',
      render: (school) => (
        <Link to={`/super-admin/schools/${school.id}`} className="font-medium text-slate-800 hover:underline">
          {school.name}
        </Link>
      ),
    },
    { key: 'slug', header: 'Slug', cellClassName: 'font-mono text-xs text-slate-500' },
    {
      key: 'plan',
      header: 'Plan',
      render: (school) => <span className="text-slate-600">{school.subscription_plan?.name ?? '—'}</span>,
    },
    { key: 'status', header: 'Status', render: (school) => <StatusBadge status={school.status} /> },
    { key: 'users_count', header: 'Users', align: 'right' },
  ]

  return (
    <SuperAdminLayout>
      <PageHeader title="Schools" description="Create and manage tenant schools." />

      <Card>
        <CardHeader title="Create school" description="Onboard a new tenant" />
        <CardBody>
          <form onSubmit={handleCreate} className="grid gap-2 sm:grid-cols-2 lg:grid-cols-5">
            <Input name="name" label="Name" value={form.name} onChange={setField('name')} placeholder="AICS Cameroon" />
            <Input name="slug" label="Slug" value={form.slug} onChange={setField('slug')} placeholder="aics" />
            <Input name="email" label="Email" value={form.email} onChange={setField('email')} placeholder="contact@school.cm" />
            <Input name="phone" label="Phone" value={form.phone} onChange={setField('phone')} placeholder="+237…" />
            <div className="flex items-end">
              <Button type="submit" loading={submitting} className="w-full">
                Create
              </Button>
            </div>
          </form>
          <div className="mt-3">
            <ErrorDisplay message={formError} />
          </div>
        </CardBody>
      </Card>

      <Card>
        <CardBody>
          <DataTable
            columns={columns}
            rows={schools}
            loading={loading}
            emptyTitle="No schools"
            emptyDescription="Create your first school to get started."
          />
          {error ? <p className="mt-3 text-sm text-slate-500">Could not load schools.</p> : null}
        </CardBody>
      </Card>
    </SuperAdminLayout>
  )
}
