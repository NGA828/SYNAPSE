import apiClient from './apiClient.js'

/**
 * Fetch the authenticated student's dashboard payload.
 */
export async function getDashboard() {
  const { data } = await apiClient.get('/student/dashboard')
  return data
}

export async function getGrades() {
  const { data } = await apiClient.get('/student/grades')
  return data
}

export async function getReportCard() {
  const { data } = await apiClient.get('/student/report-card')
  return data
}

export async function getTimetable() {
  const { data } = await apiClient.get('/student/timetable')
  return data
}
