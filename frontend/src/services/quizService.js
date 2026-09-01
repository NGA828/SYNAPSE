import apiClient from './apiClient.js'
import { multipartConfig, toNestedBody } from './uploadHelpers.js'

/**
 * Auto-marked quizzes.
 *
 * Teacher routes are scoped by the backend to the authenticated teacher's own
 * quizzes. Student routes never return the answer key except on `review`, and
 * only for an attempt that student has already submitted.
 */

// --- Teacher ---------------------------------------------------------------

export async function listTeacherQuizzes(params) {
  const { data } = await apiClient.get('/teacher/quizzes', { params })
  return data
}

/** The full paper, answer key included — for the authoring screen only. */
export async function getTeacherQuiz(id) {
  const { data } = await apiClient.get(`/teacher/quizzes/${id}`)
  return data.data
}

export async function createQuiz(payload, files = []) {
  const { data } = await apiClient.post('/teacher/quizzes', toNestedBody(payload, files), multipartConfig)
  return data.data
}

export async function updateQuiz(id, payload, files = []) {
  const body = toNestedBody(payload, files)
  // Laravel needs this to treat a multipart POST as a PUT.
  body.append('_method', 'PUT')

  const { data } = await apiClient.post(`/teacher/quizzes/${id}`, body, multipartConfig)
  return data.data
}

export async function deleteQuiz(id) {
  const { data } = await apiClient.delete(`/teacher/quizzes/${id}`)
  return data
}

export async function publishQuiz(id) {
  const { data } = await apiClient.post(`/teacher/quizzes/${id}/publish`)
  return data.data
}

export async function unpublishQuiz(id) {
  const { data } = await apiClient.post(`/teacher/quizzes/${id}/unpublish`)
  return data.data
}

/** Class results with the per-question breakdown. */
export async function getQuizResults(id) {
  const { data } = await apiClient.get(`/teacher/quizzes/${id}/results`)
  return data
}

export async function getQuizAttempt(id) {
  const { data } = await apiClient.get(`/teacher/quiz-attempts/${id}`)
  return data.data
}

export async function reviewQuizAttempt(id, feedback) {
  const { data } = await apiClient.post(`/teacher/quiz-attempts/${id}/review`, { feedback })
  return data.data
}

// --- Student ---------------------------------------------------------------

export async function listStudentQuizzes() {
  const { data } = await apiClient.get('/student/quizzes')
  return data
}

/** The paper to sit. Contains no answer key. */
export async function getQuizPaper(id) {
  const { data } = await apiClient.get(`/student/quizzes/${id}/paper`)
  return data
}

/**
 * Submit an answer sheet. `answers` maps question id to the chosen option
 * index; the mark comes back immediately.
 */
export async function submitQuiz(id, answers) {
  const { data } = await apiClient.post(`/student/quizzes/${id}/submit`, { answers })
  return data.data
}

/** Per-question review of a submitted attempt, with the answer key. */
export async function getAttemptReview(attemptId) {
  const { data } = await apiClient.get(`/student/quiz-attempts/${attemptId}/review`)
  return data
}

// A quiz's own paper is attached with `class` visibility, so it downloads
// through the same authorised attachment route as everything else — reuse
// homeworkService.downloadAttachment(attachment) rather than duplicating it.
