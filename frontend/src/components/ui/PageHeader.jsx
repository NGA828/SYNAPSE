import { Link } from 'react-router-dom'
import { ArrowLeft } from 'lucide-react'

export function PageHeader({ title, description, back, children }) {
  return (
    <div className="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
      <div className="min-w-0">
        {back ? (
          <Link
            to={back}
            className="mb-2 inline-flex items-center gap-1 text-sm font-medium text-brand-600 hover:underline"
          >
            <ArrowLeft className="size-4" aria-hidden="true" />
            Back
          </Link>
        ) : null}
        <h1 className="text-2xl font-bold tracking-tight text-slate-900">{title}</h1>
        {description ? <p className="mt-1 text-sm text-slate-500">{description}</p> : null}
      </div>
      {children ? <div className="flex flex-wrap items-center gap-2">{children}</div> : null}
    </div>
  )
}
