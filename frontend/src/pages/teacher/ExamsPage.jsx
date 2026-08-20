import { useState } from 'react'
import { CalendarClock, Trophy } from 'lucide-react'
import { useAsyncList } from '../../hooks/useAsyncList.js'
import { listAssignments } from '../../services/teacherService.js'
import { getTeacherRanking, listTeacherExams } from '../../services/examService.js'
import { PageContainer } from '../../components/layout/PageContainer.jsx'
import { PageHeader } from '../../components/ui/PageHeader.jsx'
import { Button } from '../../components/ui/Button.jsx'
import { Select } from '../../components/ui/Select.jsx'
import { Badge } from '../../components/ui/Badge.jsx'
import { Card, CardBody, CardHeader } from '../../components/ui/Card.jsx'
import { Spinner } from '../../components/ui/Spinner.jsx'
import { subjectPalette } from '../../utils/timetable.js'
import { cn } from '../../utils/cn.js'
import { formatDate } from '../../utils/formatters.js'

export default function ExamsPage() {
  const { data: exams, loading, error } = useAsyncList(listTeacherExams)
  const { data: assignments } = useAsyncList(listAssignments)

  const [assignmentId, setAssignmentId] = useState('')
  const [ranking, setRanking] = useState(null)

  const grouped = (exams ?? []).reduce((acc, exam) => {
    ;(acc[exam.date] ??= []).push(exam)
    return acc
  }, {})

  const selectedAssignment = assignments?.find((assignment) => String(assignment.id) === assignmentId)

  const handleRanking = async () => {
    if (!selectedAssignment) return
    setRanking(
      await getTeacherRanking(selectedAssignment.class.id, selectedAssignment.subject.id),
    )
  }

  return (
    <PageContainer>
      <div className="space-y-6">
        <PageHeader title="Exams" description="Your scheduled exam sessions." />

        {loading ? (
          <div className="flex justify-center py-20">
            <Spinner className="size-8" />
          </div>
        ) : error ? (
          <Card className="p-6 text-sm text-slate-500">Could not load your exams.</Card>
        ) : exams?.length === 0 ? (
          <Card className="p-10 text-center text-sm text-slate-500">
            No exam sessions are scheduled for your subjects yet.
          </Card>
        ) : (
          <Card>
            <CardHeader title="Exam timetable" description={`${exams.length} session${exams.length === 1 ? '' : 's'}`} />
            <CardBody>
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
                          <div className="text-sm text-slate-500">
                            {exam.start} – {exam.end} · {exam.room ?? '—'}
                          </div>
                        </li>
                      ))}
                    </ul>
                  </div>
                ))}
              </div>
            </CardBody>
          </Card>
        )}

        <Card>
          <CardHeader title="Class ranking" description="Compile ranked results for one of your assignments" />
          <CardBody>
            <div className="flex flex-col gap-2 sm:flex-row sm:items-end">
              <Select
                name="assignment"
                label="Assignment"
                className="sm:max-w-xs"
                value={assignmentId}
                onChange={(event) => setAssignmentId(event.target.value)}
              >
                <option value="">Select…</option>
                {assignments?.map((assignment) => (
                  <option key={assignment.id} value={assignment.id}>
                    {assignment.subject.name} · {assignment.class.name}
                  </option>
                ))}
              </Select>
              <Button onClick={handleRanking} disabled={!selectedAssignment}>
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
                        <td className="px-3 py-2.5 text-right font-semibold tabular-nums text-slate-800">{entry.average}</td>
                      </tr>
                    ))}
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
