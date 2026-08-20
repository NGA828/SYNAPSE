import { forwardRef, useState } from 'react'
import { AlertCircle, Eye, EyeOff } from 'lucide-react'
import { cn } from '../../utils/cn.js'

export const Input = forwardRef(function Input(
  { label, error, hint, id, icon: Icon, className, wrapperClassName, type = 'text', ...props },
  ref,
) {
  const inputId = id ?? props.name
  const [reveal, setReveal] = useState(false)
  const isPassword = type === 'password'
  const inputType = isPassword && reveal ? 'text' : type

  return (
    <div className={cn('w-full', wrapperClassName)}>
      {label ? (
        <label htmlFor={inputId} className="mb-1.5 block text-sm font-medium text-slate-700">
          {label}
        </label>
      ) : null}

      <div className="relative">
        {Icon ? (
          <span className="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-slate-400">
            <Icon className="size-4" aria-hidden="true" />
          </span>
        ) : null}

        <input
          ref={ref}
          id={inputId}
          type={inputType}
          className={cn(
            'h-11 w-full rounded-xl border bg-white text-sm text-slate-900 shadow-sm transition placeholder:text-slate-400 focus:outline-none focus:ring-2 disabled:cursor-not-allowed disabled:bg-slate-50 disabled:text-slate-400',
            Icon ? 'pl-9' : 'pl-3.5',
            isPassword ? 'pr-10' : 'pr-3.5',
            error
              ? 'border-rose-300 focus:border-rose-400 focus:ring-rose-100'
              : 'border-slate-300 hover:border-slate-400 focus:border-brand-500 focus:ring-brand-100',
            className,
          )}
          aria-invalid={Boolean(error)}
          {...props}
        />

        {isPassword ? (
          <button
            type="button"
            onClick={() => setReveal((value) => !value)}
            className="absolute right-2.5 top-1/2 -translate-y-1/2 rounded-md p-1 text-slate-400 transition hover:text-slate-600"
            aria-label={reveal ? 'Hide password' : 'Show password'}
          >
            {reveal ? <EyeOff className="size-4" /> : <Eye className="size-4" />}
          </button>
        ) : null}
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
