import { useState } from 'react'
import { Link, useNavigate, useSearchParams } from 'react-router-dom'
import { CheckCircle2, ShieldCheck } from 'lucide-react'
import { resetPassword } from '../../services/authService.js'
import { Logo } from '../../components/brand/Logo.jsx'
import { Button } from '../../components/ui/Button.jsx'
import { Input } from '../../components/ui/Input.jsx'
import { ErrorDisplay } from '../../components/forms/ErrorDisplay.jsx'
import { PasswordRules } from '../../components/forms/PasswordRules.jsx'

export default function ResetPasswordPage() {
  const [params] = useSearchParams()
  const navigate = useNavigate()

  const token = params.get('token') ?? ''
  const emailFromLink = params.get('email') ?? ''

  const [form, setForm] = useState({ email: emailFromLink, password: '', password_confirmation: '' })
  const [error, setError] = useState(null)
  const [loading, setLoading] = useState(false)
  const [done, setDone] = useState(false)

  const setField = (field) => (event) => setForm((current) => ({ ...current, [field]: event.target.value }))

  const handleSubmit = async (event) => {
    event.preventDefault()
    setLoading(true)
    setError(null)

    try {
      await resetPassword({ ...form, token })
      setDone(true)
      window.setTimeout(() => navigate('/login', { replace: true }), 2500)
    } catch (err) {
      const errors = err?.response?.data?.errors ?? {}
      setError(
        errors.email?.[0] ??
          errors.password?.[0] ??
          err?.response?.data?.message ??
          'This reset link is invalid or has expired.',
      )
    } finally {
      setLoading(false)
    }
  }

  if (!token) {
    return (
      <div className="flex min-h-screen items-center justify-center bg-slate-50 px-4">
        <div className="w-full max-w-md text-center">
          <Logo />
          <h1 className="mt-8 text-xl font-semibold text-slate-900">This link is incomplete</h1>
          <p className="mt-2 text-sm text-slate-500">
            Open the link exactly as it appears in the e-mail, or request a new one.
          </p>
          <Link to="/forgot-password" className="mt-6 block">
            <Button className="w-full">Request a new link</Button>
          </Link>
        </div>
      </div>
    )
  }

  return (
    <div className="flex min-h-screen items-center justify-center bg-slate-50 px-4">
      <div className="w-full max-w-md">
        <div className="flex justify-center">
          <Logo />
        </div>

        {done ? (
          <div className="mt-8 rounded-2xl border border-emerald-100 bg-emerald-50 p-6 text-center">
            <CheckCircle2 className="mx-auto size-10 text-emerald-600" aria-hidden="true" />
            <h1 className="mt-3 text-lg font-semibold text-emerald-900">Password updated</h1>
            <p className="mt-1 text-sm text-emerald-800">Redirecting you to sign in…</p>
          </div>
        ) : (
          <form onSubmit={handleSubmit} className="mt-8 space-y-4">
            <div className="text-center">
              <span className="inline-flex size-12 items-center justify-center rounded-2xl bg-brand-50 text-brand-600">
                <ShieldCheck className="size-6" aria-hidden="true" />
              </span>
              <h1 className="mt-4 text-2xl font-bold tracking-tight text-slate-900">Choose a new password</h1>
            </div>

            <Input label="Email address" type="email" value={form.email} onChange={setField('email')} required />
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
              Reset password
            </Button>
          </form>
        )}
      </div>
    </div>
  )
}
