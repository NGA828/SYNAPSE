import apiClient from './apiClient.js'

/**
 * Tenant (school) context for the authenticated user.
 */

export async function getTenant() {
  const { data } = await apiClient.get('/tenant')
  return data
}

export async function getPublicSchool(slug) {
  const { data } = await apiClient.get(`/school/${slug}`)
  return data.school
}
