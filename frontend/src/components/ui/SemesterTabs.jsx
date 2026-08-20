import { cn } from '../../utils/cn.js'

/**
 * Semester filter tabs ("All" + each grading period).
 */
export function SemesterTabs({ semesters = [], value, onChange }) {
  if (!semesters || semesters.length === 0) return null

  return (
    <div className="scrollbar-thin flex gap-1 overflow-x-auto rounded-2xl border border-slate-200 bg-white p-1.5">
      <button
        type="button"
        onClick={() => onChange('')}
        className={cn(
          'shrink-0 rounded-xl px-4 py-2 text-sm font-medium transition',
          value === '' ? 'bg-brand-600 text-white' : 'text-slate-600 hover:bg-slate-100',
        )}
      >
        All year
      </button>
      {semesters.map((semester) => (
        <button
          key={semester.id}
          type="button"
          onClick={() => onChange(String(semester.id))}
          className={cn(
            'shrink-0 rounded-xl px-4 py-2 text-sm font-medium transition',
            value === String(semester.id) ? 'bg-brand-600 text-white' : 'text-slate-600 hover:bg-slate-100',
          )}
        >
          {semester.name}
          {semester.is_current ? ' · Current' : ''}
        </button>
      ))}
    </div>
  )
}
