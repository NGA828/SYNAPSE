import apiClient from './apiClient.js'

/**
 * Administrator endpoints: dashboard summary + academic structure
 * (years, classes, subjects, teaching assignments).
 */

export async function getAdminDashboard() {
  const { data } = await apiClient.get('/admin/dashboard')
  return data
}

export async function listAcademicYears() {
  const { data } = await apiClient.get('/admin/academic-years')
  return data.data
}

export async function createAcademicYear(payload) {
  const { data } = await apiClient.post('/admin/academic-years', payload)
  return data.data
}

export async function activateAcademicYear(id) {
  const { data } = await apiClient.post(`/admin/academic-years/${id}/activate`)
  return data.data
}

export async function listClasses() {
  const { data } = await apiClient.get('/admin/classes')
  return data.data
}

export async function createClass(payload) {
  const { data } = await apiClient.post('/admin/classes', payload)
  return data.data
}

export async function listSubjects() {
  const { data } = await apiClient.get('/admin/subjects')
  return data.data
}

export async function createSubject(payload) {
  const { data } = await apiClient.post('/admin/subjects', payload)
  return data.data
}

export async function updateSubject(id, payload) {
  const { data } = await apiClient.put(`/admin/subjects/${id}`, payload)
  return data.data
}

export async function deleteSubject(id) {
  const { data } = await apiClient.delete(`/admin/subjects/${id}`)
  return data
}

export async function listTeachingAssignments() {
  const { data } = await apiClient.get('/admin/teaching-assignments')
  return data.data
}

export async function createTeachingAssignment(payload) {
  const { data } = await apiClient.post('/admin/teaching-assignments', payload)
  return data.data
}

export async function deleteTeachingAssignment(id) {
  const { data } = await apiClient.delete(`/admin/teaching-assignments/${id}`)
  return data
}

export async function listTimetable(classId) {
  const { data } = await apiClient.get('/admin/timetable', { params: { class_id: classId } })
  return data
}

export async function createTimetableEntry(payload) {
  const { data } = await apiClient.post('/admin/timetable/entries', payload)
  return data.data
}

export async function updateTimetableEntry(id, payload) {
  const { data } = await apiClient.put(`/admin/timetable/entries/${id}`, payload)
  return data.data
}

export async function deleteTimetableEntry(id) {
  const { data } = await apiClient.delete(`/admin/timetable/entries/${id}`)
  return data
}
