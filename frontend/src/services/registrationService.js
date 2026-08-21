import apiClient from './apiClient.js'

/**
 * Administrator endpoints for registering students and teachers.
 */

export async function listTeachers() {
  const { data } = await apiClient.get('/admin/teachers')
  return data.data
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

export async function listStudents() {
  const { data } = await apiClient.get('/admin/students')
  return data.data
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
