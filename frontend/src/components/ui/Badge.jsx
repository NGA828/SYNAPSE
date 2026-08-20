import { cn } from '../../utils/cn.js'

const variants = {
  neutral: 'bg-slate-100 text-slate-700 ring-slate-600/10',
  info: 'bg-brand-50 text-brand-700 ring-brand-600/20',
  success: 'bg-emerald-50 text-emerald-700 ring-emerald-600/20',
  warning: 'bg-amber-50 text-amber-700 ring-amber-600/20',
  danger: 'bg-rose-50 text-rose-700 ring-rose-600/20',
  violet: 'bg-violet-50 text-violet-700 ring-violet-600/20',
  teal: 'bg-teal-50 text-teal-700 ring-teal-600/20',
}

const dots = {
  neutral: 'bg-slate-500',
  info: 'bg-brand-500',
  success: 'bg-emerald-500',
  warning: 'bg-amber-500',
  danger: 'bg-rose-500',
  violet: 'bg-violet-500',
  teal: 'bg-teal-500',
}

export function Badge({ variant = 'neutral', dot = false, className, children, ...props }) {
  return (
    <span
      className={cn(
        'inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-medium ring-1 ring-inset',
        variants[variant],
        className,
      )}
      {...props}
    >
      {dot ? <span className={cn('size-1.5 rounded-full', dots[variant])} aria-hidden="true" /> : null}
      {children}
    </span>
  )
}
