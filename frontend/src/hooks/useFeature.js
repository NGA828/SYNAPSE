import { useSubscription } from './useSubscription.js'

/**
 * Whether a plan feature is enabled for the current tenant.
 */
export function useFeature(feature) {
  const { features } = useSubscription()

  return features.includes(feature)
}
