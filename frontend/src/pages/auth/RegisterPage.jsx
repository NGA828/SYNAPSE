import { Link } from 'react-router-dom'
import { Info, UserPlus } from 'lucide-react'
import { Logo } from '../../components/brand/Logo.jsx'
import { Button } from '../../components/ui/Button.jsx'

export default function RegisterPage() {
  return (
    <div className="flex min-h-screen items-center justify-center bg-slate-50 px-4">
      <div className="w-full max-w-md">
        <div className="flex justify-center">
          <Logo />
        </div>

        <div className="mt-8 text-center">
          <span className="inline-flex size-12 items-center justify-center rounded-2xl bg-brand-50 text-brand-600">
            <UserPlus className="size-6" aria-hidden="true" />
          </span>
          <h1 className="mt-4 text-2xl font-bold tracking-tight text-slate-900">
            Accounts are provisioned by your school
          </h1>
          <p className="mt-2 text-sm text-slate-500">
            SYNAPSE accounts are created by administrators so that every user is correctly
            linked to their classes, subjects and academic history.
          </p>
        </div>

        <div className="mt-6 flex items-start gap-3 rounded-xl border border-brand-100 bg-brand-50 p-4 text-left">
          <Info className="mt-0.5 size-4 shrink-0 text-brand-600" aria-hidden="true" />
          <p className="text-sm text-brand-800">
            If you need access, contact your school administrator with your name and matricule or
            staff number. They will issue your credentials.
          </p>
        </div>

        <Link to="/login" className="mt-6 block">
          <Button variant="secondary" className="w-full">
            Back to sign in
          </Button>
        </Link>
      </div>
    </div>
  )
}
