import { Link } from 'react-router-dom'
import { Users } from 'lucide-react'
import { Card } from '../ui/Card.jsx'
import { Badge } from '../ui/Badge.jsx'
import { Button } from '../ui/Button.jsx'
import { subjectPalette } from '../../utils/timetable.js'
import { cn } from '../../utils/cn.js'

export function AssignmentCard({ klass, subject, students, to, gradesTo }) {
  const palette = subjectPalette(subject)
  const hasActions = Boolean(to || gradesTo)

  return (
    <Card interactive className="flex h-full flex-col">
      <div className="flex items-start gap-4 p-5">
        <span
          className={cn(
            'flex size-11 shrink-0 items-center justify-center rounded-xl text-lg font-bold ring-1 ring-inset',
            palette.chip,
          )}
        >
          {String(subject ?? '').slice(0, 1).toUpperCase()}
        </span>
        <div className="min-w-0 flex-1">
          <p className="truncate text-sm font-semibold text-slate-900">{subject}</p>
          <p className="truncate text-sm text-slate-500">{klass}</p>
        </div>
        <Badge variant="neutral" dot>
          <Users className="size-3" aria-hidden="true" />
          {students}
        </Badge>
      </div>

      {hasActions ? (
        <div className="mt-auto flex items-center gap-2 border-t border-slate-100 px-5 py-3">
          {to ? (
            <Link to={to} className="flex-1">
              <Button variant="secondary" size="sm" className="w-full">
                Students
              </Button>
            </Link>
          ) : null}
          {gradesTo ? (
            <Link to={gradesTo} className="flex-1">
              <Button variant="soft" size="sm" className="w-full">
                Grades
              </Button>
            </Link>
          ) : null}
        </div>
      ) : null}
    </Card>
  )
}
