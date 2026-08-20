import apiClient from './apiClient.js'

/**
 * School onboarding (public registration).
 */

export async function registerSchool(payload) {
  const { data } = await apiClient.post('/onboarding/schools', payload)
  return data
}

export async function listPublicPlans() {
  const { data } = await apiClient.get('/onboarding/plans')
  return data.data
}
