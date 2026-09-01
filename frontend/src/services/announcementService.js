import apiClient from './apiClient.js'

/**
 * Announcements — the listing endpoint is role-aware; publishing is admin-only.
 */

export async function getAnnouncements() {
  const { data } = await apiClient.get('/announcements')
  return data.data
}

export async function createAnnouncement(payload) {
  const { data } = await apiClient.post('/admin/announcements', payload)
  return data.data
}

/**
 * Draft an announcement from a brief.
 *
 * Returns suggested {title, body} and persists nothing. The administrator still
 * reads it, edits it and presses Publish — drafting cannot reach the audience
 * fan-out, which is the point.
 */
export async function draftAnnouncement(payload) {
  const { data } = await apiClient.post('/admin/announcements/draft', payload)
  return data.data
}
