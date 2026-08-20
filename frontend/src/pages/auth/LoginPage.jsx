import { Link, Navigate } from 'react-router-dom'
import { ClipboardCheck, GraduationCap, ShieldCheck } from 'lucide-react'
import { useAuth } from '../../hooks/useAuth.js'
import { getRolePath, useRoleRedirect } from '../../hooks/useRoleRedirect.js'
import { LoginForm } from '../../components/forms/LoginForm.jsx'
import { Logo } from '../../components/brand/Logo.jsx'
import { Spinner } from '../../components/ui/Spinner.jsx'

const DEMO_ACCOUNTS = [
  { label: 'Super Admin', email: 'superadmin@synapse.test' },
  { label: 'Administrator', email: 'admin@synapse.test' },
  { label: 'Teacher', email: 'teacher@synapse.test' },
  { label: 'Student', email: 'student@synapse.test' },
  { label: 'School B Admin', email: 'admin.saintalbert@synapse.test' },
]

const FEATURES = [
  { icon: GraduationCap, text: 'Isolated tenants — every school sees only its own data' },
  { icon: ShieldCheck, text: 'Server-enforced permissions, never frontend-only' },
  { icon: ClipboardCheck, text: 'Grades, timetables and requests in one place' },
]

export default function LoginPage() {
  const { isAuthenticated, role, loading } = useAuth()
  const { redirectByRole } = useRoleRedirect()

  if (loading) {
    return (
      <div className="flex min-h-screen items-center justify-center bg-slate-50">
        <Spinner className="size-8" />
      </div>
    )
  }

  if (isAuthenticated) {
    return <Navigate to={getRolePath(role)} replace />
  }

  return (
    <div className="grid min-h-screen lg:grid-cols-2">
      <div className="relative hidden overflow-hidden lg:block">
        <div className="absolute inset-0 bg-gradient-to-br from-brand-700 via-violet-700 to-brand-900" />
        <div className="absolute inset-0 bg-grid-slate opacity-40" />
        <div className="relative flex h-full flex-col justify-between p-12">
          <Logo className="text-white" />
          <div>
            <h2 className="max-w-md text-3xl font-bold leading-tight text-white">
              One platform for the whole school.
            </h2>
            <p className="mt-3 max-w-md text-brand-100">
              Students, teachers and administrators — connected through a single, secure portal.
            </p>
            <ul className="mt-8 space-y-3">
              {FEATURES.map(({ icon: Icon, text }) => (
                <li key={text} className="flex items-center gap-3 text-sm text-brand-50">
                  <span className="flex size-8 items-center justify-center rounded-lg bg-white/10">
                    <Icon className="size-4" aria-hidden="true" />
                  </span>
                  {text}
                </li>
              ))}
            </ul>
          </div>
          <p className="text-sm text-brand-200">© 2026 SYNAPSE · Multi-tenant school platform</p>
        </div>
      </div>

      <div className="flex items-center justify-center px-4 py-10 sm:px-8">
        <div className="w-full max-w-sm">
          <div className="mb-8 lg:hidden">
            <Logo />
          </div>
          <h1 className="text-2xl font-bold tracking-tight text-slate-900">Welcome back</h1>
          <p className="mt-1 text-sm text-slate-500">Sign in to your Synapse account.</p>

          <div className="mt-6">
            <LoginForm onSuccess={(user) => redirectByRole(user.role)} />
          </div>

          {import.meta.env.VITE_USE_MOCK === 'true' ? (
            <div className="mt-6 rounded-xl border border-slate-200 bg-slate-50 p-4">
              <p className="text-xs font-semibold text-slate-700">Demo accounts (mock mode)</p>
              <ul className="mt-2 space-y-1">
                {DEMO_ACCOUNTS.map((account) => (
                  <li key={account.email} className="flex justify-between text-xs text-slate-500">
                    <span>{account.label}</span>
                    <span className="font-mono">{account.email}</span>
                  </li>
                ))}
              </ul>
              <p className="mt-2 text-xs text-slate-400">
                Password for all: <span className="font-mono">password123</span>
              </p>
            </div>
          ) : null}

          <p className="mt-6 text-center text-xs text-slate-400">
            New school?{' '}
            <Link to="/onboarding" className="font-medium text-brand-600 hover:underline">
              Register your school
            </Link>{' '}
            ·{' '}
            <Link to="/register" className="font-medium text-brand-600 hover:underline">
              About accounts
            </Link>
          </p>
        </div>
      </div>
    </div>
  )
}
