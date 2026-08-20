import { formatDecimal } from '../../utils/formatters.js'
import { subjectPalette } from '../../utils/timetable.js'
import { cn } from '../../utils/cn.js'
import { Badge } from '../ui/Badge.jsx'
import { EmptyState } from './EmptyState.jsx'

function remark(value) {
  const num = Number(value)
  if (num == null || Number.isNaN(num)) return { label: '—', variant: 'neutral' }
  if (num >= 16) return { label: 'Excellent', variant: 'success' }
  if (num >= 12) return { label: 'Good', variant: 'info' }
  if (num >= 8) return { label: 'Fair', variant: 'warning' }
  return { label: 'Needs work', variant: 'danger' }
}

export function ReportCardTable({ grades = [] }) {
  if (!grades || grades.length === 0) {
    return <EmptyState title="No grades yet" description="Your report card will fill in once teachers publish grades." />
  }

  const overall = (() => {
    const values = grades.map((grade) => grade.average).filter((value) => value != null)
    if (values.length === 0) return null
    return values.reduce((sum, value) => sum + value, 0) / values.length
  })()
  const overallRemark = remark(overall)

  return (
    <div className="overflow-x-auto">
      <table className="w-full min-w-[34rem] text-sm">
        <thead>
          <tr className="border-b border-slate-200 text-left text-xs uppercase tracking-wide text-slate-400">
            <th className="px-4 py-3 font-semibold">Subject</th>
            <th className="px-4 py-3 text-right font-semibold">Test 1</th>
            <th className="px-4 py-3 text-right font-semibold">Test 2</th>
            <th className="px-4 py-3 text-right font-semibold">Exam</th>
            <th className="px-4 py-3 text-right font-semibold">Average</th>
            <th className="px-4 py-3 text-right font-semibold">Remark</th>
          </tr>
        </thead>
        <tbody>
          {grades.map((grade) => {
            const palette = subjectPalette(grade.subject)
            const r = remark(grade.average)
            return (
              <tr key={grade.subject} className="border-b border-slate-100 last:border-0">
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
                <td className="px-4 py-3 text-right font-semibold tabular-nums text-slate-800">
                  {formatDecimal(grade.average)}
                </td>
                <td className="px-4 py-3 text-right">
                  <Badge variant={r.variant} dot>{r.label}</Badge>
                </td>
              </tr>
            )
          })}
        </tbody>
        <tfoot>
          <tr className="border-t-2 border-slate-200 bg-slate-50/60">
            <td className="px-4 py-3 font-semibold text-slate-700">Term average</td>
            <td colSpan={3} />
            <td className="px-4 py-3 text-right text-base font-bold tabular-nums text-slate-900">
              {formatDecimal(overall)}
            </td>
            <td className="px-4 py-3 text-right">
              <Badge variant={overallRemark.variant} dot>{overallRemark.label}</Badge>
            </td>
          </tr>
        </tfoot>
      </table>
    </div>
  )
}
