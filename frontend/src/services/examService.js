import apiClient from './apiClient.js'

/**
 * Exams & exam timetable.
 */

// Admin
export async function listAdminExams(classId) {
  const { data } = await apiClient.get('/admin/exams', { params: { class_id: classId } })
  return data.data
}

export async function createExam(payload) {
  const { data } = await apiClient.post('/admin/exams', payload)
  return data.data
}

export async function deleteExam(id) {
  const { data } = await apiClient.delete(`/admin/exams/${id}`)
  return data
}

export async function getAdminRanking(classId, subjectId, semesterId) {
  const { data } = await apiClient.get('/admin/exams/ranking', {
    params: { class_id: classId, subject_id: subjectId, semester_id: semesterId },
  })
  return data
}

// Teacher
export async function listTeacherExams() {
  const { data } = await apiClient.get('/teacher/exams')
  return data.data
}

export async function getTeacherRanking(classId, subjectId, semesterId) {
  const { data } = await apiClient.get('/teacher/exams/ranking', {
    params: { class_id: classId, subject_id: subjectId, semester_id: semesterId },
  })
  return data
}

// Student
export async function listStudentExams() {
  const { data } = await apiClient.get('/student/exams')
  return data
}
