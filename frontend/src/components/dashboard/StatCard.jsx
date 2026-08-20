import { ArrowDownRight, ArrowUpRight } from 'lucide-react'
import { cn } from '../../utils/cn.js'
import { Card } from '../ui/Card.jsx'

const tones = {
  brand: 'bg-brand-50 text-brand-600 ring-brand-600/10',
  violet: 'bg-violet-50 text-violet-600 ring-violet-600/10',
  teal: 'bg-teal-50 text-teal-600 ring-teal-600/10',
  emerald: 'bg-emerald-50 text-emerald-600 ring-emerald-600/10',
  amber: 'bg-amber-50 text-amber-600 ring-amber-600/10',
  rose: 'bg-rose-50 text-rose-600 ring-rose-600/10',
  sky: 'bg-sky-50 text-sky-600 ring-sky-600/10',
}

export function StatCard({ icon: Icon, label, value, hint, trend, trendUp = true, tone = 'brand' }) {
  return (
    <Card className="p-5">
      <div className="flex items-start justify-between">
        <span className={cn('flex size-11 items-center justify-center rounded-xl ring-1 ring-inset', tones[tone])}>
          <Icon className="size-5" aria-hidden="true" />
        </span>
        {trend ? (
          <span
            className={cn(
              'inline-flex items-center gap-0.5 rounded-full px-2 py-0.5 text-xs font-semibold',
              trendUp ? 'bg-emerald-50 text-emerald-600' : 'bg-rose-50 text-rose-600',
            )}
          >
            {trendUp ? <ArrowUpRight className="size-3.5" /> : <ArrowDownRight className="size-3.5" />}
            {trend}
          </span>
        ) : null}
      </div>
      <p className="mt-4 text-sm text-slate-500">{label}</p>
      <p className="text-2xl font-bold tracking-tight text-slate-900">{value}</p>
      {hint ? <p className="mt-1 text-xs text-slate-400">{hint}</p> : null}
    </Card>
  )
}
