import { Navigate, useLocation } from 'react-router-dom'
import { useAuth } from '../hooks/useAuth.js'
import { Spinner } from '../components/ui/Spinner.jsx'

function FullPageSpinner() {
  return (
    <div className="flex min-h-screen items-center justify-center bg-slate-50">
      <Spinner className="size-8" />
    </div>
  )
}

/**
 * Allow access only when a valid session exists; otherwise redirect to login.
 */
export function ProtectedRoute({ children }) {
  const { isAuthenticated, loading, mustChangePassword } = useAuth()
  const location = useLocation()

  if (loading) return <FullPageSpinner />
  if (!isAuthenticated) return <Navigate to="/login" replace state={{ from: location }} />

  // Accounts still holding a one-time password can go nowhere else; the API
  // enforces the same rule, this just avoids a wall of 403s.
  if (mustChangePassword && location.pathname !== '/account/password') {
    return <Navigate to="/account/password" replace />
  }

  return children
}

/**
 * Restrict a route to a set of roles; other roles see the forbidden page.
 */
export function RoleGuard({ allowed, children }) {
  const { role } = useAuth()

  if (!allowed.includes(role)) {
    return <Navigate to="/forbidden" replace />
  }

  return children
}
