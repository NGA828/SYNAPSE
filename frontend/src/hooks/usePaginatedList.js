import { useCallback, useEffect, useRef, useState } from 'react'

/**
 * Server-side pagination + search for list endpoints.
 *
 * `fetcher(params)` must return the Laravel envelope `{ data, meta, links }`
 * (see App\Http\Concerns\HandlesPagination). Search input is debounced so
 * typing does not fire a request per keystroke.
 */
export function usePaginatedList(fetcher, { perPage = 15, sort, debounce = 350, refreshKey } = {}) {
  const [rows, setRows] = useState([])
  const [meta, setMeta] = useState(null)
  const [page, setPage] = useState(1)
  const [search, setSearch] = useState('')
  const [debouncedSearch, setDebouncedSearch] = useState('')
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState(null)

  const fetcherRef = useRef(fetcher)

  useEffect(() => {
    fetcherRef.current = fetcher
  })

  // Debounce the search term, and always jump back to page 1 on a new query.
  useEffect(() => {
    const timer = window.setTimeout(() => {
      setDebouncedSearch(search)
      setPage(1)
    }, debounce)

    return () => window.clearTimeout(timer)
  }, [search, debounce])

  const load = useCallback(async () => {
    setLoading(true)
    setError(null)

    try {
      const params = { page, per_page: perPage }

      if (debouncedSearch) params.search = debouncedSearch
      if (sort) params.sort = sort

      const payload = await fetcherRef.current(params)

      // Tolerate endpoints that still return a bare array.
      const list = Array.isArray(payload) ? payload : (payload?.data ?? [])

      setRows(list)
      setMeta(Array.isArray(payload) ? null : (payload?.meta ?? null))
    } catch (err) {
      setError(err)
      setRows([])
    } finally {
      setLoading(false)
    }
  // `refreshKey` is not read inside the callback: it is a trigger. Callers pass
  // it so a filter their fetcher closes over refetches when it changes. It is a
  // primitive on purpose — an array literal would be a new identity every
  // render and loop for ever.
  // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [page, perPage, debouncedSearch, sort, refreshKey])

  useEffect(() => {
    // Deferred to a microtask so the first setState happens outside the
    // effect body (avoids the cascading-render lint rule and double renders).
    let cancelled = false

    Promise.resolve().then(() => {
      if (!cancelled) load()
    })

    return () => {
      cancelled = true
    }
  }, [load])

  return {
    rows,
    meta,
    page,
    setPage,
    search,
    setSearch,
    loading,
    error,
    reload: load,
    total: meta?.total ?? rows.length,
  }
}
