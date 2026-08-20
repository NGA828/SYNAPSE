import { forwardRef } from 'react'
import { AlertCircle, ChevronDown } from 'lucide-react'
import { cn } from '../../utils/cn.js'

export const Select = forwardRef(function Select(
  { label, error, hint, id, className, wrapperClassName, children, ...props },
  ref,
) {
  const selectId = id ?? props.name

  return (
    <div className={cn('w-full', wrapperClassName)}>
      {label ? (
        <label htmlFor={selectId} className="mb-1.5 block text-sm font-medium text-slate-700">
          {label}
        </label>
      ) : null}

      <div className="relative">
        <select
          ref={ref}
          id={selectId}
          className={cn(
            'h-11 w-full appearance-none rounded-xl border bg-white pl-3.5 pr-10 text-sm text-slate-900 shadow-sm transition focus:outline-none focus:ring-2 disabled:cursor-not-allowed disabled:bg-slate-50 disabled:text-slate-400',
            error
              ? 'border-rose-300 focus:border-rose-400 focus:ring-rose-100'
              : 'border-slate-300 hover:border-slate-400 focus:border-brand-500 focus:ring-brand-100',
            className,
          )}
          {...props}
        >
          {children}
        </select>
        <ChevronDown
          className="pointer-events-none absolute right-3 top-1/2 size-4 -translate-y-1/2 text-slate-400"
          aria-hidden="true"
        />
      </div>

      {error ? (
        <p className="mt-1.5 flex items-center gap-1 text-xs font-medium text-rose-600">
          <AlertCircle className="size-3.5 shrink-0" aria-hidden="true" />
          {error}
        </p>
      ) : hint ? (
        <p className="mt-1.5 text-xs text-slate-500">{hint}</p>
      ) : null}
    </div>
  )
})
