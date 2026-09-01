import { ChevronLeft, ChevronRight } from 'lucide-react'
import { Button } from '../ui/Button.jsx'

const rangeLabel = (from, to) => {
  const start = new Date(`${from}T00:00:00`)
  const end = new Date(`${to}T00:00:00`)
  const sameMonth = start.getMonth() === end.getMonth()

  const left = start.toLocaleDateString(undefined, {
    day: 'numeric',
    ...(sameMonth ? {} : { month: 'short' }),
  })
  const right = end.toLocaleDateString(undefined, { day: 'numeric', month: 'short', year: 'numeric' })

  return `${left} – ${right}`
}

/** Previous / next week with the resolved range the API actually used. */
export function WeekNav({ from, to, onPrevious, onNext, onToday }) {
  return (
    <div className="flex flex-wrap items-center justify-between gap-2">
      <p className="text-sm font-medium text-slate-700">{rangeLabel(from, to)}</p>
      <div className="flex items-center gap-2">
        <Button variant="secondary" size="sm" onClick={onToday}>
          Today
        </Button>
        <Button variant="secondary" size="icon" onClick={onPrevious} aria-label="Previous week">
          <ChevronLeft className="size-4" aria-hidden="true" />
        </Button>
        <Button variant="secondary" size="icon" onClick={onNext} aria-label="Next week">
          <ChevronRight className="size-4" aria-hidden="true" />
        </Button>
      </div>
    </div>
  )
}
