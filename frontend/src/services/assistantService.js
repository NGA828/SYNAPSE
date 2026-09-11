import apiClient from './apiClient.js'

/**
 * The student AI study assistant.
 *
 * The conversation is stateless: the client owns the transcript and replays
 * its most recent turns with every request. Nothing is stored server-side,
 * so "delete the conversation" is a purely local act.
 */

const TIMEOUT_MS = 30000

export async function sendAssistantMessage({ message, history = [] }) {
  const { data } = await apiClient.post(
    '/student/assistant/chat',
    { message, history },
    { timeout: TIMEOUT_MS },
  )

  return data
}
