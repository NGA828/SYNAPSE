import apiClient from './apiClient.js'

/**
 * School events.
 *
 * Read routes are shared and only ever return published events the caller's
 * audience includes. Write routes are admin-only.
 */

// --- Everyone --------------------------------------------------------------

export async function listEvents(params) {
  const { data } = await apiClient.get('/events', { params })
  return data.data ?? []
}

export async function getEvent(id) {
  const { data } = await apiClient.get(`/events/${id}`)
  return data.data
}

// --- Admin -----------------------------------------------------------------

export async function listAdminEvents(params) {
  const { data } = await apiClient.get('/admin/events', { params })
  return data
}

export async function createEvent(payload) {
  const { data } = await apiClient.post('/admin/events', payload)
  return data.data
}

export async function updateEvent(id, payload) {
  const { data } = await apiClient.put(`/admin/events/${id}`, payload)
  return data.data
}

export async function deleteEvent(id) {
  const { data } = await apiClient.delete(`/admin/events/${id}`)
  return data
}

export async function publishEvent(id) {
  const { data } = await apiClient.post(`/admin/events/${id}/publish`)
  return data.data
}

export async function unpublishEvent(id) {
  const { data } = await apiClient.post(`/admin/events/${id}/unpublish`)
  return data.data
}
