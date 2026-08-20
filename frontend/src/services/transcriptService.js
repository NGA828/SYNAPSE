import apiClient from './apiClient.js'

/**
 * Student transcript (multi-year academic history).
 */

export async function getTranscript() {
  const { data } = await apiClient.get('/student/transcript')
  return data
}
