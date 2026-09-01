import apiClient from './apiClient.js'

/**
 * Student requests (submission) + administrator request management.
 */

/**
 * The documents a student may ask for. Served by the API so the form can only
 * offer what the server will accept — the free-text field this replaces is what
 * let a request be filed for a document no template could produce.
 */
export async function getRequestTypes() {
  const { data } = await apiClient.get('/student/requests/types')
  return data.data
}

/** Queue counters: how much of the backlog is instant, how much needs a person. */
export async function getRequestTriage() {
  const { data } = await apiClient.get('/admin/requests/triage')
  return data.data
}

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
