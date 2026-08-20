import { useState } from 'react'
import { ClipboardCheck } from 'lucide-react'
import { Card, CardBody, CardHeader } from '../ui/Card.jsx'
import { Button } from '../ui/Button.jsx'
import { Badge } from '../ui/Badge.jsx'
import { AttendanceRoster } from './AttendanceRoster.jsx'

/**
 * Self-contained roll-call card. Rendered with a `key` derived from class +
 * date so the editable marks reset cleanly when the selection changes.
 */
export function AttendanceCard({ roster, onSave, saving }) {
  const students = roster?.students ?? []

  const [marks, setMarks] = useState(() =>
    Object.fromEntries(students.map((student) => [student.id, student.status ?? null])),
  )

  const handleChange = (id, status) => setMarks((current) => ({ ...current, [id]: status }))

  const markAllPresent = () =>
    setMarks((current) => {
      const next = { ...current }
      for (const student of students) next[student.id] = 'present'
      return next
    })

  const submit = () => {
    const records = students
      .map((student) => ({ student_id: student.id, status: marks[student.id] }))
      .filter((record) => record.status)
    onSave(records)
  }

  return (
    <Card>
      <CardHeader
        title={`${roster?.class?.name ?? 'Class'} — ${roster?.date ?? ''}`}
        description={`${students.length} students · ${roster?.academic_year?.name ?? ''}`}
        action={
          <>
            <Badge variant="teal" dot>Roll call</Badge>
            <Button size="sm" variant="secondary" onClick={markAllPresent}>
              <ClipboardCheck className="size-4" aria-hidden="true" />
              Mark all present
            </Button>
            <Button size="sm" onClick={submit} loading={saving}>
              Save attendance
            </Button>
          </>
        }
      />
      <CardBody>
        <AttendanceRoster students={students} marks={marks} onChange={handleChange} />
      </CardBody>
    </Card>
  )
}
