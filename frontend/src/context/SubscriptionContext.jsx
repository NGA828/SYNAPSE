/* eslint-disable react-refresh/only-export-components */
import { createContext, useMemo } from 'react'
import { useTenant } from '../hooks/useTenant.js'

export const SubscriptionContext = createContext(null)

/**
 * Derived subscription state for the current tenant (plan, status, usage,
 * limits, feature flags, remaining trial days).
 */
export function SubscriptionProvider({ children }) {
  const { plan, subscription, status, usage, features } = useTenant()

  const remainingDays = useMemo(() => {
    if (!subscription?.end_date) return null
    const end = new Date(subscription.end_date)
    const days = Math.ceil((end.getTime() - Date.now()) / 86_400_000)
    return Math.max(0, days)
  }, [subscription])

  const value = useMemo(
    () => ({
      plan,
      subscription,
      status,
      usage,
      features,
      isActive: status === 'active' || status === 'trial' || status === 'platform',
      isTrial: status === 'trial',
      remainingDays,
      hasFeature: (feature) => features.includes(feature),
      currency: plan?.currency ?? 'XAF',
    }),
    [plan, subscription, status, usage, features, remainingDays],
  )

  return <SubscriptionContext.Provider value={value}>{children}</SubscriptionContext.Provider>
}
