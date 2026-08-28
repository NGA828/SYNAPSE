import { useState } from 'react'
import { Link } from 'react-router-dom'
import { KeyRound, MailCheck } from 'lucide-react'
import { forgotPassword } from '../../services/authService.js'
import { Logo } from '../../components/brand/Logo.jsx'
import { Button } from '../../components/ui/Button.jsx'
import { Input } from '../../components/ui/Input.jsx'
import { ErrorDisplay } from '../../components/forms/ErrorDisplay.jsx'

export default function ForgotPasswordPage() {
  const [email, setEmail] = useState('')
  const [sent, setSent] = useState(null)
  const [error, setError] = useState(null)
  const [loading, setLoading] = useState(false)

  const handleSubmit = async (event) => {
    event.preventDefault()
    setLoading(true)
    setError(null)

    try {
      const { message } = await forgotPassword(email)
      setSent(message ?? 'If that address belongs to an account, a reset link is on its way.')
    } catch (err) {
      setError(
        err?.response?.data?.errors?.email?.[0] ??
          err?.response?.data?.message ??
          'We could not send the reset link. Please try again.',
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

        {sent ? (
          <div className="mt-8 rounded-2xl border border-emerald-100 bg-emerald-50 p-6 text-center">
            <span className="inline-flex size-12 items-center justify-center rounded-2xl bg-emerald-100 text-emerald-700">
              <MailCheck className="size-6" aria-hidden="true" />
            </span>
            <h1 className="mt-4 text-lg font-semibold text-emerald-900">Check your inbox</h1>
            <p className="mt-2 text-sm text-emerald-800">{sent}</p>
            <Link to="/login" className="mt-6 block">
              <Button variant="secondary" className="w-full">
                Back to sign in
              </Button>
            </Link>
          </div>
        ) : (
          <form onSubmit={handleSubmit} className="mt-8 space-y-4">
            <div className="text-center">
              <span className="inline-flex size-12 items-center justify-center rounded-2xl bg-brand-50 text-brand-600">
                <KeyRound className="size-6" aria-hidden="true" />
              </span>
              <h1 className="mt-4 text-2xl font-bold tracking-tight text-slate-900">Forgot your password?</h1>
              <p className="mt-2 text-sm text-slate-500">
                Enter the e-mail address on your account and we will send you a link to choose a new password.
              </p>
            </div>

            <Input
              label="Email address"
              type="email"
              name="email"
              autoComplete="email"
              required
              value={email}
              onChange={(event) => setEmail(event.target.value)}
              placeholder="you@school.edu"
            />

            <ErrorDisplay message={error} />

            <Button type="submit" className="w-full" loading={loading}>
              Send reset link
            </Button>

            <Link to="/login" className="block text-center text-sm text-brand-600 hover:underline">
              Back to sign in
            </Link>
          </form>
        )}
      </div>
    </div>
  )
}
