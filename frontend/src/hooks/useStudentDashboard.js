import { useEffect, useState } from 'react'
import { getDashboard } from '../services/studentService.js'

/**
 * Load the authenticated student's dashboard data.
 */
export function useStudentDashboard() {
  const [data, setData] = useState(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState(null)

  useEffect(() => {
    let active = true

    getDashboard()
      .then((payload) => {
        if (active) setData(payload)
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
  }, [])

  return { data, loading, error }
}
