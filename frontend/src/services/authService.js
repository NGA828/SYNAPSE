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

/**
 * Request a password reset link. The API never reveals whether the address
 * exists, so the same confirmation is shown either way.
 */
export async function forgotPassword(email) {
  const { data } = await apiClient.post('/forgot-password', { email })
  return data
}

/**
 * Complete a reset using the token from the e-mail.
 */
export async function resetPassword(payload) {
  const { data } = await apiClient.post('/reset-password', payload)
  return data
}

/**
 * Rotate the signed-in user's password (also clears a forced rotation).
 */
export async function changePassword(payload) {
  const { data } = await apiClient.post('/password', payload)
  return data
}

/**
 * Profile + active sessions.
 */
export async function getProfile() {
  const { data } = await apiClient.get('/profile')
  return data
}

export async function updateProfile(payload) {
  const { data } = await apiClient.patch('/profile', payload)
  return data
}

export async function signOutOtherSessions() {
  const { data } = await apiClient.post('/profile/sign-out-others')
  return data
}

/**
 * Public authenticity check for a printed document.
 */
export async function verifyDocument(code) {
  const { data } = await apiClient.get(`/verify/${encodeURIComponent(code)}`)
  return data
}
