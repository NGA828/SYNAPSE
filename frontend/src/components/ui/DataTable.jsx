import { cn } from '../../utils/cn.js'
import { EmptyState } from '../dashboard/EmptyState.jsx'
import { Spinner } from './Spinner.jsx'

/**
 * Consistent table primitive used across the app: uppercase header row,
 * hover states, tabular numerals for right-aligned cells, loading and empty
 * states.
 *
 * columns: [{ key, header, align, headerClassName, cellClassName, render(row) }]
 */
export function DataTable({
  columns = [],
  rows = [],
  keyField = 'id',
  loading = false,
  emptyTitle = 'No records',
  emptyDescription,
  footer,
  className,
}) {
  if (loading) {
    return (
      <div className="flex justify-center py-12">
        <Spinner />
      </div>
    )
  }

  if (!rows || rows.length === 0) {
    return <EmptyState title={emptyTitle} description={emptyDescription} />
  }

  return (
    <div className={className}>
      <div className="scrollbar-thin overflow-x-auto">
        <table className="w-full text-sm">
          <thead>
            <tr className="border-b border-slate-100 text-left text-xs uppercase tracking-wide text-slate-400">
              {columns.map((column) => (
                <th
                  key={column.key}
                  className={cn(
                    'px-4 py-3 font-semibold',
                    column.align === 'right' && 'text-right',
                    column.headerClassName,
                  )}
                >
                  {column.header}
                </th>
              ))}
            </tr>
          </thead>
          <tbody>
            {rows.map((row) => (
              <tr
                key={row[keyField]}
                className="border-b border-slate-50 transition last:border-0 hover:bg-slate-50/70"
              >
                {columns.map((column) => (
                  <td
                    key={column.key}
                    className={cn(
                      'px-4 py-3 align-middle',
                      column.align === 'right' && 'text-right tabular-nums',
                      column.cellClassName,
                    )}
                  >
                    {column.render ? column.render(row) : row[column.key]}
                  </td>
                ))}
              </tr>
            ))}
          </tbody>
        </table>
      </div>
      {footer ? <div className="mt-4">{footer}</div> : null}
    </div>
  )
}
