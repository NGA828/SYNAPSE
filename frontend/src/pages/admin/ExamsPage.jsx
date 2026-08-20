import { useState } from 'react'
import { CalendarClock, Trophy, Trash2 } from 'lucide-react'
import { useAsyncList } from '../../hooks/useAsyncList.js'
import { listClasses, listSubjects } from '../../services/adminService.js'
import { listSemesters } from '../../services/semesterService.js'
import {
  createExam,
  deleteExam,
  getAdminRanking,
  listAdminExams,
} from '../../services/examService.js'
import { PageContainer } from '../../components/layout/PageContainer.jsx'
import { PageHeader } from '../../components/ui/PageHeader.jsx'
import { Button } from '../../components/ui/Button.jsx'
import { Input } from '../../components/ui/Input.jsx'
import { Select } from '../../components/ui/Select.jsx'
import { Badge } from '../../components/ui/Badge.jsx'
import { Card, CardBody, CardHeader } from '../../components/ui/Card.jsx'
import { Spinner } from '../../components/ui/Spinner.jsx'
import { ErrorDisplay } from '../../components/forms/ErrorDisplay.jsx'
import { subjectPalette } from '../../utils/timetable.js'
import { cn } from '../../utils/cn.js'
import { formatDate } from '../../utils/formatters.js'

export default function ExamsPage() {
  const { data: classes } = useAsyncList(listClasses)
  const { data: subjects } = useAsyncList(listSubjects)
  const { data: semestersData } = useAsyncList(listSemesters)
  const semesters = semestersData?.data ?? []

  const [classId, setClassId] = useState('')
  const { data: exams, loading, error, reload } = useAsyncList(
    () => listAdminExams(classId || undefined),
    [classId],
  )

  const [form, setForm] = useState({ subject_id: '', class_id: '', semester_id: '', date: '', start: '', end: '', room: '' })
  const [formError, setFormError] = useState(null)
  const [submitting, setSubmitting] = useState(false)

  const [rankClass, setRankClass] = useState('')
  const [rankSubject, setRankSubject] = useState('')
  const [rankSemester, setRankSemester] = useState('')
  const [ranking, setRanking] = useState(null)

  const setField = (field) => (event) => setForm((current) => ({ ...current, [field]: event.target.value }))

  const handleCreate = async (event) => {
    event.preventDefault()
    setSubmitting(true)
    setFormError(null)
    try {
      await createExam({ ...form, semester_id: form.semester_id || null })
      setForm({ subject_id: '', class_id: '', semester_id: '', date: '', start: '', end: '', room: '' })
      await reload()
    } catch (err) {
      setFormError(err?.response?.data?.message ?? 'Could not create the exam session.')
    } finally {
      setSubmitting(false)
    }
  }

  const handleDelete = async (id) => {
    await deleteExam(id)
    await reload()
  }

  const handleRanking = async () => {
    if (!rankClass || !rankSubject) return
    setRanking(await getAdminRanking(rankClass, rankSubject, rankSemester || undefined))
  }

  const grouped = (exams ?? []).reduce((acc, exam) => {
    ;(acc[exam.date] ??= []).push(exam)
    return acc
  }, {})

  return (
    <PageContainer>
      <div className="space-y-6">
        <PageHeader title="Exams" description="Schedule exam sessions and compile results." />

        <div className="grid gap-6 lg:grid-cols-3">
          <Card className="self-start lg:col-span-1">
            <CardHeader title="Schedule an exam" description="Add a session to the timetable" />
            <CardBody>
              <form onSubmit={handleCreate} className="space-y-3">
                <Select name="class" label="Class" value={form.class_id} onChange={setField('class_id')}>
                  <option value="">Select…</option>
                  {classes?.map((item) => (
                    <option key={item.id} value={item.id}>{item.name}</option>
                  ))}
                </Select>
                <Select name="subject" label="Subject" value={form.subject_id} onChange={setField('subject_id')}>
                  <option value="">Select…</option>
                  {subjects?.map((subject) => (
                    <option key={subject.id} value={subject.id}>{subject.name}</option>
                  ))}
                </Select>
                <Select name="semester" label="Semester" value={form.semester_id} onChange={setField('semester_id')}>
                  <option value="">Full year</option>
                  {semesters.map((semester) => (
                    <option key={semester.id} value={semester.id}>{semester.name}</option>
                  ))}
                </Select>
                <Input name="date" label="Date" type="date" value={form.date} onChange={setField('date')} />
                <div className="grid grid-cols-2 gap-2">
                  <Input name="start" label="Start" type="time" value={form.start} onChange={setField('start')} />
                  <Input name="end" label="End" type="time" value={form.end} onChange={setField('end')} />
                </div>
                <Input name="room" label="Room" placeholder="Hall A" value={form.room} onChange={setField('room')} />
                <Button type="submit" loading={submitting} className="w-full">Schedule</Button>
              </form>
              <div className="mt-3"><ErrorDisplay message={formError} /></div>
            </CardBody>
          </Card>

          <Card className="lg:col-span-2">
            <CardHeader
              title="Exam timetable"
              action={
                <Select name="class" className="w-44" value={classId} onChange={(event) => setClassId(event.target.value)}>
                  <option value="">All classes</option>
                  {classes?.map((item) => (
                    <option key={item.id} value={item.id}>{item.name}</option>
                  ))}
                </Select>
              }
            />
            <CardBody>
              {loading ? (
                <div className="flex justify-center py-10"><Spinner /></div>
              ) : error ? (
                <p className="text-sm text-slate-500">Could not load exams.</p>
              ) : exams?.length === 0 ? (
                <p className="py-8 text-center text-sm text-slate-500">No exam sessions scheduled.</p>
              ) : (
                <div className="space-y-5">
                  {Object.entries(grouped).map(([date, sessions]) => (
                    <div key={date}>
                      <p className="mb-2 flex items-center gap-2 text-xs font-semibold uppercase tracking-wide text-slate-400">
                        <CalendarClock className="size-3.5" aria-hidden="true" />
                        {formatDate(date)}
                      </p>
                      <ul className="space-y-2">
                        {sessions.map((exam) => (
                          <li key={exam.id} className="flex items-center justify-between rounded-xl border border-slate-200 px-3 py-2">
                            <div className="flex items-center gap-2.5">
                              <span className={cn('size-2 rounded-full', subjectPalette(exam.subject?.name).dot)} />
                              <span className="text-sm font-medium text-slate-800">{exam.subject?.name}</span>
                              <Badge variant="neutral">{exam.class?.name}</Badge>
                              {exam.semester ? <Badge variant="info">{exam.semester.name}</Badge> : null}
                            </div>
                            <div className="flex items-center gap-3 text-sm text-slate-500">
                              <span>{exam.start} – {exam.end}</span>
                              <span className="hidden sm:inline">{exam.room ?? '—'}</span>
                              <button
                                type="button"
                                onClick={() => handleDelete(exam.id)}
                                className="rounded-lg p-1 text-slate-400 transition hover:bg-rose-50 hover:text-rose-600"
                                aria-label="Remove exam"
                              >
                                <Trash2 className="size-4" />
                              </button>
                            </div>
                          </li>
                        ))}
                      </ul>
                    </div>
                  ))}
                </div>
              )}
            </CardBody>
          </Card>
        </div>

        <Card>
          <CardHeader title="Result compilation" description="Rank students by term average per class and subject" />
          <CardBody>
            <div className="flex flex-col gap-2 sm:flex-row sm:items-end">
              <Select name="class" label="Class" className="sm:max-w-xs" value={rankClass} onChange={(event) => setRankClass(event.target.value)}>
                <option value="">Select…</option>
                {classes?.map((item) => (<option key={item.id} value={item.id}>{item.name}</option>))}
              </Select>
              <Select name="subject" label="Subject" className="sm:max-w-xs" value={rankSubject} onChange={(event) => setRankSubject(event.target.value)}>
                <option value="">Select…</option>
                {subjects?.map((subject) => (<option key={subject.id} value={subject.id}>{subject.name}</option>))}
              </Select>
              <Select name="semester" label="Semester" className="sm:max-w-xs" value={rankSemester} onChange={(event) => setRankSemester(event.target.value)}>
                <option value="">All year</option>
                {semesters.map((semester) => (<option key={semester.id} value={semester.id}>{semester.name}</option>))}
              </Select>
              <Button onClick={handleRanking} disabled={!rankClass || !rankSubject}>
                <Trophy className="size-4" aria-hidden="true" />
                Compile ranking
              </Button>
            </div>

            {ranking ? (
              <div className="mt-5 overflow-x-auto">
                <table className="w-full min-w-[28rem] text-sm">
                  <thead>
                    <tr className="border-b border-slate-100 text-left text-xs uppercase tracking-wide text-slate-400">
                      <th className="px-3 py-2.5 font-semibold">Rank</th>
                      <th className="px-3 py-2.5 font-semibold">Student</th>
                      <th className="px-3 py-2.5 font-semibold">Matricule</th>
                      <th className="px-3 py-2.5 text-right font-semibold">Average</th>
                    </tr>
                  </thead>
                  <tbody>
                    {ranking.ranking.map((entry) => (
                      <tr key={entry.student_id} className="border-b border-slate-50 last:border-0">
                        <td className="px-3 py-2.5">
                          <Badge variant={entry.rank === 1 ? 'warning' : 'neutral'} dot>#{entry.rank}</Badge>
                        </td>
                        <td className="px-3 py-2.5 font-medium text-slate-800">{entry.name}</td>
                        <td className="px-3 py-2.5 font-mono text-xs text-slate-500">{entry.matricule}</td>
                        <td className="px-3 py-2.5 text-right font-semibold tabular-nums text-slate-800">{entry.average}</td>
                      </tr>
                    ))}
                    {ranking.ranking.length === 0 ? (
                      <tr><td colSpan={4} className="px-3 py-8 text-center text-sm text-slate-500">No grades recorded for this selection.</td></tr>
                    ) : null}
                  </tbody>
                </table>
              </div>
            ) : null}
          </CardBody>
        </Card>
      </div>
    </PageContainer>
  )
}
