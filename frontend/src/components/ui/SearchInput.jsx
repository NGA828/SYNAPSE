import { Search, X } from 'lucide-react'
import { clsx } from 'clsx'

/**
 * Debounced-by-caller search box for paginated tables.
 */
export function SearchInput({ value, onChange, placeholder = 'Search…', className }) {
  return (
    <div className={clsx('relative', className)}>
      <Search
        className="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-slate-400"
        aria-hidden="true"
      />
      <input
        type="search"
        value={value}
        onChange={(event) => onChange(event.target.value)}
        placeholder={placeholder}
        aria-label={placeholder}
        className="w-full rounded-xl border border-slate-200 bg-white py-2 pl-9 pr-9 text-sm text-slate-800 placeholder:text-slate-400 focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-100"
      />
      {value ? (
        <button
          type="button"
          onClick={() => onChange('')}
          aria-label="Clear search"
          className="absolute right-2 top-1/2 -translate-y-1/2 rounded-lg p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-600"
        >
          <X className="size-4" aria-hidden="true" />
        </button>
      ) : null}
    </div>
  )
}
