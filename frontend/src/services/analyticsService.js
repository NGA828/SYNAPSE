import apiClient from './apiClient.js'

/**
 * Academic analytics and the pastoral register.
 *
 * `path` is `/admin` or `/teacher`: the same endpoints exist under both, and
 * the backend scopes the numbers to the caller, so a teacher gets their own
 * classes rather than the whole school.
 */

export async function getOverview(path = '/admin') {
  const { data } = await apiClient.get(`${path}/analytics`)
  return data
}

/**
 * Flagged students, worst first. Accepts `class_id`, `severity`, `search`,
 * `page` and `per_page`.
 */
export async function getAtRisk({ path = '/admin', ...params } = {}) {
  const { data } = await apiClient.get(`${path}/analytics/at-risk`, { params })
  return data
}

/** One student's full record, including every signal and the numbers behind it. */
export async function getStudent(id, path = '/admin') {
  const { data } = await apiClient.get(`${path}/analytics/students/${id}`)
  return data
}

/** A student's own signals. */
export async function getMyInsights() {
  const { data } = await apiClient.get('/student/insights')
  return data
}
