import apiClient from './apiClient.js'

/**
 * Authenticate and return { token, user }.
 */
export async function login(credentials) {
  const { data } = await apiClient.post('/login', credentials)
  return data
}

/**
 * Fetch the currently authenticated user.
 */
export async function getUser() {
  const { data } = await apiClient.get('/user')
  return data
}

/**
 * Revoke the current session token.
 */
export async function logout() {
  const { data } = await apiClient.post('/logout')
  return data
}
