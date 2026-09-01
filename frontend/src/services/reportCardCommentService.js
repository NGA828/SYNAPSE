import apiClient from './apiClient.js'

/**
 * Report-card appreciations.
 *
 * `draft` returns suggested text and saves nothing, so a teacher can regenerate
 * as often as they like. Only `save` records text a person has approved, and
 * `lock` is what makes it appear on the PDF.
 */

export async function getReportCardComment(studentId, params = {}) {
  const { data } = await apiClient.get(`/teacher/students/${studentId}/report-card-comment`, { params })
  return data.data
}

export async function draftReportCardComment(studentId, params = {}) {
  const { data } = await apiClient.post(
    `/teacher/students/${studentId}/report-card-comment/draft`,
    {},
    { params },
  )
  return data.data
}

export async function saveReportCardComment(studentId, payload) {
  const { data } = await apiClient.put(`/teacher/students/${studentId}/report-card-comment`, payload)
  return data.data
}
