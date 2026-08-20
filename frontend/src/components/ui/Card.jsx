import { cn } from '../../utils/cn.js'

export function Card({ variant = 'default', interactive = false, className, children, ...props }) {
  return (
    <div
      className={cn(
        'rounded-2xl border border-slate-200 bg-white',
        variant === 'elevated' ? 'shadow-lg shadow-slate-900/5' : 'shadow-sm',
        interactive && 'transition hover:-translate-y-0.5 hover:border-slate-300 hover:shadow-md',
        className,
      )}
      {...props}
    >
      {children}
    </div>
  )
}

export function CardHeader({ title, description, action, className, children, noBorder = false }) {
  return (
    <div
      className={cn(
        'flex flex-col gap-1 px-5 py-4 sm:flex-row sm:items-center sm:justify-between sm:gap-3',
        !noBorder && 'border-b border-slate-100',
        className,
      )}
    >
      <div className="min-w-0">
        {title ? <h2 className="text-base font-semibold text-slate-900">{title}</h2> : null}
        {description ? <p className="mt-0.5 text-sm text-slate-500">{description}</p> : null}
        {children}
      </div>
      {action ? <div className="flex shrink-0 items-center gap-2">{action}</div> : null}
    </div>
  )
}

export function CardBody({ className, children }) {
  return <div className={cn('px-5 py-5', className)}>{children}</div>
}

export function CardFooter({ className, children }) {
  return <div className={cn('flex items-center gap-2 border-t border-slate-100 px-5 py-3.5', className)}>{children}</div>
}
