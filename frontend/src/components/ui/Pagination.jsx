import { ChevronLeft, ChevronRight } from 'lucide-react'
import { clsx } from 'clsx'
import { Button } from './Button.jsx'

/**
 * Page controls for a server-paginated list.
 *
 * Renders nothing when everything fits on a single page, so it can be dropped
 * under any table without cluttering small datasets.
 */
export function Pagination({ meta, page, onPageChange, className }) {
  if (!meta || (meta.last_page ?? 1) <= 1) return null

  const current = meta.current_page ?? page ?? 1
  const last = meta.last_page ?? 1
  const from = meta.from ?? (current - 1) * (meta.per_page ?? 0) + 1
  const to = meta.to ?? Math.min(current * (meta.per_page ?? 0), meta.total ?? 0)

  const pages = pageWindow(current, last)

  return (
    <nav
      className={clsx('mt-4 flex flex-wrap items-center justify-between gap-3', className)}
      aria-label="Pagination"
    >
      <p className="text-xs text-slate-500">
        Showing <span className="font-medium text-slate-700">{from}</span>–
        <span className="font-medium text-slate-700">{to}</span> of{' '}
        <span className="font-medium text-slate-700">{meta.total}</span>
      </p>

      <div className="flex items-center gap-1">
        <Button
          size="sm"
          variant="secondary"
          disabled={current <= 1}
          onClick={() => onPageChange(current - 1)}
          aria-label="Previous page"
        >
          <ChevronLeft className="size-4" aria-hidden="true" />
        </Button>

        {pages.map((item, index) =>
          item === '…' ? (
            <span key={`gap-${index}`} className="px-2 text-sm text-slate-400">
              …
            </span>
          ) : (
            <Button
              key={item}
              size="sm"
              variant={item === current ? 'primary' : 'ghost'}
              onClick={() => onPageChange(item)}
              aria-current={item === current ? 'page' : undefined}
            >
              {item}
            </Button>
          ),
        )}

        <Button
          size="sm"
          variant="secondary"
          disabled={current >= last}
          onClick={() => onPageChange(current + 1)}
          aria-label="Next page"
        >
          <ChevronRight className="size-4" aria-hidden="true" />
        </Button>
      </div>
    </nav>
  )
}

/** 1 … 4 5 [6] 7 8 … 20 */
function pageWindow(current, last) {
  const pages = []
  const push = (value) => {
    if (pages[pages.length - 1] !== value) pages.push(value)
  }

  for (let page = 1; page <= last; page += 1) {
    const nearEdges = page <= 1 || page > last - 1
    const nearCurrent = Math.abs(page - current) <= 1

    if (nearEdges || nearCurrent) {
      push(page)
    } else {
      push('…')
    }
  }

  return pages
}
