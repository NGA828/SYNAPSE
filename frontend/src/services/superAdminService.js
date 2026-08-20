import apiClient from './apiClient.js'

/**
 * Platform super admin dashboard.
 */

export async function getSuperAdminDashboard() {
  const { data } = await apiClient.get('/super-admin/dashboard')
  return data
}
