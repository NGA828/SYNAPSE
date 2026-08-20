import apiClient from './apiClient.js'

/**
 * School management (platform super admin).
 */

export async function listSchools() {
  const { data } = await apiClient.get('/super-admin/schools')
  return data.data
}

export async function getSchool(id) {
  const { data } = await apiClient.get(`/super-admin/schools/${id}`)
  return data
}

export async function createSchool(payload) {
  const { data } = await apiClient.post('/super-admin/schools', payload)
  return data.data
}

export async function updateSchool(id, payload) {
  const { data } = await apiClient.put(`/super-admin/schools/${id}`, payload)
  return data.data
}

export async function setSchoolStatus(id, status) {
  const { data } = await apiClient.post(`/super-admin/schools/${id}/status`, { status })
  return data.data
}

export async function getSchoolUsers(id) {
  const { data } = await apiClient.get(`/super-admin/schools/${id}/users`)
  return data.data
}
