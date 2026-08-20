import { useState } from 'react'
import { School } from 'lucide-react'
import { useAsyncList } from '../../hooks/useAsyncList.js'
import { createClass, listClasses } from '../../services/adminService.js'
import { Button } from '../ui/Button.jsx'
import { Input } from '../ui/Input.jsx'
import { Badge } from '../ui/Badge.jsx'
import { Card, CardBody, CardHeader } from '../ui/Card.jsx'
import { Spinner } from '../ui/Spinner.jsx'
import { ErrorDisplay } from '../forms/ErrorDisplay.jsx'

export function ClassManager() {
  const { data: classes, loading, error, reload } = useAsyncList(listClasses)
  const [name, setName] = useState('')
  const [formError, setFormError] = useState(null)
  const [submitting, setSubmitting] = useState(false)

  const handleCreate = async (event) => {
    event.preventDefault()
    setSubmitting(true)
    setFormError(null)
    try {
      await createClass({ name })
      setName('')
      await reload()
    } catch (err) {
      setFormError(err?.response?.data?.message ?? 'Could not create the class.')
    } finally {
      setSubmitting(false)
    }
  }

  return (
    <Card>
      <CardHeader
        title="Classes"
        description="Create and list your class levels"
        action={<Badge variant="teal" dot>{classes?.length ?? 0} classes</Badge>}
      />
      <CardBody>
        <form onSubmit={handleCreate} className="mb-5 flex gap-2">
          <Input
            name="class"
            placeholder="e.g. Level 4"
            value={name}
            onChange={(event) => setName(event.target.value)}
          />
          <Button type="submit" loading={submitting}>
            Add class
          </Button>
        </form>
        <ErrorDisplay message={formError} />

        {loading ? (
          <div className="flex justify-center py-8">
            <Spinner />
          </div>
        ) : error ? (
          <p className="text-sm text-slate-500">Could not load classes.</p>
        ) : (
          <ul className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            {classes?.map((item) => (
              <li
                key={item.id}
                className="flex items-center gap-3 rounded-2xl border border-slate-200 bg-white p-4 transition hover:border-slate-300 hover:shadow-sm"
              >
                <span className="flex size-10 shrink-0 items-center justify-center rounded-xl bg-brand-50 text-brand-600">
                  <School className="size-5" aria-hidden="true" />
                </span>
                <span className="truncate text-sm font-semibold text-slate-800">{item.name}</span>
              </li>
            ))}
          </ul>
        )}
      </CardBody>
    </Card>
  )
}
