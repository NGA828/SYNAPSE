import apiClient from './apiClient.js'

/**
 * School settings (school admin).
 */

export async function getSettings() {
  const { data } = await apiClient.get('/admin/settings')
  return data.data
}

/**
 * Save school settings + white-label branding (logo, name, contacts).
 */
export async function updateSettings(payload) {
  const { data } = await apiClient.patch('/admin/settings', payload)
  return data
}
