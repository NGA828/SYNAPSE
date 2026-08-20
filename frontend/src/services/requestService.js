import apiClient from './apiClient.js'

/**
 * Student requests (submission) + administrator request management.
 */

export async function getStudentRequests() {
  const { data } = await apiClient.get('/student/requests')
  return data.data
}

export async function createRequest(payload) {
  const { data } = await apiClient.post('/student/requests', payload)
  return data.data
}

export async function getAdminRequests() {
  const { data } = await apiClient.get('/admin/requests')
  return data.data
}

export async function updateRequestStatus(id, payload) {
  const { data } = await apiClient.post(`/admin/requests/${id}/status`, payload)
  return data.data
}

export async function generateRequestDocument(id) {
  const { data } = await apiClient.post(`/admin/requests/${id}/generate-document`)
  return data.data
}
