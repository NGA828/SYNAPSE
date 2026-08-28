import apiClient from './apiClient.js'

/**
 * Fetch a binary endpoint and hand it to the browser as a file download.
 *
 * Kept in one place so every PDF button behaves the same: correct auth header,
 * correct filename, and object URLs always revoked.
 */
export async function downloadFile(url, fallbackName, { params } = {}) {
  const response = await apiClient.get(url, { params, responseType: 'blob' })

  const disposition = response.headers?.['content-disposition'] ?? ''
  const match = /filename="?([^";]+)"?/i.exec(disposition)
  const filename = match?.[1] ?? fallbackName

  const blob = response.data instanceof Blob ? response.data : new Blob([response.data])
  const href = URL.createObjectURL(blob)
  const link = document.createElement('a')

  link.href = href
  link.download = filename
  document.body.appendChild(link)
  link.click()
  link.remove()

  // Give Safari a tick before revoking, otherwise the download is cancelled.
  window.setTimeout(() => URL.revokeObjectURL(href), 1000)

  return filename
}

export function downloadReportCard(semesterId) {
  return downloadFile('/student/report-card/pdf', 'report-card.pdf', {
    params: semesterId ? { semester_id: semesterId } : undefined,
  })
}

export function downloadTranscript() {
  return downloadFile('/student/transcript/pdf', 'transcript.pdf')
}

export function downloadStudentReportCard(studentId, semesterId) {
  return downloadFile(`/admin/students/${studentId}/report-card`, `report-card-${studentId}.pdf`, {
    params: semesterId ? { semester_id: semesterId } : undefined,
  })
}

export function downloadStudentTranscript(studentId) {
  return downloadFile(`/admin/students/${studentId}/transcript`, `transcript-${studentId}.pdf`)
}

export function downloadReceipt(paymentId) {
  return downloadFile(`/admin/payments/${paymentId}/receipt`, `receipt-${paymentId}.pdf`)
}

/**
 * Queue report cards for an entire class (returns immediately, 202).
 */
export async function generateClassReportCards(classId, semesterId, notify = true) {
  const { data } = await apiClient.post(`/admin/classes/${classId}/report-cards`, {
    semester_id: semesterId,
    notify,
  })
  return data
}
