import apiClient from './apiClient.js'

/**
 * Student documents (issued by the administration).
 */

export async function getStudentDocuments() {
  const { data } = await apiClient.get('/student/documents')
  return data.data
}

/**
 * Download a document. In mock mode a plain-text file is generated
 * client-side; against the real API the file is fetched as a blob.
 */
export async function downloadDocument(document) {
  if (import.meta.env.VITE_USE_MOCK === 'true') {
    const content = [
      'SYNAPSE — OFFICIAL DOCUMENT',
      '=============================',
      `Type: ${document.title}`,
      `Reference: ${document.request?.reference ?? '—'}`,
      `Issued: ${new Date().toISOString().slice(0, 10)}`,
      '',
      'This document was generated and issued through SYNAPSE.',
    ].join('\n')

    triggerBlobDownload(content, `${document.title.toLowerCase().replace(/\s+/g, '-')}.txt`, 'text/plain')
    return
  }

  const response = await apiClient.get(`/student/documents/${document.id}/download`, {
    responseType: 'blob',
  })

  triggerBlobDownload(response.data, document.file_name, document.mime_type)
}

function triggerBlobDownload(content, fileName, mimeType) {
  const blob = content instanceof Blob ? content : new Blob([content], { type: mimeType })
  const url = URL.createObjectURL(blob)
  const anchor = document.createElement('a')
  anchor.href = url
  anchor.download = fileName
  document.body.appendChild(anchor)
  anchor.click()
  anchor.remove()
  URL.revokeObjectURL(url)
}
