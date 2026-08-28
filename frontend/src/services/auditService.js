import apiClient from './apiClient.js'

/**
 * Audit trail (read-only). Admins see their own school; super admins see the
 * whole platform and can filter by school.
 */
export async function listAuditLogs(params = {}) {
  const { data } = await apiClient.get('/admin/audit-logs', { params })
  return data
}

export async function listPlatformAuditLogs(params = {}) {
  const { data } = await apiClient.get('/super-admin/audit-logs', { params })
  return data
}
