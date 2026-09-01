import apiClient from './apiClient.js'
import { multipartConfig, toFormData } from './uploadHelpers.js'

/**
 * Course materials (lessons).
 *
 * Teacher routes are scoped by the backend to the authenticated teacher's own
 * lessons; student routes to published lessons for their enrolled class.
 */

// --- Teacher ---------------------------------------------------------------

export async function listTeacherLessons(params) {
  const { data } = await apiClient.get('/teacher/materials', { params })
  return data
}

export async function getTeacherLesson(id) {
  const { data } = await apiClient.get(`/teacher/materials/${id}`)
  return data.data
}

export async function createLesson(payload, files = []) {
  const { data } = await apiClient.post('/teacher/materials', toFormData(payload, files), multipartConfig)
  return data.data
}

export async function updateLesson(id, payload, files = []) {
  const body = toFormData(payload, files)
  // Laravel needs this to treat a multipart POST as a PUT.
  body.append('_method', 'PUT')

  const { data } = await apiClient.post(`/teacher/materials/${id}`, body, multipartConfig)
  return data.data
}

export async function deleteLesson(id) {
  const { data } = await apiClient.delete(`/teacher/materials/${id}`)
  return data
}

export async function publishLesson(id) {
  const { data } = await apiClient.post(`/teacher/materials/${id}/publish`)
  return data.data
}

export async function unpublishLesson(id) {
  const { data } = await apiClient.post(`/teacher/materials/${id}/unpublish`)
  return data.data
}

// --- Student ---------------------------------------------------------------

export async function listStudentMaterials() {
  const { data } = await apiClient.get('/student/materials')
  return data
}

export async function getStudentLesson(id) {
  const { data } = await apiClient.get(`/student/materials/${id}`)
  return data.data
}
