import apiClient from './apiClient.js'

/**
 * Announcements — the listing endpoint is role-aware; publishing is admin-only.
 */

export async function getAnnouncements() {
  const { data } = await apiClient.get('/announcements')
  return data.data
}

export async function createAnnouncement(payload) {
  const { data } = await apiClient.post('/admin/announcements', payload)
  return data.data
}
