import { cn } from '../../utils/cn.js'

const tones = {
  brand: 'bg-brand-500',
  violet: 'bg-violet-500',
  teal: 'bg-teal-500',
  emerald: 'bg-emerald-500',
  amber: 'bg-amber-500',
  rose: 'bg-rose-500',
  sky: 'bg-sky-500',
}

/**
 * Horizontal bar list — label + value with a proportional bar.
 * Good for categorical distributions (subjects, school statuses, plans).
 */
export function BarList({ items = [], formatValue, className }) {
  const max = Math.max(1, ...items.map((item) => Number(item.value ?? 0)))

  return (
    <div className={cn('space-y-3.5', className)}>
      {items.map((item) => (
        <div key={item.label}>
          <div className="flex items-center justify-between text-sm">
            <span className="flex items-center gap-2 text-slate-600">
              {item.dot ? <span className={cn('size-2 rounded-full', item.dot)} /> : null}
              {item.label}
            </span>
            <span className="font-semibold tabular-nums text-slate-800">
              {formatValue ? formatValue(item.value) : item.value}
            </span>
          </div>
          <div className="mt-1.5 h-2 overflow-hidden rounded-full bg-slate-100">
            <div
              className={cn('h-full rounded-full transition-all duration-500', tones[item.tone] ?? 'bg-brand-500')}
              style={{ width: `${Math.max(4, Math.round((Number(item.value ?? 0) / max) * 100))}%` }}
            />
          </div>
        </div>
      ))}
    </div>
  )
}
