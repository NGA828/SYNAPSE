import apiClient from './apiClient.js'

/**
 * Bulk import (students / teachers) — school admin.
 */

export async function importRows(type, rows) {
  const { data } = await apiClient.post('/admin/import', { type, rows })
  return data
}
