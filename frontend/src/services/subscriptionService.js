import apiClient from './apiClient.js'

/**
 * Plans, subscriptions and payments (platform super admin).
 */

export async function listPlans() {
  const { data } = await apiClient.get('/super-admin/plans')
  return data.data
}

export async function createPlan(payload) {
  const { data } = await apiClient.post('/super-admin/plans', payload)
  return data.data
}

export async function updatePlan(id, payload) {
  const { data } = await apiClient.put(`/super-admin/plans/${id}`, payload)
  return data.data
}

export async function listSubscriptions() {
  const { data } = await apiClient.get('/super-admin/subscriptions')
  return data.data
}

export async function listPayments() {
  const { data } = await apiClient.get('/super-admin/payments')
  return data.data
}
