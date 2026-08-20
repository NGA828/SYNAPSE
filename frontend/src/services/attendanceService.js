import apiClient from './apiClient.js'

/**
 * Attendance — teacher (their classes), admin (any class), student (own).
 */

export async function getTeacherRoster(classId, date) {
  const { data } = await apiClient.get(`/teacher/classes/${classId}/attendance`, { params: { date } })
  return data
}

export async function saveTeacherAttendance(classId, payload) {
  const { data } = await apiClient.post(`/teacher/classes/${classId}/attendance`, payload)
  return data
}

export async function getAdminRoster(classId, date) {
  const { data } = await apiClient.get('/admin/attendance', { params: { class_id: classId, date } })
  return data
}

export async function saveAdminAttendance(payload) {
  const { data } = await apiClient.post('/admin/attendance', payload)
  return data
}

export async function getStudentAttendance() {
  const { data } = await apiClient.get('/student/attendance')
  return data
}
