import { useState } from 'react'
import { useAsyncList } from '../../hooks/useAsyncList.js'
import { createPlan, listPlans } from '../../services/subscriptionService.js'
import { SuperAdminLayout } from '../../components/layout/SuperAdminLayout.jsx'
import { StatusBadge } from '../../components/super-admin/StatusBadge.jsx'
import { PageHeader } from '../../components/ui/PageHeader.jsx'
import { Button } from '../../components/ui/Button.jsx'
import { Input } from '../../components/ui/Input.jsx'
import { Select } from '../../components/ui/Select.jsx'
import { Badge } from '../../components/ui/Badge.jsx'
import { Card, CardBody, CardHeader } from '../../components/ui/Card.jsx'
import { DataTable } from '../../components/ui/DataTable.jsx'
import { ErrorDisplay } from '../../components/forms/ErrorDisplay.jsx'

const FEATURE_OPTIONS = [
  'basic_academics',
  'report_cards',
  'document_management',
  'notifications',
  'custom_branding',
  'advanced_analytics',
]

const EMPTY = { name: '', slug: '', price: '', billing_interval: 'monthly', currency: 'XAF', max_students: '', max_teachers: '', max_classes: '', features: [] }

export default function PlansPage() {
  const { data: plans, loading, error, reload } = useAsyncList(listPlans)
  const [form, setForm] = useState(EMPTY)
  const [formError, setFormError] = useState(null)
  const [submitting, setSubmitting] = useState(false)

  const setField = (field) => (event) => setForm((current) => ({ ...current, [field]: event.target.value }))

  const toggleFeature = (feature) => {
    setForm((current) => ({
      ...current,
      features: current.features.includes(feature)
        ? current.features.filter((item) => item !== feature)
        : [...current.features, feature],
    }))
  }

  const handleCreate = async (event) => {
    event.preventDefault()
    setSubmitting(true)
    setFormError(null)
    try {
      await createPlan({
        name: form.name,
        slug: form.slug,
        price: Number(form.price || 0),
        billing_interval: form.billing_interval,
        currency: form.currency,
        max_students: form.max_students === '' ? null : Number(form.max_students),
        max_teachers: form.max_teachers === '' ? null : Number(form.max_teachers),
        max_classes: form.max_classes === '' ? null : Number(form.max_classes),
        features: form.features,
      })
      setForm(EMPTY)
      await reload()
    } catch (err) {
      setFormError(err?.response?.data?.message ?? 'Could not create the plan.')
    } finally {
      setSubmitting(false)
    }
  }

  const columns = [
    { key: 'name', header: 'Plan', render: (plan) => <span className="font-medium text-slate-800">{plan.name}</span> },
    {
      key: 'price',
      header: 'Price',
      align: 'right',
      render: (plan) => (
        <span className="text-slate-600">
          {plan.price} {plan.currency}/{plan.billing_interval}
        </span>
      ),
    },
    { key: 'max_students', header: 'Students', align: 'right', render: (plan) => <span className="text-slate-600">{plan.max_students ?? '∞'}</span> },
    { key: 'max_teachers', header: 'Teachers', align: 'right', render: (plan) => <span className="text-slate-600">{plan.max_teachers ?? '∞'}</span> },
    {
      key: 'features',
      header: 'Features',
      render: (plan) => (
        <div className="flex max-w-xs flex-wrap gap-1">
          {plan.features?.slice(0, 3).map((feature) => (
            <Badge key={feature} variant="neutral">
              {feature}
            </Badge>
          ))}
          {plan.features?.length > 3 ? <Badge variant="neutral">+{plan.features.length - 3}</Badge> : null}
        </div>
      ),
    },
    { key: 'status', header: 'Status', render: (plan) => <StatusBadge status={plan.status} /> },
  ]

  return (
    <SuperAdminLayout>
      <PageHeader title="Subscription plans" description="Configurable plans and limits — never hard-coded." />

      <Card>
        <CardHeader title="Create plan" />
        <CardBody>
          <form onSubmit={handleCreate} className="space-y-3">
            <div className="grid gap-2 sm:grid-cols-2 lg:grid-cols-4">
              <Input name="name" label="Name" value={form.name} onChange={setField('name')} />
              <Input name="slug" label="Slug" value={form.slug} onChange={setField('slug')} />
              <Input name="price" label="Price" type="number" min="0" value={form.price} onChange={setField('price')} />
              <Select name="billing_interval" label="Interval" value={form.billing_interval} onChange={setField('billing_interval')}>
                <option value="monthly">Monthly</option>
                <option value="yearly">Yearly</option>
              </Select>
              <Input name="currency" label="Currency" value={form.currency} onChange={setField('currency')} />
              <Input name="max_students" label="Max students" type="number" min="0" value={form.max_students} onChange={setField('max_students')} />
              <Input name="max_teachers" label="Max teachers" type="number" min="0" value={form.max_teachers} onChange={setField('max_teachers')} />
              <Input name="max_classes" label="Max classes" type="number" min="0" value={form.max_classes} onChange={setField('max_classes')} />
            </div>
            <div>
              <p className="mb-1.5 text-sm font-medium text-slate-700">Features</p>
              <div className="flex flex-wrap gap-2">
                {FEATURE_OPTIONS.map((feature) => (
                  <button
                    key={feature}
                    type="button"
                    onClick={() => toggleFeature(feature)}
                    className={`rounded-full px-3 py-1 text-xs font-medium ring-1 ring-inset transition ${
                      form.features.includes(feature)
                        ? 'bg-brand-600 text-white ring-brand-600'
                        : 'bg-slate-50 text-slate-600 ring-slate-300 hover:bg-slate-100'
                    }`}
                  >
                    {feature}
                  </button>
                ))}
              </div>
            </div>
            <Button type="submit" loading={submitting}>
              Create plan
            </Button>
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
            rows={plans}
            loading={loading}
            emptyTitle="No plans"
            emptyDescription="Create your first subscription plan."
          />
          {error ? <p className="mt-3 text-sm text-slate-500">Could not load plans.</p> : null}
        </CardBody>
      </Card>
    </SuperAdminLayout>
  )
}
