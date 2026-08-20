import { useState } from 'react'
import { CalendarRange, Trash2 } from 'lucide-react'
import { useAsyncList } from '../../hooks/useAsyncList.js'
import {
  activateSemester,
  createSemester,
  deleteSemester,
  listSemesters,
} from '../../services/semesterService.js'
import { formatDate } from '../../utils/formatters.js'
import { Button } from '../ui/Button.jsx'
import { Input } from '../ui/Input.jsx'
import { Badge } from '../ui/Badge.jsx'
import { Card, CardBody, CardHeader } from '../ui/Card.jsx'
import { Spinner } from '../ui/Spinner.jsx'
import { ErrorDisplay } from '../forms/ErrorDisplay.jsx'

export function SemesterManager() {
  const { data, loading, error, reload } = useAsyncList(listSemesters)
  const semesters = data?.data ?? []
  const year = data?.academic_year

  const [form, setForm] = useState({ name: '', sequence: '1', start_date: '', end_date: '', is_current: false })
  const [formError, setFormError] = useState(null)
  const [submitting, setSubmitting] = useState(false)

  const setField = (field) => (event) =>
    setForm((current) => ({ ...current, [field]: event.target.type === 'checkbox' ? event.target.checked : event.target.value }))

  const handleCreate = async (event) => {
    event.preventDefault()
    setSubmitting(true)
    setFormError(null)
    try {
      await createSemester({
        academic_year_id: year?.id,
        name: form.name,
        sequence: Number(form.sequence),
        start_date: form.start_date || null,
        end_date: form.end_date || null,
        is_current: form.is_current,
      })
      setForm({ name: '', sequence: '1', start_date: '', end_date: '', is_current: false })
      await reload()
    } catch (err) {
      setFormError(err?.response?.data?.message ?? 'Could not create the semester.')
    } finally {
      setSubmitting(false)
    }
  }

  const handleActivate = async (id) => {
    await activateSemester(id)
    await reload()
  }

  const handleDelete = async (id) => {
    await deleteSemester(id)
    await reload()
  }

  return (
    <Card>
      <CardHeader
        title="Semesters"
        description={`Grading periods for ${year?.name ?? 'the current year'}`}
        action={<Badge variant="teal" dot>{semesters.length} periods</Badge>}
      />
      <CardBody>
        <form onSubmit={handleCreate} className="mb-5 grid gap-2 sm:grid-cols-2 lg:grid-cols-4">
          <Input name="name" label="Name" placeholder="Semester 2" value={form.name} onChange={setField('name')} />
          <Input name="sequence" label="Sequence" type="number" min="1" value={form.sequence} onChange={setField('sequence')} />
          <Input name="start" label="Start" type="date" value={form.start_date} onChange={setField('start_date')} />
          <Input name="end" label="End" type="date" value={form.end_date} onChange={setField('end_date')} />
        </form>
        <div className="mb-5 flex items-center gap-4">
          <Button onClick={handleCreate} loading={submitting}>
            Add semester
          </Button>
          <label className="flex items-center gap-2 text-sm font-medium text-slate-700">
            <input type="checkbox" checked={form.is_current} onChange={setField('is_current')} className="size-4 rounded border-slate-300 text-brand-600" />
            Set as current
          </label>
        </div>
        <ErrorDisplay message={formError} />

        {loading ? (
          <div className="flex justify-center py-8">
            <Spinner />
          </div>
        ) : error ? (
          <p className="text-sm text-slate-500">Could not load semesters.</p>
        ) : (
          <div className="grid gap-3 sm:grid-cols-2">
            {semesters.map((semester) => (
              <div
                key={semester.id}
                className={`flex items-center justify-between rounded-2xl border p-4 ${
                  semester.is_current ? 'border-teal-300 bg-teal-50/50' : 'border-slate-200 bg-white'
                }`}
              >
                <div className="flex items-center gap-3">
                  <span
                    className={`flex size-10 items-center justify-center rounded-xl ${
                      semester.is_current ? 'bg-teal-500 text-white' : 'bg-slate-100 text-slate-500'
                    }`}
                  >
                    <CalendarRange className="size-5" aria-hidden="true" />
                  </span>
                  <div>
                    <div className="flex items-center gap-2">
                      <p className="text-sm font-semibold text-slate-900">{semester.name}</p>
                      {semester.is_current ? <Badge variant="success" dot>Current</Badge> : null}
                    </div>
                    <p className="text-xs text-slate-400">
                      Term {semester.sequence} · {formatDate(semester.start_date)} → {formatDate(semester.end_date)}
                    </p>
                  </div>
                </div>
                <div className="flex items-center gap-1.5">
                  {!semester.is_current ? (
                    <Button variant="soft" size="sm" onClick={() => handleActivate(semester.id)}>
                      Set current
                    </Button>
                  ) : null}
                  <button
                    type="button"
                    onClick={() => handleDelete(semester.id)}
                    className="rounded-lg p-1.5 text-slate-400 transition hover:bg-rose-50 hover:text-rose-600"
                    aria-label="Remove semester"
                  >
                    <Trash2 className="size-4" />
                  </button>
                </div>
              </div>
            ))}
          </div>
        )}
      </CardBody>
    </Card>
  )
}
