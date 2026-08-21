import { useState } from 'react'
import { Plus } from 'lucide-react'
import { useAsyncList } from '../../hooks/useAsyncList.js'
import {
  createTimetableEntry,
  deleteTimetableEntry,
  listClasses,
  listSubjects,
  listTimetable,
  updateTimetableEntry,
} from '../../services/adminService.js'
import { TimetableBoard } from '../dashboard/TimetableBoard.jsx'
import { TimetableEditorModal } from './TimetableEditorModal.jsx'
import { Card, CardBody, CardHeader } from '../ui/Card.jsx'
import { Button } from '../ui/Button.jsx'
import { Select } from '../ui/Select.jsx'
import { Spinner } from '../ui/Spinner.jsx'

export function TimetableManager() {
  const { data: classes } = useAsyncList(listClasses)
  const { data: subjects } = useAsyncList(listSubjects)
  const [classId, setClassId] = useState('')

  const { data: timetable, loading, error, reload } = useAsyncList(
    () => (classId ? listTimetable(classId) : Promise.resolve({ entries: [] })),
    [classId],
  )

  const [modal, setModal] = useState({ open: false, mode: 'create', entry: null, slot: null, nonce: 0 })
  const [saving, setSaving] = useState(false)
  const [formError, setFormError] = useState(null)

  const openCreate = (start, day) =>
    setModal({ open: true, mode: 'create', entry: null, slot: { start, day }, nonce: Date.now() })

  const openEdit = (entry) =>
    setModal({ open: true, mode: 'edit', entry, slot: null, nonce: Date.now() })

  const close = () => setModal((current) => ({ ...current, open: false }))

  const handleSave = async (payload) => {
    setSaving(true)
    setFormError(null)
    try {
      if (modal.mode === 'edit' && modal.entry?.id) {
        await updateTimetableEntry(modal.entry.id, { ...payload, class_id: classId })
      } else {
        await createTimetableEntry({ ...payload, class_id: classId })
      }
      close()
      await reload()
    } catch (err) {
      setFormError(err?.response?.data?.message ?? 'Could not save the slot.')
    } finally {
      setSaving(false)
    }
  }

  const handleDelete = async () => {
    setSaving(true)
    setFormError(null)
    try {
      await deleteTimetableEntry(modal.entry.id)
      close()
      await reload()
    } catch (err) {
      setFormError(err?.response?.data?.message ?? 'Could not remove the slot.')
    } finally {
      setSaving(false)
    }
  }

  const initial =
    modal.mode === 'edit' && modal.entry
      ? {
          day: String(modal.entry.day),
          start: modal.entry.start,
          end: modal.entry.end,
          subject_id: String(modal.entry.subject?.id ?? ''),
        }
      : {
          day: String(modal.slot?.day ?? 1),
          start: modal.slot?.start ?? '',
          end: '',
          subject_id: '',
        }

  return (
    <Card>
      <CardHeader
        title="Weekly timetable"
        description="Click an empty cell to add, or a subject chip to edit."
        action={
          classId ? (
            <Button size="sm" onClick={() => openCreate('08:00', 1)}>
              <Plus className="size-4" aria-hidden="true" />
              Add slot
            </Button>
          ) : null
        }
      />
      <CardBody>
        <div className="mb-5 max-w-xs">
          <Select
            name="class"
            label="Class"
            value={classId}
            onChange={(event) => setClassId(event.target.value)}
          >
            <option value="">Select a class…</option>
            {classes?.map((item) => (
              <option key={item.id} value={item.id}>
                {item.name}
              </option>
            ))}
          </Select>
        </div>

        {classId ? (
          loading ? (
            <div className="flex justify-center py-10">
              <Spinner />
            </div>
          ) : error ? (
            <p className="text-sm text-slate-500">Could not load the timetable.</p>
          ) : (
            <TimetableBoard
              entries={timetable?.entries ?? []}
              interactive
              legend
              onSelectEntry={openEdit}
              onSelectSlot={openCreate}
            />
          )
        ) : (
          <p className="text-sm text-slate-500">Select a class to view and edit its weekly timetable.</p>
        )}
      </CardBody>

      <TimetableEditorModal
        key={modal.nonce}
        open={modal.open}
        onClose={close}
        mode={modal.mode}
        initial={initial}
        subjects={subjects ?? []}
        onSave={handleSave}
        onDelete={modal.mode === 'edit' ? handleDelete : null}
        saving={saving}
        error={formError}
      />
    </Card>
  )
}
