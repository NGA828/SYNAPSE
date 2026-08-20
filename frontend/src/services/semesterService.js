import apiClient from './apiClient.js'

/**
 * Semesters (grading periods) — school admin.
 */

export async function listSemesters() {
  const { data } = await apiClient.get('/admin/semesters')
  return data
}

export async function createSemester(payload) {
  const { data } = await apiClient.post('/admin/semesters', payload)
  return data.data
}

export async function activateSemester(id) {
  const { data } = await apiClient.post(`/admin/semesters/${id}/activate`)
  return data.data
}

export async function deleteSemester(id) {
  const { data } = await apiClient.delete(`/admin/semesters/${id}`)
  return data
}
