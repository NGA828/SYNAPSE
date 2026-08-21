import { useState } from 'react'
import { BookMarked, Pencil, Trash2 } from 'lucide-react'
import { useAsyncList } from '../../hooks/useAsyncList.js'
import { createSubject, deleteSubject, listSubjects, updateSubject } from '../../services/adminService.js'
import { Button } from '../ui/Button.jsx'
import { Input } from '../ui/Input.jsx'
import { Badge } from '../ui/Badge.jsx'
import { Card, CardBody, CardHeader } from '../ui/Card.jsx'
import { Spinner } from '../ui/Spinner.jsx'
import { ErrorDisplay } from '../forms/ErrorDisplay.jsx'
import { Modal } from '../ui/Modal.jsx'

export function SubjectManager() {
  const { data: subjects, loading, error, reload } = useAsyncList(listSubjects)
  const [name, setName] = useState('')
  const [code, setCode] = useState('')
  const [formError, setFormError] = useState(null)
  const [submitting, setSubmitting] = useState(false)
  const [editing, setEditing] = useState(null)

  const handleCreate = async (event) => {
    event.preventDefault()
    setSubmitting(true)
    setFormError(null)
    try {
      await createSubject({ name, code: code || null })
      setName('')
      setCode('')
      await reload()
    } catch (err) {
      setFormError(err?.response?.data?.message ?? 'Could not create the subject.')
    } finally {
      setSubmitting(false)
    }
  }

  const handleUpdate = async (event) => {
    event.preventDefault()
    setSubmitting(true)
    setFormError(null)
    try {
      await updateSubject(editing.id, { name, code: code || null })
      setEditing(null)
      setName('')
      setCode('')
      await reload()
    } catch (err) {
      setFormError(err?.response?.data?.message ?? 'Could not update the subject.')
    } finally {
      setSubmitting(false)
    }
  }

  const handleDelete = async (subject) => {
    if (!window.confirm(`Remove ${subject.name}? Related assignments and timetable entries will also be removed.`)) return
    setFormError(null)
    try {
      await deleteSubject(subject.id)
      await reload()
    } catch (err) {
      setFormError(err?.response?.data?.message ?? 'Could not remove the subject.')
    }
  }

  return (
    <Card>
      <CardHeader
        title="Subjects"
        description="Add subjects with optional codes"
        action={<Badge variant="teal" dot>{subjects?.length ?? 0} subjects</Badge>}
      />
      <CardBody>
        <form onSubmit={handleCreate} className="mb-5 flex gap-2">
          <Input
            name="subject"
            placeholder="e.g. Chemistry"
            value={name}
            onChange={(event) => setName(event.target.value)}
          />
          <Input
            name="code"
            placeholder="Code"
            className="max-w-24"
            value={code}
            onChange={(event) => setCode(event.target.value)}
          />
          <Button type="submit" loading={submitting}>
            Add
          </Button>
        </form>
        <ErrorDisplay message={formError} />

        {loading ? (
          <div className="flex justify-center py-8">
            <Spinner />
          </div>
        ) : error ? (
          <p className="text-sm text-slate-500">Could not load subjects.</p>
        ) : (
          <ul className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            {subjects?.map((item) => (
              <li
                key={item.id}
                className="flex items-center gap-3 rounded-2xl border border-slate-200 bg-white p-4 transition hover:border-slate-300 hover:shadow-sm"
              >
                <span className="flex size-10 shrink-0 items-center justify-center rounded-xl bg-violet-50 text-violet-600">
                  <BookMarked className="size-5" aria-hidden="true" />
                </span>
                <span className="min-w-0 flex-1 truncate text-sm font-semibold text-slate-800">
                  {item.name}
                </span>
                {item.code ? <Badge variant="neutral">{item.code}</Badge> : null}
                <span className="flex gap-1">
                  <Button type="button" size="sm" variant="ghost" title="Edit subject" onClick={() => { setEditing(item); setName(item.name); setCode(item.code ?? '') }}>
                    <Pencil className="size-4" aria-hidden="true" />
                  </Button>
                  <Button type="button" size="sm" variant="ghost" title="Delete subject" onClick={() => handleDelete(item)}>
                    <Trash2 className="size-4 text-rose-600" aria-hidden="true" />
                  </Button>
                </span>
              </li>
            ))}
          </ul>
        )}
      </CardBody>
      <Modal open={Boolean(editing)} onClose={() => setEditing(null)} title="Edit subject" description="Update the subject name or code.">
        <form onSubmit={handleUpdate} className="space-y-4">
          <Input label="Subject" value={name} onChange={(event) => setName(event.target.value)} />
          <Input label="Code" value={code} onChange={(event) => setCode(event.target.value)} />
          <ErrorDisplay message={formError} />
          <div className="flex justify-end gap-2"><Button type="button" variant="secondary" onClick={() => setEditing(null)}>Cancel</Button><Button type="submit" loading={submitting}>Save changes</Button></div>
        </form>
      </Modal>
    </Card>
  )
}
