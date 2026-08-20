import { useState } from 'react'
import { computeAverage } from '../../utils/grades.js'
import { formatDecimal } from '../../utils/formatters.js'
import { Button } from '../ui/Button.jsx'
import { Avatar } from '../ui/Avatar.jsx'

function ScoreInput({ value, onChange, label }) {
  return (
    <input
      type="number"
      inputMode="decimal"
      min="0"
      max="20"
      step="0.5"
      value={value}
      onChange={onChange}
      aria-label={label}
      className="h-9 w-20 rounded-lg border border-slate-300 bg-white px-2 text-right text-sm text-slate-800 focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-100"
      placeholder="—"
    />
  )
}

export function GradebookForm({ students = [], onSubmit, submitting }) {
  // State is initialised from props; the parent remounts this form (via key)
  // when the class/subject changes, so the editable grid resets cleanly.
  const [entries, setEntries] = useState(() =>
    students.map((student) => ({
      student_id: student.id,
      test1: student.test1 ?? '',
      test2: student.test2 ?? '',
      exam: student.exam ?? '',
    })),
  )

  const setField = (index, field) => (event) => {
    setEntries((current) =>
      current.map((row, rowIndex) => (rowIndex === index ? { ...row, [field]: event.target.value } : row)),
    )
  }

  const handleSubmit = (event) => {
    event.preventDefault()
    onSubmit(
      entries.map((row) => ({
        student_id: row.student_id,
        test1: row.test1 === '' ? null : Number(row.test1),
        test2: row.test2 === '' ? null : Number(row.test2),
        exam: row.exam === '' ? null : Number(row.exam),
      })),
    )
  }

  return (
    <form onSubmit={handleSubmit}>
      <div className="overflow-x-auto">
        <table className="w-full min-w-[34rem] text-sm">
          <thead>
            <tr className="border-b border-slate-100 text-left text-xs uppercase tracking-wide text-slate-400">
              <th className="px-3 py-2.5 font-semibold">Student</th>
              <th className="px-3 py-2.5 text-right font-semibold">Test 1</th>
              <th className="px-3 py-2.5 text-right font-semibold">Test 2</th>
              <th className="px-3 py-2.5 text-right font-semibold">Exam</th>
              <th className="px-3 py-2.5 text-right font-semibold">Average</th>
            </tr>
          </thead>
          <tbody>
            {entries.map((row, index) => {
              const student = students[index]
              return (
                <tr key={row.student_id} className="border-b border-slate-50 last:border-0">
                  <td className="px-3 py-2.5">
                    <span className="flex items-center gap-3">
                      <Avatar name={student?.name} size="sm" />
                      <span>
                        <span className="block font-medium text-slate-800">{student?.name}</span>
                        <span className="block font-mono text-xs text-slate-400">{student?.matricule}</span>
                      </span>
                    </span>
                  </td>
                  <td className="px-3 py-2.5 text-right">
                    <ScoreInput
                      label={`Test 1 for ${student?.name}`}
                      value={row.test1}
                      onChange={setField(index, 'test1')}
                    />
                  </td>
                  <td className="px-3 py-2.5 text-right">
                    <ScoreInput
                      label={`Test 2 for ${student?.name}`}
                      value={row.test2}
                      onChange={setField(index, 'test2')}
                    />
                  </td>
                  <td className="px-3 py-2.5 text-right">
                    <ScoreInput
                      label={`Exam for ${student?.name}`}
                      value={row.exam}
                      onChange={setField(index, 'exam')}
                    />
                  </td>
                  <td className="px-3 py-2.5 text-right font-semibold text-slate-700">
                    {formatDecimal(computeAverage(row.test1, row.test2, row.exam))}
                  </td>
                </tr>
              )
            })}
          </tbody>
        </table>
      </div>

      <div className="mt-4 flex items-center justify-end gap-3">
        <p className="text-xs text-slate-400">Average = mean of entered scores (out of 20)</p>
        <Button type="submit" loading={submitting}>
          Save grades
        </Button>
      </div>
    </form>
  )
}
