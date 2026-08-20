import { forwardRef } from 'react'
import { AlertCircle } from 'lucide-react'
import { cn } from '../../utils/cn.js'

export const Textarea = forwardRef(function Textarea(
  { label, error, hint, id, className, wrapperClassName, rows = 3, ...props },
  ref,
) {
  const inputId = id ?? props.name

  return (
    <div className={cn('w-full', wrapperClassName)}>
      {label ? (
        <label htmlFor={inputId} className="mb-1.5 block text-sm font-medium text-slate-700">
          {label}
        </label>
      ) : null}

      <textarea
        ref={ref}
        id={inputId}
        rows={rows}
        className={cn(
          'w-full rounded-xl border bg-white px-3.5 py-2.5 text-sm text-slate-900 shadow-sm transition placeholder:text-slate-400 focus:outline-none focus:ring-2 disabled:cursor-not-allowed disabled:bg-slate-50',
          error
            ? 'border-rose-300 focus:border-rose-400 focus:ring-rose-100'
            : 'border-slate-300 hover:border-slate-400 focus:border-brand-500 focus:ring-brand-100',
          className,
        )}
        aria-invalid={Boolean(error)}
        {...props}
      />

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
