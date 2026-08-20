import { Check, X } from 'lucide-react'
import { REQUEST_STATUS_META, REQUEST_STEPS } from '../../utils/requests.js'
import { cn } from '../../utils/cn.js'

export function RequestStatusStepper({ status }) {
  if (status === 'rejected') {
    return (
      <div className="flex items-center gap-2 rounded-lg bg-rose-50 px-3 py-2 text-xs font-medium text-rose-600">
        <X className="size-3.5" aria-hidden="true" />
        Rejected
      </div>
    )
  }

  const current = REQUEST_STATUS_META[status]?.step ?? 0

  return (
    <ol className="flex flex-wrap items-center gap-1">
      {REQUEST_STEPS.map((step, index) => {
        const done = index <= current
        return (
          <li key={step} className="flex items-center gap-1">
            <span
              className={cn(
                'flex items-center gap-1 rounded-full px-2.5 py-1 text-[11px] font-medium',
                done ? 'bg-brand-50 text-brand-700' : 'bg-slate-100 text-slate-400',
              )}
            >
              {index < current ? <Check className="size-3" aria-hidden="true" /> : null}
              {step}
            </span>
            {index < REQUEST_STEPS.length - 1 ? (
              <span className="hidden h-px w-3 bg-slate-200 sm:block" aria-hidden="true" />
            ) : null}
          </li>
        )
      })}
    </ol>
  )
}
