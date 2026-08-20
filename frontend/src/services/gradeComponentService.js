import apiClient from './apiClient.js'

/**
 * Weighted grade components — school admin.
 */

export async function listGradeComponents() {
  const { data } = await apiClient.get('/admin/grade-components')
  return data
}

export async function createGradeComponent(payload) {
  const { data } = await apiClient.post('/admin/grade-components', payload)
  return data.data
}

export async function updateGradeComponent(id, payload) {
  const { data } = await apiClient.put(`/admin/grade-components/${id}`, payload)
  return data.data
}

export async function deleteGradeComponent(id) {
  const { data } = await apiClient.delete(`/admin/grade-components/${id}`)
  return data
}
