/* eslint-disable react-refresh/only-export-components */
// The context object and provider live together so the provider stays the
// single wiring point for tenant state.
import { createContext, useCallback, useEffect, useMemo, useState } from 'react'
import { useAuth } from '../hooks/useAuth.js'
import { getTenant } from '../services/tenantService.js'

export const TenantContext = createContext(null)

export function TenantProvider({ children }) {
  const { isAuthenticated, role } = useAuth()
  const isSuperAdmin = role === 'super_admin'
  const [tenant, setTenant] = useState(null)
  const [loaded, setLoaded] = useState(false)

  const load = useCallback(async () => {
    setLoaded(false)
    try {
      setTenant(await getTenant())
    } catch {
      setTenant(null)
    } finally {
      setLoaded(true)
    }
  }, [])

  useEffect(() => {
    if (!isAuthenticated || isSuperAdmin) return

    let active = true

    getTenant()
      .then((data) => {
        if (active) setTenant(data)
      })
      .catch(() => {
        if (active) setTenant(null)
      })
      .finally(() => {
        if (active) setLoaded(true)
      })

    return () => {
      active = false
    }
  }, [isAuthenticated, isSuperAdmin])

  const value = useMemo(
    () => ({
      school: tenant?.school ?? null,
      plan: tenant?.plan ?? null,
      subscription: tenant?.subscription ?? null,
      status: tenant?.status ?? null,
      features: tenant?.features ?? [],
      usage: tenant?.usage ?? null,
      loading: isAuthenticated && !isSuperAdmin && !loaded,
      isSuperAdmin,
      refresh: load,
    }),
    [tenant, loaded, isAuthenticated, isSuperAdmin, load],
  )

  return <TenantContext.Provider value={value}>{children}</TenantContext.Provider>
}
