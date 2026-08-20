import { useState } from 'react'
import { CheckCircle2 } from 'lucide-react'
import { useAsyncList, useAsync } from '../../hooks/useAsyncList.js'
import { listClasses } from '../../services/adminService.js'
import { getAdminRoster, saveAdminAttendance } from '../../services/attendanceService.js'
import { PageContainer } from '../../components/layout/PageContainer.jsx'
import { PageHeader } from '../../components/ui/PageHeader.jsx'
import { AttendanceCard } from '../../components/attendance/AttendanceCard.jsx'
import { Card, CardBody, CardHeader } from '../../components/ui/Card.jsx'
import { Input } from '../../components/ui/Input.jsx'
import { Select } from '../../components/ui/Select.jsx'
import { Spinner } from '../../components/ui/Spinner.jsx'
import { ErrorDisplay } from '../../components/forms/ErrorDisplay.jsx'
import { todayString } from '../../utils/attendance.js'

export default function AttendancePage() {
  const { data: classes } = useAsyncList(listClasses)
  const [classId, setClassId] = useState('')
  const [date, setDate] = useState(todayString())
  const [saving, setSaving] = useState(false)
  const [saved, setSaved] = useState(false)
  const [saveError, setSaveError] = useState(null)

  const { data, loading, error } = useAsync(
    () => (classId ? getAdminRoster(classId, date) : Promise.resolve(null)),
    [classId, date],
  )

  const handleSave = async (records) => {
    setSaving(true)
    setSaved(false)
    setSaveError(null)
    try {
      await saveAdminAttendance({ class_id: classId, date, records })
      setSaved(true)
    } catch (err) {
      setSaveError(err?.response?.data?.message ?? 'Could not save attendance.')
    } finally {
      setSaving(false)
    }
  }

  return (
    <PageContainer>
      <div className="space-y-6">
        <PageHeader title="Attendance" description="View and manage attendance for any class." />

        <Card>
          <CardHeader title="Select class & date" />
          <CardBody className="flex flex-col gap-3 sm:flex-row sm:items-end">
            <Select
              name="class"
              label="Class"
              className="sm:max-w-xs"
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
            <Input
              name="date"
              label="Date"
              type="date"
              className="sm:max-w-48"
              value={date}
              onChange={(event) => setDate(event.target.value)}
            />
          </CardBody>
        </Card>

        {classId ? (
          loading ? (
            <div className="flex justify-center py-20">
              <Spinner className="size-8" />
            </div>
          ) : error ? (
            <Card className="p-6 text-sm text-slate-500">Could not load the roster.</Card>
          ) : (
            <>
              {saved ? (
                <div className="flex items-center gap-2 rounded-xl border border-emerald-200 bg-emerald-50 px-3.5 py-3 text-sm text-emerald-700">
                  <CheckCircle2 className="size-4" aria-hidden="true" />
                  Attendance saved for {date}.
                </div>
              ) : null}
              <ErrorDisplay message={saveError} />
              <AttendanceCard key={`${classId}-${date}`} roster={data} onSave={handleSave} saving={saving} />
            </>
          )
        ) : (
          <Card className="p-10 text-center text-sm text-slate-500">
            Select a class to view its attendance.
          </Card>
        )}
      </div>
    </PageContainer>
  )
}
