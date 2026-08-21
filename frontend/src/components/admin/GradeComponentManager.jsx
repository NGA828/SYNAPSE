import { useState } from 'react'
import { Pencil, Scale, Trash2 } from 'lucide-react'
import { useAsyncList } from '../../hooks/useAsyncList.js'
import {
  createGradeComponent,
  deleteGradeComponent,
  listGradeComponents,
  updateGradeComponent,
} from '../../services/gradeComponentService.js'
import { listSubjects } from '../../services/adminService.js'
import { Button } from '../ui/Button.jsx'
import { Input } from '../ui/Input.jsx'
import { Select } from '../ui/Select.jsx'
import { Badge } from '../ui/Badge.jsx'
import { Card, CardBody, CardHeader } from '../ui/Card.jsx'
import { Spinner } from '../ui/Spinner.jsx'
import { ErrorDisplay } from '../forms/ErrorDisplay.jsx'
import { Modal } from '../ui/Modal.jsx'

export function GradeComponentManager() {
  const { data, loading, error, reload } = useAsyncList(listGradeComponents)
  const { data: subjects } = useAsyncList(listSubjects)

  const defaults = data?.default ?? []
  const bySubject = data?.by_subject ?? {}

  const [form, setForm] = useState({ name: '', weight: '', subject_id: '' })
  const [formError, setFormError] = useState(null)
  const [submitting, setSubmitting] = useState(false)
  const [editing, setEditing] = useState(null)

  const setField = (field) => (event) => setForm((current) => ({ ...current, [field]: event.target.value }))

  const handleCreate = async (event) => {
    event.preventDefault()
    setSubmitting(true)
    setFormError(null)
    try {
      await createGradeComponent({
        name: form.name,
        weight: Number(form.weight),
        subject_id: form.subject_id || null,
      })
      setForm({ name: '', weight: '', subject_id: '' })
      await reload()
    } catch (err) {
      setFormError(err?.response?.data?.message ?? 'Could not create the component.')
    } finally {
      setSubmitting(false)
    }
  }

  const handleDelete = async (id) => {
    if (!window.confirm('Remove this grading component? Existing scores may also be removed.')) return
    setFormError(null)
    try {
      await deleteGradeComponent(id)
      await reload()
    } catch (err) {
      setFormError(err?.response?.data?.message ?? 'Could not remove the component.')
    }
  }

  const handleUpdate = async (event) => {
    event.preventDefault()
    setSubmitting(true)
    setFormError(null)
    try {
      await updateGradeComponent(editing.id, {
        name: form.name,
        weight: Number(form.weight),
        subject_id: form.subject_id || null,
      })
      setEditing(null)
      setForm({ name: '', weight: '', subject_id: '' })
      await reload()
    } catch (err) {
      setFormError(err?.response?.data?.message ?? 'Could not update the component.')
    } finally {
      setSubmitting(false)
    }
  }

  const totalWeight = defaults.reduce((sum, component) => sum + Number(component.weight), 0)

  const renderRow = (component) => (
    <li key={component.id} className="flex items-center justify-between rounded-2xl border border-slate-200 bg-white p-4">
      <div className="flex items-center gap-3">
        <span className="flex size-10 items-center justify-center rounded-xl bg-violet-50 text-violet-600">
          <Scale className="size-5" aria-hidden="true" />
        </span>
        <div>
          <p className="text-sm font-semibold text-slate-900">{component.name}</p>
          {component.subject ? <p className="text-xs text-slate-400">{component.subject.name}</p> : null}
        </div>
      </div>
      <div className="flex items-center gap-3">
        <Badge variant="violet" dot>{component.weight}%</Badge>
        <button
          type="button"
          onClick={() => { setEditing(component); setForm({ name: component.name, weight: String(component.weight), subject_id: String(component.subject?.id ?? '') }); setFormError(null) }}
          className="rounded-lg p-1.5 text-slate-400 transition hover:bg-slate-100 hover:text-slate-700"
          aria-label="Edit component"
        >
          <Pencil className="size-4" />
        </button>
        <button
          type="button"
          onClick={() => handleDelete(component.id)}
          className="rounded-lg p-1.5 text-slate-400 transition hover:bg-rose-50 hover:text-rose-600"
          aria-label="Remove component"
        >
          <Trash2 className="size-4" />
        </button>
      </div>
    </li>
  )

  return (
    <Card>
      <CardHeader
        title="Grading components"
        description="Weighted components used to compute subject averages"
        action={<Badge variant="teal" dot>Default total: {totalWeight}%</Badge>}
      />
      <CardBody>
        <form onSubmit={handleCreate} className="mb-5 grid gap-2 sm:grid-cols-4">
          <Input name="name" label="Component" placeholder="Assignments" value={form.name} onChange={setField('name')} />
          <Input name="weight" label="Weight %" type="number" min="0" max="100" value={form.weight} onChange={setField('weight')} />
          <Select name="subject" label="Subject (optional)" value={form.subject_id} onChange={setField('subject_id')}>
            <option value="">School-wide default</option>
            {subjects?.map((subject) => (
              <option key={subject.id} value={subject.id}>
                {subject.name}
              </option>
            ))}
          </Select>
          <div className="flex items-end">
            <Button type="submit" loading={submitting} className="w-full">
              Add component
            </Button>
          </div>
        </form>
        <ErrorDisplay message={formError} />

        {loading ? (
          <div className="flex justify-center py-8">
            <Spinner />
          </div>
        ) : error ? (
          <p className="text-sm text-slate-500">Could not load components.</p>
        ) : (
          <div className="space-y-5">
            <div>
              <p className="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-400">School-wide defaults</p>
              <ul className="grid gap-2 sm:grid-cols-2">{defaults.map(renderRow)}</ul>
            </div>
            {Object.keys(bySubject).length > 0 ? (
              <div>
                <p className="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-400">Subject-specific</p>
                <ul className="grid gap-2 sm:grid-cols-2">
                  {Object.values(bySubject).flat().map(renderRow)}
                </ul>
              </div>
            ) : null}
          </div>
        )}
      </CardBody>
      <Modal open={Boolean(editing)} onClose={() => setEditing(null)} title="Edit grading component" description="Update the component name, weight, or subject.">
        <form onSubmit={handleUpdate} className="space-y-4">
          <Input name="edit-name" label="Component" value={form.name} onChange={setField('name')} />
          <Input name="edit-weight" label="Weight %" type="number" min="0" max="100" value={form.weight} onChange={setField('weight')} />
          <Select name="edit-subject" label="Subject (optional)" value={form.subject_id} onChange={setField('subject_id')}>
            <option value="">School-wide default</option>
            {subjects?.map((subject) => <option key={subject.id} value={subject.id}>{subject.name}</option>)}
          </Select>
          <ErrorDisplay message={formError} />
          <div className="flex justify-end gap-2"><Button type="button" variant="secondary" onClick={() => setEditing(null)}>Cancel</Button><Button type="submit" loading={submitting}>Save changes</Button></div>
        </form>
      </Modal>
    </Card>
  )
}
