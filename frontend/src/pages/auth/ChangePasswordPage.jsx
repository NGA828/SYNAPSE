import { useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { KeyRound } from 'lucide-react'
import { useAuth } from '../../hooks/useAuth.js'
import { getRolePath } from '../../hooks/useRoleRedirect.js'
import { changePassword } from '../../services/authService.js'
import { Logo } from '../../components/brand/Logo.jsx'
import { Button } from '../../components/ui/Button.jsx'
import { Input } from '../../components/ui/Input.jsx'
import { ErrorDisplay } from '../../components/forms/ErrorDisplay.jsx'
import { PasswordRules } from '../../components/forms/PasswordRules.jsx'

/**
 * Shown when an account still carries the one-time password issued by an
 * administrator. The API blocks every other endpoint until this is done.
 */
export default function ChangePasswordPage() {
  const { user, role, refresh } = useAuth()
  const navigate = useNavigate()

  const [form, setForm] = useState({ current_password: '', password: '', password_confirmation: '' })
  const [error, setError] = useState(null)
  const [loading, setLoading] = useState(false)

  const setField = (field) => (event) => setForm((current) => ({ ...current, [field]: event.target.value }))

  const handleSubmit = async (event) => {
    event.preventDefault()
    setLoading(true)
    setError(null)

    try {
      await changePassword(form)
      await refresh?.()
      navigate(getRolePath(role), { replace: true })
    } catch (err) {
      const errors = err?.response?.data?.errors ?? {}
      setError(
        errors.current_password?.[0] ??
          errors.password?.[0] ??
          err?.response?.data?.message ??
          'Could not update your password.',
      )
    } finally {
      setLoading(false)
    }
  }

  return (
    <div className="flex min-h-screen items-center justify-center bg-slate-50 px-4">
      <div className="w-full max-w-md">
        <div className="flex justify-center">
          <Logo />
        </div>

        <form onSubmit={handleSubmit} className="mt-8 space-y-4">
          <div className="text-center">
            <span className="inline-flex size-12 items-center justify-center rounded-2xl bg-amber-50 text-amber-600">
              <KeyRound className="size-6" aria-hidden="true" />
            </span>
            <h1 className="mt-4 text-2xl font-bold tracking-tight text-slate-900">Choose your own password</h1>
            <p className="mt-2 text-sm text-slate-500">
              {user?.name ? `Welcome ${user.name}. ` : ''}
              Your account was created with a temporary password. Pick a new one to continue.
            </p>
          </div>

          <Input
            label="Temporary password"
            type="password"
            autoComplete="current-password"
            value={form.current_password}
            onChange={setField('current_password')}
            required
          />
          <Input
            label="New password"
            type="password"
            autoComplete="new-password"
            value={form.password}
            onChange={setField('password')}
            required
          />
          <Input
            label="Confirm new password"
            type="password"
            autoComplete="new-password"
            value={form.password_confirmation}
            onChange={setField('password_confirmation')}
            required
          />

          <PasswordRules value={form.password} confirmation={form.password_confirmation} />

          <ErrorDisplay message={error} />

          <Button type="submit" className="w-full" loading={loading}>
            Save and continue
          </Button>
        </form>
      </div>
    </div>
  )
}
