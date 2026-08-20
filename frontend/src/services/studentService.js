import apiClient from './apiClient.js'

/**
 * Fetch the authenticated student's dashboard payload.
 */
export async function getDashboard() {
  const { data } = await apiClient.get('/student/dashboard')
  return data
}

export async function getGrades(semesterId) {
  const { data } = await apiClient.get('/student/grades', { params: { semester_id: semesterId } })
  return data
}

export async function getReportCard(semesterId) {
  const { data } = await apiClient.get('/student/report-card', { params: { semester_id: semesterId } })
  return data
}

export async function getTimetable() {
  const { data } = await apiClient.get('/student/timetable')
  return data
}
