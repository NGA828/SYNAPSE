import { useCallback, useEffect, useRef, useState } from 'react'

/**
 * Fetch data via a service function and expose { data, loading, error, reload }.
 *
 * `deps` triggers a refetch when route params (or other inputs) change; the
 * fetcher itself is read through a ref so inline closures never retrigger
 * loading on every render.
 */
export function useAsyncList(fetcher, deps = []) {
  const [data, setData] = useState(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState(null)

  const fetcherRef = useRef(fetcher)

  useEffect(() => {
    fetcherRef.current = fetcher
  })

  const reload = useCallback(async () => {
    setLoading(true)
    setError(null)
    try {
      setData(await fetcherRef.current())
    } catch (err) {
      setError(err)
    } finally {
      setLoading(false)
    }
  }, [])

  useEffect(() => {
    let active = true

    fetcherRef.current()
      .then((result) => {
        if (active) {
          setData(result)
          setError(null)
        }
      })
      .catch((err) => {
        if (active) setError(err)
      })
      .finally(() => {
        if (active) setLoading(false)
      })

    return () => {
      active = false
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, deps)

  return { data, loading, error, reload }
}

// `useAsync` is the same primitive, named for single-object fetches.
export { useAsyncList as useAsync }
