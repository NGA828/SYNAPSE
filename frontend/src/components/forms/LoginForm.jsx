import { useState } from 'react'
import { useAuth } from '../../hooks/useAuth.js'
import { normalizeApiErrors, validateLoginForm } from '../../utils/validators.js'
import { Button } from '../ui/Button.jsx'
import { Input } from '../ui/Input.jsx'
import { ErrorDisplay } from './ErrorDisplay.jsx'

export function LoginForm({ onSuccess }) {
  const { login } = useAuth()
  const [values, setValues] = useState({ email: '', password: '' })
  const [errors, setErrors] = useState({})
  const [serverError, setServerError] = useState(null)
  const [submitting, setSubmitting] = useState(false)

  const handleChange = (event) => {
    const { name, value } = event.target
    setValues((current) => ({ ...current, [name]: value }))
  }

  const handleSubmit = async (event) => {
    event.preventDefault()

    const nextErrors = validateLoginForm(values)
    setErrors(nextErrors)
    if (Object.keys(nextErrors).length > 0) return

    setSubmitting(true)
    setServerError(null)

    try {
      const user = await login(values)
      onSuccess(user)
    } catch (error) {
      const payload = error?.response?.data
      if (payload?.errors) {
        setErrors(normalizeApiErrors(payload.errors))
      } else {
        setServerError(payload?.message ?? 'Something went wrong. Please try again.')
      }
    } finally {
      setSubmitting(false)
    }
  }

  return (
    <form onSubmit={handleSubmit} noValidate className="space-y-4">
      <ErrorDisplay message={serverError} />
      <Input
        label="Email"
        name="email"
        type="email"
        autoComplete="email"
        placeholder="you@school.edu"
        value={values.email}
        onChange={handleChange}
        error={errors.email}
      />
      <Input
        label="Password"
        name="password"
        type="password"
        autoComplete="current-password"
        placeholder="••••••••"
        value={values.password}
        onChange={handleChange}
        error={errors.password}
      />
      <Button type="submit" size="lg" loading={submitting} className="w-full">
        Sign in
      </Button>
    </form>
  )
}
