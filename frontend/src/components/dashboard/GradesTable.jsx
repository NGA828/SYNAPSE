import { formatDecimal } from '../../utils/formatters.js'
import { subjectPalette } from '../../utils/timetable.js'
import { cn } from '../../utils/cn.js'
import { Badge } from '../ui/Badge.jsx'
import { EmptyState } from './EmptyState.jsx'

function averageVariant(value) {
  const num = Number(value)
  if (num >= 16) return 'success'
  if (num >= 12) return 'info'
  return 'warning'
}

export function GradesTable({ grades = [] }) {
  if (!grades || grades.length === 0) {
    return (
      <EmptyState
        title="No grades yet"
        description="Your subject grades will appear here once teachers publish them."
      />
    )
  }

  return (
    <div className="overflow-x-auto">
      <table className="w-full min-w-[30rem] text-sm">
        <thead>
          <tr className="border-b border-slate-100 text-left text-xs uppercase tracking-wide text-slate-400">
            <th className="px-4 py-3 font-semibold">Subject</th>
            <th className="px-4 py-3 text-right font-semibold">Test 1</th>
            <th className="px-4 py-3 text-right font-semibold">Test 2</th>
            <th className="px-4 py-3 text-right font-semibold">Exam</th>
            <th className="px-4 py-3 text-right font-semibold">Average</th>
          </tr>
        </thead>
        <tbody>
          {grades.map((grade) => {
            const palette = subjectPalette(grade.subject)
            return (
              <tr
                key={grade.subject}
                className="border-b border-slate-50 transition last:border-0 hover:bg-slate-50/70"
              >
                <td className="px-4 py-3">
                  <span className="inline-flex items-center gap-2">
                    <span className={cn('size-2 shrink-0 rounded-full', palette.dot)} />
                    <span className="font-medium text-slate-800">{grade.subject}</span>
                    {grade.subject_code ? (
                      <span className="font-mono text-xs text-slate-400">{grade.subject_code}</span>
                    ) : null}
                  </span>
                </td>
                <td className="px-4 py-3 text-right tabular-nums text-slate-600">{grade.test1 ?? '—'}</td>
                <td className="px-4 py-3 text-right tabular-nums text-slate-600">{grade.test2 ?? '—'}</td>
                <td className="px-4 py-3 text-right tabular-nums text-slate-600">{grade.exam ?? '—'}</td>
                <td className="px-4 py-3 text-right">
                  <Badge variant={averageVariant(grade.average)} dot>
                    {formatDecimal(grade.average)}
                  </Badge>
                </td>
              </tr>
            )
          })}
        </tbody>
      </table>
    </div>
  )
}
