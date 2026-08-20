import { Avatar } from '../ui/Avatar.jsx'
import { Badge } from '../ui/Badge.jsx'
import { roleMeta } from '../../utils/roleMeta.js'

function greeting() {
  const hour = new Date().getHours()
  if (hour < 12) return 'Good morning'
  if (hour < 18) return 'Good afternoon'
  return 'Good evening'
}

export function WelcomeHeader({ name, subtitle, role, children }) {
  const today = new Date()
  const date = today.toLocaleDateString('en-GB', {
    weekday: 'long',
    day: 'numeric',
    month: 'long',
    year: 'numeric',
  })
  const meta = roleMeta(role)

  return (
    <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
      <div className="flex items-center gap-4">
        <Avatar name={name} size="lg" />
        <div className="min-w-0">
          <p className="text-sm font-medium text-slate-500">{greeting()},</p>
          <div className="flex flex-wrap items-center gap-2">
            <h1 className="truncate text-2xl font-bold tracking-tight text-slate-900">{name}</h1>
            {meta ? (
              <Badge variant={meta.badge} dot>
                {meta.label}
              </Badge>
            ) : null}
          </div>
          {subtitle ? <p className="mt-0.5 text-sm text-slate-500">{subtitle}</p> : null}
        </div>
      </div>

      <div className="flex flex-col items-start gap-2 sm:items-end">
        <p className="text-sm text-slate-500">{date}</p>
        {children ? <div className="flex items-center gap-2">{children}</div> : null}
      </div>
    </div>
  )
}
