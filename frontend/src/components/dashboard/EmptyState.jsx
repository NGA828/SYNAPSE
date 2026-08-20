import { Sparkles } from 'lucide-react'

export function EmptyState({
  icon: Icon = Sparkles,
  title = 'Nothing here yet',
  description = 'This section is wired up in a later phase.',
  action,
}) {
  return (
    <div className="flex flex-col items-center justify-center rounded-xl border border-dashed border-slate-200 bg-slate-50/60 px-6 py-12 text-center">
      <span className="flex size-12 items-center justify-center rounded-full bg-brand-50 text-brand-500">
        <Icon className="size-6" aria-hidden="true" />
      </span>
      <p className="mt-4 text-sm font-semibold text-slate-800">{title}</p>
      <p className="mt-1 max-w-sm text-sm text-slate-500">{description}</p>
      {action ? <div className="mt-4">{action}</div> : null}
    </div>
  )
}
