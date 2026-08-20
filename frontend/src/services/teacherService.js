import apiClient from './apiClient.js'

/**
 * Teacher endpoints — every response is scoped to the authenticated teacher's
 * TeachingAssignment records by the backend.
 */

export async function getTeacherDashboard() {
  const { data } = await apiClient.get('/teacher/dashboard')
  return data
}

export async function listAssignments() {
  const { data } = await apiClient.get('/teacher/assignments')
  return data.data
}

export async function getClassStudents(classId, subjectId) {
  const { data } = await apiClient.get(`/teacher/classes/${classId}/subjects/${subjectId}/students`)
  return data
}

export async function getGradebook(classId, subjectId, semesterId) {
  const { data } = await apiClient.get(`/teacher/classes/${classId}/subjects/${subjectId}/gradebook`, {
    params: { semester_id: semesterId },
  })
  return data
}

export async function saveGrades(classId, subjectId, grades, semesterId) {
  const { data } = await apiClient.post(
    `/teacher/classes/${classId}/subjects/${subjectId}/grades`,
    { grades },
    { params: { semester_id: semesterId } },
  )
  return data
}
