import { useParams } from 'react-router-dom'
import { useAsync } from '../../hooks/useAsyncList.js'
import { useRoleRedirect } from '../../hooks/useRoleRedirect.js'
import { getPublicSchool } from '../../services/tenantService.js'
import { LoginForm } from '../../components/forms/LoginForm.jsx'
import { Spinner } from '../../components/ui/Spinner.jsx'
import { LogoMark } from '../../components/brand/Logo.jsx'

export default function SchoolLoginPage() {
  const { slug } = useParams()
  const { data: school, loading } = useAsync(() => getPublicSchool(slug), [slug])
  const { redirectByRole } = useRoleRedirect()

  return (
    <div className="flex min-h-screen items-center justify-center bg-slate-50 px-4">
      <div className="w-full max-w-sm">
        <div className="flex flex-col items-center text-center">
          {loading ? (
            <Spinner className="size-8" />
          ) : (
            <>
              <LogoMark className="size-12" />
              <h1 className="mt-4 text-2xl font-bold tracking-tight text-slate-900">{school?.name ?? 'SYNAPSE'}</h1>
              <p className="mt-1 text-sm text-slate-500">Sign in to your school portal.</p>
            </>
          )}
        </div>
        <div className="mt-8">
          <LoginForm onSuccess={(user) => redirectByRole(user.role)} />
        </div>
      </div>
    </div>
  )
}
