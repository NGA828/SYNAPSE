import { useMemo } from 'react'
import apiClient from '../services/apiClient.js'

/**
 * Expose the shared, configured axios client to hooks that need direct
 * access. Services are the primary entry point; this hook exists for
 * one-off requests that do not warrant their own service module.
 */
export function useApi() {
  return useMemo(() => apiClient, [])
}
