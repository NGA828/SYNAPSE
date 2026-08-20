const EMAIL_REGEX = /^[^\s@]+@[^\s@]+\.[^\s@]+$/

export function isValidEmail(email) {
  return EMAIL_REGEX.test(String(email ?? '').trim())
}

export function validatePassword(password) {
  const isValid = typeof password === 'string' && password.length >= 8

  return {
    isValid,
    message: 'Password must be at least 8 characters.',
  }
}

/**
 * Normalize Laravel validation errors ({ field: [message, ...] }) into a
 * flat { field: message } map for the UI.
 */
export function normalizeApiErrors(errors = {}) {
  return Object.fromEntries(
    Object.entries(errors).map(([key, value]) => [
      key,
      Array.isArray(value) ? value[0] : value,
    ]),
  )
}

/**
 * Validate the login form and return a flat { field: message } errors map.
 */
export function validateLoginForm({ email, password }) {
  const errors = {}

  if (!email || !String(email).trim()) {
    errors.email = 'Email is required.'
  } else if (!isValidEmail(email)) {
    errors.email = 'Enter a valid email address.'
  }

  if (!password) {
    errors.password = 'Password is required.'
  } else if (!validatePassword(password).isValid) {
    errors.password = validatePassword(password).message
  }

  return errors
}
