import { useState } from 'react'
import { CalendarDays, Check, Pencil, Trash2 } from 'lucide-react'
import { useAsyncList } from '../../hooks/useAsyncList.js'
import { activateAcademicYear, createAcademicYear, deleteAcademicYear, listAcademicYears, updateAcademicYear } from '../../services/adminService.js'
import { formatDate } from '../../utils/formatters.js'
import { Button } from '../ui/Button.jsx'
import { Input } from '../ui/Input.jsx'
import { Badge } from '../ui/Badge.jsx'
import { Card, CardBody, CardHeader } from '../ui/Card.jsx'
import { Spinner } from '../ui/Spinner.jsx'
import { ErrorDisplay } from '../forms/ErrorDisplay.jsx'
import { Modal } from '../ui/Modal.jsx'

export function AcademicYearManager() {
  const { data: years, loading, error, reload } = useAsyncList(listAcademicYears)
  const [name, setName] = useState('')
  const [startDate, setStartDate] = useState('')
  const [endDate, setEndDate] = useState('')
  const [formError, setFormError] = useState(null)
  const [submitting, setSubmitting] = useState(false)
  const [editing, setEditing] = useState(null)

  const handleCreate = async (event) => {
    event.preventDefault()
    setSubmitting(true)
    setFormError(null)
    try {
      await createAcademicYear({ name, start_date: startDate || null, end_date: endDate || null })
      setName('')
      setStartDate('')
      setEndDate('')
      await reload()
    } catch (err) {
      setFormError(err?.response?.data?.message ?? 'Could not create the year.')
    } finally {
      setSubmitting(false)
    }
  }

  const handleActivate = async (id) => {
    try {
      await activateAcademicYear(id)
      await reload()
    } catch (err) {
      setFormError(err?.response?.data?.message ?? 'Could not activate the year.')
    }
  }

  const openEdit = (year) => {
    setEditing(year)
    setName(year.name)
    setStartDate(year.start_date?.slice(0, 10) ?? '')
    setEndDate(year.end_date?.slice(0, 10) ?? '')
    setFormError(null)
  }

  const handleUpdate = async (event) => {
    event.preventDefault()
    setSubmitting(true)
    setFormError(null)
    try {
      await updateAcademicYear(editing.id, { name, start_date: startDate || null, end_date: endDate || null })
      setEditing(null)
      setName('')
      setStartDate('')
      setEndDate('')
      await reload()
    } catch (err) {
      setFormError(err?.response?.data?.message ?? 'Could not update the year.')
    } finally {
      setSubmitting(false)
    }
  }

  const handleDelete = async (year) => {
    if (year.is_current) {
      setFormError('Set another year as current before deleting this year.')
      return
    }
    if (!window.confirm(`Remove academic year ${year.name}? Related academic records may also be removed.`)) return
    setFormError(null)
    try {
      await deleteAcademicYear(year.id)
      await reload()
    } catch (err) {
      setFormError(err?.response?.data?.message ?? 'Could not remove the year.')
    }
  }

  return (
    <Card>
      <CardHeader
        title="Academic years"
        description="One year is active at a time"
        action={<Badge variant="teal" dot>{years?.length ?? 0} years</Badge>}
      />
      <CardBody>
        <form onSubmit={handleCreate} className="mb-5 grid gap-2 sm:grid-cols-4">
          <Input
            name="year"
            placeholder="e.g. 2027/2028"
            value={name}
            onChange={(event) => setName(event.target.value)}
          />
          <Input name="start_date" label="Start date" type="date" value={startDate} onChange={(event) => setStartDate(event.target.value)} />
          <Input name="end_date" label="End date" type="date" value={endDate} onChange={(event) => setEndDate(event.target.value)} />
          <Button type="submit" loading={submitting}>
            Add year
          </Button>
        </form>
        <ErrorDisplay message={formError} />

        {loading ? (
          <div className="flex justify-center py-8">
            <Spinner />
          </div>
        ) : error ? (
          <p className="text-sm text-slate-500">Could not load academic years.</p>
        ) : (
          <div className="grid gap-3 sm:grid-cols-2">
            {years?.map((year) => (
              <div
                key={year.id}
                className={`flex items-center justify-between rounded-2xl border p-4 ${
                  year.is_current ? 'border-teal-300 bg-teal-50/50' : 'border-slate-200 bg-white'
                }`}
              >
                <div className="flex items-center gap-3">
                  <span
                    className={`flex size-10 items-center justify-center rounded-xl ${
                      year.is_current ? 'bg-teal-500 text-white' : 'bg-slate-100 text-slate-500'
                    }`}
                  >
                    <CalendarDays className="size-5" aria-hidden="true" />
                  </span>
                  <div>
                    <div className="flex items-center gap-2">
                      <p className="text-sm font-semibold text-slate-900">{year.name}</p>
                      {year.is_current ? <Badge variant="success" dot>Current</Badge> : null}
                    </div>
                    <p className="text-xs text-slate-400">
                      {formatDate(year.start_date)} → {formatDate(year.end_date)}
                    </p>
                  </div>
                </div>
                <div className="flex items-center gap-1">
                  {!year.is_current ? (
                    <Button variant="soft" size="sm" onClick={() => handleActivate(year.id)}>
                      <Check className="size-4" aria-hidden="true" />
                      Set current
                    </Button>
                  ) : null}
                  <Button variant="ghost" size="sm" title="Edit academic year" onClick={() => openEdit(year)}>
                    <Pencil className="size-4" aria-hidden="true" />
                  </Button>
                  <Button variant="ghost" size="sm" title="Delete academic year" onClick={() => handleDelete(year)}>
                    <Trash2 className="size-4 text-rose-600" aria-hidden="true" />
                  </Button>
                </div>
              </div>
            ))}
          </div>
        )}
      </CardBody>
      <Modal open={Boolean(editing)} onClose={() => setEditing(null)} title="Edit academic year" description="Update the year name and date range.">
        <form onSubmit={handleUpdate} className="space-y-4">
          <Input label="Academic year" value={name} onChange={(event) => setName(event.target.value)} />
          <div className="grid grid-cols-2 gap-3">
            <Input label="Start date" type="date" value={startDate} onChange={(event) => setStartDate(event.target.value)} />
            <Input label="End date" type="date" value={endDate} onChange={(event) => setEndDate(event.target.value)} />
          </div>
          <ErrorDisplay message={formError} />
          <div className="flex justify-end gap-2"><Button type="button" variant="secondary" onClick={() => setEditing(null)}>Cancel</Button><Button type="submit" loading={submitting}>Save changes</Button></div>
        </form>
      </Modal>
    </Card>
  )
}
