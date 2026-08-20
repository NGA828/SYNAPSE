import { Link } from 'react-router-dom'
import { ShieldAlert } from 'lucide-react'
import { Logo } from '../../components/brand/Logo.jsx'
import { Button } from '../../components/ui/Button.jsx'

export default function ForbiddenPage() {
  return (
    <div className="flex min-h-screen flex-col items-center justify-center px-4 text-center">
      <Logo />
      <ShieldAlert className="mt-8 size-12 text-rose-500" aria-hidden="true" />
      <h1 className="mt-4 text-2xl font-bold tracking-tight text-slate-900">Access denied</h1>
      <p className="mt-2 max-w-sm text-sm text-slate-500">
        You don&apos;t have permission to view this page. If you believe this is a mistake,
        contact your administrator.
      </p>
      <Link to="/" className="mt-6">
        <Button variant="secondary">Back to home</Button>
      </Link>
    </div>
  )
}
