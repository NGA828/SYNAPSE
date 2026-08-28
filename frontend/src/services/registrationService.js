import apiClient from './apiClient.js'

/**
 * Administrator endpoints for registering students and teachers.
 */

/**
 * Paginated teacher directory. Returns the full `{ data, meta, links }`
 * envelope so callers can render page controls.
 */
export async function listTeachers(params = {}) {
  const { data } = await apiClient.get('/admin/teachers', { params })
  return data
}

export async function createTeacher(payload) {
  const { data } = await apiClient.post('/admin/teachers', payload)
  return data.data
}

export async function updateTeacher(id, payload) {
  const { data } = await apiClient.put(`/admin/teachers/${id}`, payload)
  return data.data
}

export async function deleteTeacher(id) {
  const { data } = await apiClient.delete(`/admin/teachers/${id}`)
  return data
}

/**
 * Paginated, searchable student directory (`{ data, meta, links }`).
 */
export async function listStudents(params = {}) {
  const { data } = await apiClient.get('/admin/students', { params })
  return data
}

export async function createStudent(payload) {
  const { data } = await apiClient.post('/admin/students', payload)
  return data.data
}

export async function updateStudent(id, payload) {
  const { data } = await apiClient.put(`/admin/students/${id}`, payload)
  return data.data
}

export async function deleteStudent(id) {
  const { data } = await apiClient.delete(`/admin/students/${id}`)
  return data
}
