import { Link } from 'react-router-dom'
import { Compass } from 'lucide-react'
import { Logo } from '../../components/brand/Logo.jsx'
import { Button } from '../../components/ui/Button.jsx'

export default function NotFoundPage() {
  return (
    <div className="flex min-h-screen flex-col items-center justify-center px-4 text-center">
      <Logo />
      <Compass className="mt-8 size-12 text-brand-500" aria-hidden="true" />
      <h1 className="mt-4 text-2xl font-bold tracking-tight text-slate-900">Page not found</h1>
      <p className="mt-2 max-w-sm text-sm text-slate-500">
        The page you&apos;re looking for doesn&apos;t exist or has moved.
      </p>
      <Link to="/" className="mt-6">
        <Button variant="secondary">Back to home</Button>
      </Link>
    </div>
  )
}
