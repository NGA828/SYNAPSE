import apiClient from './apiClient.js'
import { downloadFile } from './downloadService.js'
import { multipartConfig, toFormData } from './uploadHelpers.js'

/**
 * Homework endpoints.
 *
 * Teacher routes are scoped by the backend to the authenticated teacher's own
 * homework; student routes to published homework for their enrolled class.
 */

// --- Teacher ---------------------------------------------------------------

export async function listTeacherHomework(params) {
  const { data } = await apiClient.get('/teacher/homework', { params })
  return data
}

export async function createHomework(payload, files = []) {
  const { data } = await apiClient.post('/teacher/homework', toFormData(payload, files), multipartConfig)
  return data.data
}

export async function updateHomework(id, payload, files = []) {
  const body = toFormData(payload, files)
  // Laravel needs this to treat a multipart POST as a PUT.
  body.append('_method', 'PUT')

  const { data } = await apiClient.post(`/teacher/homework/${id}`, body, multipartConfig)
  return data.data
}

export async function deleteHomework(id) {
  const { data } = await apiClient.delete(`/teacher/homework/${id}`)
  return data
}

export async function publishHomework(id) {
  const { data } = await apiClient.post(`/teacher/homework/${id}/publish`)
  return data.data
}

export async function unpublishHomework(id) {
  const { data } = await apiClient.post(`/teacher/homework/${id}/unpublish`)
  return data.data
}

export async function getHomeworkSubmissions(id) {
  const { data } = await apiClient.get(`/teacher/homework/${id}/submissions`)
  return data
}

export async function gradeSubmission(submissionId, payload) {
  const { data } = await apiClient.post(`/teacher/homework-submissions/${submissionId}/grade`, payload)
  return data.data
}

// --- Student ---------------------------------------------------------------

export async function listStudentHomework() {
  const { data } = await apiClient.get('/student/homework')
  return data
}

/**
 * Submit — text, files, or both. Multipart whenever a file is attached.
 */
export async function submitHomework(homeworkId, content, files = []) {
  const hasFiles = (files ?? []).filter(Boolean).length > 0

  const { data } = hasFiles
    ? await apiClient.post(`/student/homework/${homeworkId}/submit`, toFormData({ content }, files), multipartConfig)
    : await apiClient.post(`/student/homework/${homeworkId}/submit`, { content })

  return data.data
}

// --- Attachments -----------------------------------------------------------

export function downloadAttachment(attachment) {
  return downloadFile(attachment.download_url, attachment.file_name)
}
