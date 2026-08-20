import { useState } from 'react'
import { Link, useParams } from 'react-router-dom'
import { CheckCircle2, ShieldAlert } from 'lucide-react'
import { useAsync } from '../../hooks/useAsyncList.js'
import { getGradebook, saveGrades } from '../../services/teacherService.js'
import { PageContainer } from '../../components/layout/PageContainer.jsx'
import { PageHeader } from '../../components/ui/PageHeader.jsx'
import { GradebookForm } from '../../components/teacher/GradebookForm.jsx'
import { Card, CardBody } from '../../components/ui/Card.jsx'
import { Button } from '../../components/ui/Button.jsx'
import { Spinner } from '../../components/ui/Spinner.jsx'
import { ErrorDisplay } from '../../components/forms/ErrorDisplay.jsx'
import { subjectPalette } from '../../utils/timetable.js'
import { cn } from '../../utils/cn.js'
import { formatDecimal } from '../../utils/formatters.js'

export default function GradebookPage() {
  const { classId, subjectId } = useParams()
  const { data, loading, error, reload } = useAsync(() => getGradebook(classId, subjectId), [classId, subjectId])
  const [submitting, setSubmitting] = useState(false)
  const [saved, setSaved] = useState(false)
  const [saveError, setSaveError] = useState(null)

  const forbidden = error?.response?.status === 403 || error?.response?.status === 409
  const palette = subjectPalette(data?.subject?.name)
  const students = data?.students ?? []

  const graded = students.filter(
    (student) => student.test1 != null || student.test2 != null || student.exam != null,
  ).length
  const classAverage = (() => {
    const averages = students.map((student) => student.average).filter((value) => value != null)
    if (averages.length === 0) return null
    return averages.reduce((sum, value) => sum + value, 0) / averages.length
  })()

  const handleSave = async (entries) => {
    setSubmitting(true)
    setSaved(false)
    setSaveError(null)
    try {
      await saveGrades(classId, subjectId, entries)
      setSaved(true)
      await reload()
    } catch (err) {
      setSaveError(err?.response?.data?.message ?? 'Could not save grades.')
    } finally {
      setSubmitting(false)
    }
  }

  return (
    <PageContainer>
      <div className="space-y-6">
        <PageHeader
          title="Grade entry"
          description={`${data?.subject?.name ?? 'Subject'} · ${data?.class?.name ?? 'Class'} · ${data?.academic_year?.name ?? ''}`}
          back="/teacher/grades"
        />

        {loading ? (
          <div className="flex justify-center py-20">
            <Spinner className="size-8" />
          </div>
        ) : forbidden ? (
          <Card className="p-10 text-center">
            <ShieldAlert className="mx-auto size-10 text-rose-500" aria-hidden="true" />
            <p className="mt-3 text-base font-semibold text-slate-800">Access denied</p>
            <p className="mt-1 text-sm text-slate-500">
              You are not assigned to teach this subject in this class.
            </p>
            <Link to="/teacher" className="mt-5 inline-block">
              <Button variant="secondary" size="sm">
                Back to my assignments
              </Button>
            </Link>
          </Card>
        ) : error ? (
          <Card className="p-6 text-sm text-slate-500">Could not load the gradebook.</Card>
        ) : (
          <>
            <div className="overflow-hidden rounded-2xl bg-gradient-to-br from-violet-600 to-fuchsia-600">
              <div className="flex flex-col gap-5 p-6 text-white sm:flex-row sm:items-center sm:justify-between">
                <div className="flex items-center gap-4">
                  <span className={cn('flex size-14 items-center justify-center rounded-2xl text-2xl font-bold ring-1 ring-inset', palette.chip)}>
                    {String(data?.subject?.name ?? '').slice(0, 1).toUpperCase()}
                  </span>
                  <div>
                    <p className="text-xs uppercase tracking-wide text-violet-100">Gradebook</p>
                    <p className="text-2xl font-bold">{data?.subject?.name} · {data?.class?.name}</p>
                    <p className="text-sm text-violet-100">Scores out of 20 · {data?.academic_year?.name}</p>
                  </div>
                </div>
                <div className="grid grid-cols-3 gap-2">
                  <div className="rounded-2xl bg-white/10 px-4 py-3 text-center">
                    <p className="text-2xl font-bold">{students.length}</p>
                    <p className="text-xs text-violet-100">Students</p>
                  </div>
                  <div className="rounded-2xl bg-white/10 px-4 py-3 text-center">
                    <p className="text-2xl font-bold">{graded}</p>
                    <p className="text-xs text-violet-100">Graded</p>
                  </div>
                  <div className="rounded-2xl bg-white/10 px-4 py-3 text-center">
                    <p className="text-2xl font-bold">{formatDecimal(classAverage)}</p>
                    <p className="text-xs text-violet-100">Avg</p>
                  </div>
                </div>
              </div>
            </div>

            {saved ? (
              <div className="flex items-center gap-2 rounded-xl border border-emerald-200 bg-emerald-50 px-3.5 py-3 text-sm text-emerald-700">
                <CheckCircle2 className="size-4" aria-hidden="true" />
                Grades saved successfully.
              </div>
            ) : null}
            <ErrorDisplay message={saveError} />

            <Card>
              <CardBody>
                <GradebookForm
                  key={`${classId}-${subjectId}`}
                  students={students}
                  onSubmit={handleSave}
                  submitting={submitting}
                />
              </CardBody>
            </Card>
          </>
        )}
      </div>
    </PageContainer>
  )
}
