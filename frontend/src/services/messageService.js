import apiClient from './apiClient.js'

/**
 * Direct messages (shared by every role).
 *
 * The backend decides who may open a thread with whom: a student may write to
 * a teacher or an administrator, but not to another student. The UI reflects
 * that by listing only the people the backend allows.
 */

export async function listConversations(params) {
  const { data } = await apiClient.get('/messages', { params })
  return data
}

/** Open — or resume — a thread. Idempotent: the same pair returns one thread. */
export async function startConversation(userId) {
  const { data } = await apiClient.post('/messages', { user_id: userId })
  return data.data
}

/** A page of one thread, oldest first. Reading it also marks it read. */
export async function getConversation(id, params) {
  const { data } = await apiClient.get(`/messages/${id}`, { params })
  return data
}

export async function sendMessage(id, body) {
  const { data } = await apiClient.post(`/messages/${id}`, { body })
  return data.data
}

export async function markConversationRead(id) {
  const { data } = await apiClient.post(`/messages/${id}/read`)
  return data
}

/** Unread count across every thread — the sidebar badge. */
export async function getUnreadCount() {
  const { data } = await apiClient.get('/messages/unread')
  return data.unread ?? 0
}

/** People this user is allowed to start a conversation with. */
export async function listRecipients(params) {
  const { data } = await apiClient.get('/messages/recipients', { params })
  return data.data ?? []
}
