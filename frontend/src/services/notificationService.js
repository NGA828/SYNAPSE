import apiClient from './apiClient.js'

/**
 * Notifications (shared by every role).
 */

export async function getNotifications() {
  const { data } = await apiClient.get('/notifications')
  return data
}

export async function markRead(id) {
  const { data } = await apiClient.post(`/notifications/${id}/read`)
  return data
}

export async function markAllRead() {
  const { data } = await apiClient.post('/notifications/read-all')
  return data
}
