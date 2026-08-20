import apiClient from './apiClient.js'

/**
 * School-admin billing (plan, usage, upgrade, renew, payment history).
 */

export async function getBilling() {
  const { data } = await apiClient.get('/admin/billing')
  return data
}

export async function upgradePlan(planId, method = 'mock') {
  const { data } = await apiClient.post('/admin/billing/upgrade', { plan_id: planId, provider: 'mock', method })
  return data
}

export async function renewPlan(method = 'mock') {
  const { data } = await apiClient.post('/admin/billing/renew', { provider: 'mock', method })
  return data
}
