import { Link } from 'react-router-dom'
import { AlertTriangle, Info, Sparkles } from 'lucide-react'
import { useAuth } from '../../hooks/useAuth.js'
import { useTenant } from '../../hooks/useTenant.js'
import { useSubscription } from '../../hooks/useSubscription.js'

function limitReached(usage) {
  if (!usage) return null
  for (const key of ['students', 'teachers', 'classes']) {
    const limit = usage.limits?.[key]
    if (limit !== null && limit !== undefined && usage[key] >= limit) return key
  }
  return null
}

export function SubscriptionBanner() {
  const { role } = useAuth()
  const { school } = useTenant()
  const { isActive, isTrial, remainingDays } = useSubscription()
  const { usage } = useTenant()

  if (!school || role === 'super_admin') return null

  const exceeded = limitReached(usage)
  const isAdmin = role === 'admin'

  if (!isActive) {
    return (
      <div className="flex flex-col gap-2 rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700 sm:flex-row sm:items-center sm:justify-between">
        <span className="flex items-center gap-2">
          <AlertTriangle className="size-4 shrink-0" aria-hidden="true" />
          Your subscription has expired. Please renew your plan to continue using SYNAPSE.
        </span>
        {isAdmin ? (
          <Link to="/admin/billing" className="shrink-0 font-semibold underline">
            Renew now
          </Link>
        ) : null}
      </div>
    )
  }

  if (isTrial) {
    return (
      <div className="flex flex-col gap-2 rounded-2xl border border-brand-200 bg-brand-50 px-4 py-3 text-sm text-brand-700 sm:flex-row sm:items-center sm:justify-between">
        <span className="flex items-center gap-2">
          <Sparkles className="size-4 shrink-0" aria-hidden="true" />
          You&apos;re on a free trial — {remainingDays ?? '?'} day{remainingDays === 1 ? '' : 's'} remaining.
        </span>
        {isAdmin ? (
          <Link to="/admin/billing" className="shrink-0 font-semibold underline">
            Choose a plan
          </Link>
        ) : null}
      </div>
    )
  }

  if (exceeded) {
    return (
      <div className="flex flex-col gap-2 rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-700 sm:flex-row sm:items-center sm:justify-between">
        <span className="flex items-center gap-2">
          <Info className="size-4 shrink-0" aria-hidden="true" />
          You have reached the {exceeded} limit of your current plan.
        </span>
        {isAdmin ? (
          <Link to="/admin/billing" className="shrink-0 font-semibold underline">
            Upgrade your plan
          </Link>
        ) : null}
      </div>
    )
  }

  return null
}
